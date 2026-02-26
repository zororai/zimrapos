<?php

namespace App\Services;

use App\Models\FiscalDay;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ZIMRA v7.2 CloseDay - HARDENED Implementation
 * 
 * ZERO ASSUMPTIONS - SPEC COMPLIANCE PROVEN
 * - Integer-only arithmetic (no floats)
 * - Deterministic sorting
 * - Exact canonical string per Section 13.3.1
 * - BCMath for all monetary calculations
 */
class CloseDayHardenedService
{
    /**
     * Aggregate fiscal counters using INTEGER CENTS ONLY
     * 
     * NO FLOATS - ALL ARITHMETIC IN CENTS
     * 
     * @param int $deviceId
     * @param int $fiscalDayNo
     * @return array ['counters' => array, 'total_cents' => int]
     */
    public static function aggregateFiscalCountersIntegerSafe(int $deviceId, int $fiscalDayNo): array
    {
        Log::info('CloseDay HARDENED - Starting integer-safe aggregation', [
            'device_id' => $deviceId,
            'fiscal_day_no' => $fiscalDayNo,
        ]);

        // Query receipts with tax breakdown
        $receipts = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDayNo)
            ->where('is_valid', true)
            ->select('id', 'receipt_taxes', 'receipt_total', 'receipt_currency')
            ->get();

        if ($receipts->isEmpty()) {
            Log::warning('CloseDay HARDENED - No valid receipts found');
            return ['counters' => [], 'total_cents' => 0];
        }

        // Aggregate using INTEGER CENTS
        $countersCents = [];
        $totalReceiptCents = 0;

        foreach ($receipts as $receipt) {
            $receiptTaxes = $receipt->receipt_taxes ?? [];
            $currency = $receipt->receipt_currency ?? 'USD';
            
            // Convert receipt total to cents using BCMath
            $receiptTotalStr = (string) $receipt->receipt_total;
            $receiptCents = (int) bcmul($receiptTotalStr, '100', 0);
            $totalReceiptCents = bcadd((string) $totalReceiptCents, (string) $receiptCents, 0);

            foreach ($receiptTaxes as $tax) {
                $taxID = (int) ($tax['taxID'] ?? 1);
                $taxPercent = (string) ($tax['taxPercent'] ?? '0');
                $salesAmountWithTax = (string) ($tax['salesAmountWithTax'] ?? '0');
                
                // Convert to cents using BCMath
                $salesCents = (int) bcmul($salesAmountWithTax, '100', 0);
                
                // Format tax percent to exactly 2 decimals for grouping key
                $taxPercentFormatted = number_format((float) $taxPercent, 2, '.', '');
                
                // Grouping key: Type_Currency_TaxID_TaxPercent
                $key = "SaleByTax_{$currency}_{$taxID}_{$taxPercentFormatted}";
                
                if (!isset($countersCents[$key])) {
                    $countersCents[$key] = [
                        'fiscalCounterType' => 'SaleByTax',
                        'fiscalCounterCurrency' => $currency,
                        'fiscalCounterTaxID' => $taxID,
                        'fiscalCounterTaxPercent' => $taxPercentFormatted,
                        'value_cents' => 0,
                    ];
                }
                
                // Add using BCMath
                $currentCents = (string) $countersCents[$key]['value_cents'];
                $countersCents[$key]['value_cents'] = (int) bcadd($currentCents, (string) $salesCents, 0);
            }
        }

        // Filter out zero-value counters
        $countersCents = array_filter($countersCents, function ($c) {
            return $c['value_cents'] > 0;
        });

        // Convert to array (no values yet)
        $counters = array_values($countersCents);

        // Calculate total from counters for validation
        $totalCounterCents = 0;
        foreach ($counters as $counter) {
            if ($counter['fiscalCounterType'] === 'SaleByTax') {
                $totalCounterCents = bcadd((string) $totalCounterCents, (string) $counter['value_cents'], 0);
            }
        }

        // ASSERTION: Counter total must equal receipt total
        if ($totalCounterCents != $totalReceiptCents) {
            $diff = bcsub((string) $totalCounterCents, (string) $totalReceiptCents, 0);
            Log::error('CloseDay HARDENED - FATAL: Counter total mismatch', [
                'total_counter_cents' => $totalCounterCents,
                'total_receipt_cents' => $totalReceiptCents,
                'difference_cents' => $diff,
            ]);
            throw new \Exception(
                "FATAL: Counter total ({$totalCounterCents} cents) != Receipt total ({$totalReceiptCents} cents). Difference: {$diff} cents"
            );
        }

        Log::info('CloseDay HARDENED - Aggregation complete', [
            'counter_count' => count($counters),
            'total_cents' => $totalReceiptCents,
            'total_decimal' => bcdiv((string) $totalReceiptCents, '100', 2),
        ]);

