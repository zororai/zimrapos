<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * FDMS Canonical Signature Methods (Section 13.2.1)
 * 
 * These methods implement the canonical string format required by FDMS
 * for receipt signature generation per API specification section 13.2.1
 */
trait FDMSCanonicalSignature
{
    /**
     * Build canonical string for signature per FDMS spec section 13.2.1
     * 
     * Format: deviceID||receiptType||receiptCurrency||receiptGlobalNo||receiptDate||receiptTotal(cents)||receiptTaxes||previousReceiptHash
     * 
     * @param array $receipt Receipt data
     * @param int $deviceId Device ID
     * @return string Canonical concatenated string
     */
    private function buildCanonicalStringForSignature(array $receipt, int $deviceId): string
    {
        $parts = [];
        
        // 1. deviceID (integer)
        $parts[] = (string) $deviceId;
        
        // 2. receiptType (uppercase)
        $parts[] = strtoupper($receipt['receiptType'] ?? 'FISCALINVOICE');
        
        // 3. receiptCurrency (uppercase)
        $parts[] = strtoupper($receipt['receiptCurrency'] ?? 'USD');
        
        // 4. receiptGlobalNo (integer)
        $parts[] = (string) ($receipt['receiptGlobalNo'] ?? 0);
        
        // 5. receiptDate (ISO 8601 format)
        $parts[] = $receipt['receiptDate'] ?? '';
        
        // 6. receiptTotal (in CENTS - multiply by 100 and remove decimals)
        $receiptTotal = $receipt['receiptTotal'] ?? '0.00';
        $receiptTotalCents = (int) round((float) $receiptTotal * 100);
        $parts[] = (string) $receiptTotalCents;
        
        // 7. receiptTaxes (concatenated: taxCode||taxPercent||taxAmount||salesAmountWithTax)
        //    Amounts in cents, ordered by taxID ascending, then taxCode alphabetically
        $receiptTaxes = $receipt['receiptTaxes'] ?? [];
        $taxesString = $this->buildCanonicalTaxesString($receiptTaxes);
        $parts[] = $taxesString;
        
        // 8. previousReceiptHash (if not first receipt in fiscal day)
        // TODO: Implement receipt chaining when required
        // For now, omit if first receipt
        
        // Concatenate with no separator (fields are already delimited internally)
        $canonicalString = implode('', $parts);
        
        Log::info('CANONICAL_STRING_PARTS', [
            'deviceID' => $parts[0],
            'receiptType' => $parts[1],
            'receiptCurrency' => $parts[2],
            'receiptGlobalNo' => $parts[3],
            'receiptDate' => $parts[4],
            'receiptTotal_cents' => $parts[5],
            'receiptTaxes' => $parts[6],
            'full_string' => $canonicalString,
        ]);
        
        return $canonicalString;
    }
    
    /**
     * Build canonical taxes string per FDMS spec section 13.2.1
     * 
     * Format: taxCode||taxPercent||taxAmount||salesAmountWithTax (for each tax, concatenated)
     * - Amounts in cents
     * - taxPercent with .00 suffix (e.g., 0.00, 15.00, 14.50)
     * - Ordered by taxID ascending, then taxCode alphabetically (empty taxCode before 'A')
     * 
     * @param array $taxes Receipt taxes array
     * @return string Concatenated taxes string
     */
    private function buildCanonicalTaxesString(array $taxes): string
    {
        if (empty($taxes)) {
            return '';
        }
        
        // Sort taxes by taxID ascending, then taxCode alphabetically
        usort($taxes, function ($a, $b) {
            $taxIdCompare = ($a['taxID'] ?? 0) <=> ($b['taxID'] ?? 0);
            if ($taxIdCompare !== 0) {
                return $taxIdCompare;
            }
            
            // Empty taxCode comes before 'A'
            $taxCodeA = $a['taxCode'] ?? '';
            $taxCodeB = $b['taxCode'] ?? '';
            
            if ($taxCodeA === '' && $taxCodeB !== '') return -1;
            if ($taxCodeA !== '' && $taxCodeB === '') return 1;
            
            return strcmp($taxCodeA, $taxCodeB);
        });
        
        $taxParts = [];
        
        foreach ($taxes as $tax) {
            $taxCode = $tax['taxCode'] ?? '';
            $taxPercent = $tax['taxPercent'] ?? '0.00';
            $taxAmount = $tax['taxAmount'] ?? '0.00';
            $salesAmountWithTax = $tax['salesAmountWithTax'] ?? '0.00';
            
            // Format taxPercent with .00 suffix (e.g., 0.00, 15.00, 14.50)
            $taxPercentFloat = (float) $taxPercent;
            $taxPercentFormatted = number_format($taxPercentFloat, 2, '.', '');
            
            // Convert amounts to cents
            $taxAmountCents = (int) round((float) $taxAmount * 100);
            $salesAmountWithTaxCents = (int) round((float) $salesAmountWithTax * 100);
            
            // Concatenate: taxCode||taxPercent||taxAmount||salesAmountWithTax
            $taxString = $taxCode . $taxPercentFormatted . $taxAmountCents . $salesAmountWithTaxCents;
            $taxParts[] = $taxString;
            
            Log::debug('CANONICAL_TAX_ENTRY', [
                'taxCode' => $taxCode,
                'taxPercent' => $taxPercentFormatted,
                'taxAmount_cents' => $taxAmountCents,
                'salesAmountWithTax_cents' => $salesAmountWithTaxCents,
                'concatenated' => $taxString,
            ]);
        }
        
        return implode('', $taxParts);
    }
    