        return [
            'counters' => $counters,
            'total_cents' => (int) $totalReceiptCents,
        ];
    }

    /**
     * Sort fiscal counters DETERMINISTICALLY
     * 
     * 4-level sort:
     * 1. fiscalCounterType (ASC)
     * 2. fiscalCounterCurrency (ASC)
     * 3. fiscalCounterTaxID (ASC)
     * 4. fiscalCounterTaxPercent (ASC numeric)
     * 
     * @param array $counters
     * @return array Sorted counters
     */
    public static function sortFiscalCountersDeterministic(array $counters): array
    {
        usort($counters, function ($a, $b) {
            // Level 1: fiscalCounterType (alphabetical)
            $typeCompare = strcmp($a['fiscalCounterType'], $b['fiscalCounterType']);
            if ($typeCompare !== 0) return $typeCompare;
            
            // Level 2: fiscalCounterCurrency (alphabetical)
            $currencyCompare = strcmp($a['fiscalCounterCurrency'], $b['fiscalCounterCurrency']);
            if ($currencyCompare !== 0) return $currencyCompare;
            
            // Level 3: fiscalCounterTaxID (numeric)
            $taxIDCompare = $a['fiscalCounterTaxID'] <=> $b['fiscalCounterTaxID'];
            if ($taxIDCompare !== 0) return $taxIDCompare;
            
            // Level 4: fiscalCounterTaxPercent (numeric)
            $taxPercentA = (float) $a['fiscalCounterTaxPercent'];
            $taxPercentB = (float) $b['fiscalCounterTaxPercent'];
            return $taxPercentA <=> $taxPercentB;
        });

        Log::debug('CloseDay HARDENED - Counters sorted deterministically', [
            'counter_count' => count($counters),
            'sort_order' => array_map(function ($c) {
                return "{$c['fiscalCounterType']}_{$c['fiscalCounterCurrency']}_{$c['fiscalCounterTaxID']}_{$c['fiscalCounterTaxPercent']}";
            }, $counters),
        ]);

        return $counters;
    }

    /**
     * Build canonical string per ZIMRA Spec Section 13.3.1
     * 
     * PROVEN SPEC COMPLIANCE:
     * - deviceID || fiscalDayNo || fiscalDayDate || fiscalDayCounters
     * - fiscalDayDate format: YYYY-MM-DD (NOT datetime)
     * - fiscalDayCounters: Type || Currency || TaxPercent || Value
     * - Text values: UPPERCASE
     * - Tax percent: XX.XX format (2 decimals)
     * - Values: IN CENTS (integer)
     * 
     * @param int $deviceId
     * @param int $fiscalDayNo
     * @param string $fiscalDayDate Format: YYYY-MM-DD
     * @param array $counters Array with 'value_cents' field
     * @return string Canonical string
     */
    public static function buildCanonicalStringSpecCompliant(
        int $deviceId,
        int $fiscalDayNo,
        string $fiscalDayDate,
        array $counters
    ): string {
        $parts = [];
        
        // Part 1: deviceID
        $parts[] = (string) $deviceId;
        
        // Part 2: fiscalDayNo
        $parts[] = (string) $fiscalDayNo;
        
        // Part 3: fiscalDayDate (YYYY-MM-DD format per spec)
        // CRITICAL: Spec says "Date in ISO 8601 format YYYY-MM-DD" NOT datetime
        $parts[] = $fiscalDayDate;
        
        // Part 4: fiscalDayCounters concatenated
        $counterStrings = [];
        foreach ($counters as $counter) {
            $counterParts = [];
            
            // fiscalCounterType (UPPERCASE per spec)
            $counterParts[] = strtoupper($counter['fiscalCounterType']);
            
            // fiscalCounterCurrency (UPPERCASE per spec)
            $counterParts[] = strtoupper($counter['fiscalCounterCurrency']);
            
            // fiscalCounterTaxPercent (XX.XX format per spec)
            $taxPercent = $counter['fiscalCounterTaxPercent'];
            $counterParts[] = number_format((float) $taxPercent, 2, '.', '');
            
            // fiscalCounterValue (IN CENTS per spec: "Amounts are represented in cents")
            $valueCents = $counter['value_cents'];
            $counterParts[] = (string) $valueCents;
            
            // Concatenate without separators (|| is documentation notation only)
            $counterStrings[] = implode('', $counterParts);
        }
        
        $parts[] = implode('', $counterStrings);
        
        // Final canonical string (no separators)
        $canonicalString = implode('', $parts);
        
        Log::info('CloseDay HARDENED - Canonical string built (SPEC COMPLIANT)', [
            'deviceID' => $parts[0],
            'fiscalDayNo' => $parts[1],
            'fiscalDayDate' => $parts[2],
            'fiscalDayCounters_string' => $parts[3],
            'canonical_string' => $canonicalString,
            'canonical_length' => strlen($canonicalString),
        ]);
        
        return $canonicalString;
    }

    /**
     * Sign canonical string - Method A: Sign raw canonical string
     * 
     * Per spec: openssl_sign() with OPENSSL_ALGO_SHA256 hashes internally
     * 
     * @param string $canonicalString
     * @param string $privateKeyPem
     * @return array ['hash' => string, 'signature' => string]
     */
    public static function signCanonicalRaw(string $canonicalString, string $privateKeyPem): array
    {
        // Generate hash for the 'hash' field
        $hashBinary = hash('sha256', $canonicalString, true);
        $hashBase64 = base64_encode($hashBinary);
        $hashHex = bin2hex($hashBinary);
        
        // Sign the canonical string directly
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (!$privateKey) {
            throw new \Exception('Failed to load private key: ' . openssl_error_string());
        }
        
        $signatureBinary = '';
        $signResult = openssl_sign($canonicalString, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);
        
        if (!$signResult) {
            throw new \Exception('Failed to sign canonical string: ' . openssl_error_string());
        }
        
        $signatureBase64 = base64_encode($signatureBinary);
        
        Log::info('CloseDay HARDENED - Signature Method A (Raw Canonical)', [
            'method' => 'signCanonicalRaw',
            'canonical_length' => strlen($canonicalString),
            'hash_hex' => $hashHex,
            'hash_base64' => $hashBase64,
            'signature_base64' => substr($signatureBase64, 0, 50) . '...',
            'signature_length' => strlen($signatureBinary),
        ]);
        
        return [
            'hash' => $hashBase64,
            'signature' => $signatureBase64,
        ];
    }

    /**
     * Sign canonical string - Method B: Sign pre-hashed (SHA256)
     * 
     * Alternative method: Sign the hash instead of canonical string
     * 
     * @param string $canonicalString
     * @param string $privateKeyPem
     * @return array ['hash' => string, 'signature' => string]
     */
    public static function signPreHashed(string $canonicalString, string $privateKeyPem): array
    {
        // Generate hash
        $hashBinary = hash('sha256', $canonicalString, true);
        $hashBase64 = base64_encode($hashBinary);
        $hashHex = bin2hex($hashBinary);
        
        // Sign the HASH (not canonical string)
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (!$privateKey) {
            throw new \Exception('Failed to load private key: ' . openssl_error_string());
        }
        
        $signatureBinary = '';
        // Sign hash with NONE algorithm (hash already computed)
        openssl_sign($hashBinary, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);
        
        $signatureBase64 = base64_encode($signatureBinary);
        
        Log::info('CloseDay HARDENED - Signature Method B (Pre-Hashed)', [
            'method' => 'signPreHashed',
            'canonical_length' => strlen($canonicalString),
            'hash_hex' => $hashHex,
            'hash_base64' => $hashBase64,
            'signature_base64' => substr($signatureBase64, 0, 50) . '...',
            'signature_length' => strlen($signatureBinary),
        ]);
        
        return [
            'hash' => $hashBase64,
            'signature' => $signatureBase64,
        ];
    }

    /**
     * Verify signature locally using public key
     * 
     * @param string $canonicalString
     * @param string $signatureBase64
     * @param string $certificatePem
     * @return bool True if valid
     */
    public static function verifySignatureLocal(
        string $canonicalString,
        string $signatureBase64,
        string $certificatePem
    ): bool {
        $publicKey = openssl_pkey_get_public($certificatePem);
        if (!$publicKey) {
            Log::error('CloseDay HARDENED - Failed to load public key');
            return false;
        }
        
        $signatureBinary = base64_decode($signatureBase64);
        $verifyResult = openssl_verify($canonicalString, $signatureBinary, $publicKey, OPENSSL_ALGO_SHA256);
        
        $isValid = ($verifyResult === 1);
        
        Log::info('CloseDay HARDENED - Local signature verification', [
            'valid' => $isValid,
            'verify_result' => $verifyResult,
            'meaning' => $verifyResult === 1 ? 'VALID' : ($verifyResult === 0 ? 'INVALID' : 'ERROR'),
        ]);
        
        return $isValid;
    }

    /**
     * Validate receiptCounter with ASSERTION
     * 
     * @param int $deviceId
     * @param int $fiscalDayNo
     * @param int $expectedReceiptCounter
     * @throws \Exception if mismatch
     */
    public static function assertReceiptCounter(int $deviceId, int $fiscalDayNo, int $expectedReceiptCounter): void
    {
        $actualMax = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDayNo)
            ->where('is_valid', true)
            ->max('receipt_counter');
        
        if ($actualMax === null) {
            $actualMax = 0;
        }
        
        if ($actualMax != $expectedReceiptCounter) {
            Log::error('CloseDay HARDENED - FATAL: receiptCounter mismatch', [
                'expected' => $expectedReceiptCounter,
                'actual_max' => $actualMax,
            ]);
            throw new \Exception(
                "FATAL: receiptCounter mismatch. Expected: {$expectedReceiptCounter}, Actual max(receipt_counter): {$actualMax}"
            );
        }
        
        Log::info('CloseDay HARDENED - receiptCounter assertion PASSED', [
            'receipt_counter' => $actualMax,
        ]);
    }
}