    /**
     * Sign canonical string per FDMS spec
     * 
     * @param string $canonicalString Canonical concatenated string
     * @return array ['hash' => base64, 'signature' => base64]
     */
    private function signCanonicalString(string $canonicalString): array
    {
        Log::info('SIGNING_CANONICAL_STRING', [
            'canonical_string' => $canonicalString,
            'length' => strlen($canonicalString),
        ]);

        // Step 0: Validate certificate and private key
        $certPath = storage_path('app/zimra/device_certificate.pem');
        $privateKeyPath = storage_path('app/zimra/device_private.key');
        
        if (!file_exists($certPath)) {
            throw new \Exception('RCPT025: Device certificate not found. Please register device first.');
        }
        if (!file_exists($privateKeyPath)) {
            throw new \Exception('RCPT025: Device private key not found. Please register device first.');
        }

        // Load and validate certificate
        $certPem = file_get_contents($certPath);
        $cert = openssl_x509_read($certPem);
        if (!$cert) {
            throw new \Exception('RCPT025: Failed to read device certificate: ' . openssl_error_string());
        }

        // Load private key
        $privateKeyPem = file_get_contents($privateKeyPath);
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if (!$privateKey) {
            throw new \Exception('RCPT025: Failed to load private key: ' . openssl_error_string());
        }

        // Step 1: Generate SHA256 hash for the 'hash' field
        $hashBinary = hash('sha256', $canonicalString, true);
        $hashBase64 = base64_encode($hashBinary);

        Log::info('SIGNING_HASH_GENERATED', [
            'hash_base64' => $hashBase64,
            'hash_hex' => bin2hex($hashBinary),
        ]);

        // Step 2: Sign the canonical string (openssl_sign hashes internally with OPENSSL_ALGO_SHA256)
        $signResult = openssl_sign($canonicalString, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);
        
        if (!$signResult) {
            throw new \Exception('Failed to sign receipt: ' . openssl_error_string());
        }

        $signatureBase64 = base64_encode($signatureBinary);

        Log::info('SIGNING_RESULT', [
            'hash_base64' => $hashBase64,
            'signature_base64' => $signatureBase64,
            'signature_length' => strlen($signatureBinary),
        ]);

        // Step 3: Verify signature locally
        $publicKey = openssl_pkey_get_public($cert);
        $verifyResult = openssl_verify($canonicalString, $signatureBinary, $publicKey, OPENSSL_ALGO_SHA256);
        
        Log::info('SIGNING_LOCAL_VERIFY', [
            'verified' => $verifyResult === 1,
            'verify_result_code' => $verifyResult,
            'canonical_first_100_chars' => substr($canonicalString, 0, 100),
            'canonical_last_100_chars' => substr($canonicalString, -100),
        ]);

        if ($verifyResult !== 1) {
            throw new \Exception('RCPT025: Local signature verification failed. Signature does not match canonical string.');
        }

        Log::info('RCPT025_LOCAL_VERIFY_PASSED', [
            'signature_verified' => true,
            'hash' => $hashBase64,
        ]);

        return [
            'hash' => $hashBase64,
            'signature' => $signatureBase64,
        ];
    }
}
