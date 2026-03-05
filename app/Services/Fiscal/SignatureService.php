<?php

namespace App\Services\Fiscal;

use App\Models\Receipt;
use App\Models\ZimraConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SignatureService
{
    protected int $deviceId;
    protected ?string $privateKeyPath = null;
    protected $privateKey = null;

    public function __construct(?int $deviceId = null)
    {
        if ($deviceId) {
            $this->setDeviceId($deviceId);
        }
    }

    public function setDeviceId(int $deviceId): self
    {
        $this->deviceId = $deviceId;
        $this->loadPrivateKey();
        return $this;
    }

    protected function loadPrivateKey(): void
    {
        $config = ZimraConfig::where('device_id', $this->deviceId)->first();
        
        if (!$config || !$config->device_private_key) {
            throw new \Exception("No private key found for device {$this->deviceId}");
        }

        // Write key to temp file for OpenSSL
        $keyPath = storage_path("app/zimra/device_{$this->deviceId}_private.key");
        $keyDir = dirname($keyPath);
        
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0755, true);
        }

        file_put_contents($keyPath, $config->device_private_key);
        $this->privateKeyPath = $keyPath;
        
        $this->privateKey = openssl_pkey_get_private($config->device_private_key);
        
        if (!$this->privateKey) {
            throw new \Exception("Failed to load private key for device {$this->deviceId}");
        }
    }

    /**
     * Build the canonical string for signing
     * Per FDMS API v7.2 Section 13.2.1
     */
    public function buildCanonicalString(array $receiptData, string $previousHash): string
    {
        $deviceId = (string) $this->deviceId;
        $receiptType = strtoupper($receiptData['receiptType']);
        $receiptCurrency = $receiptData['receiptCurrency'];
        $receiptGlobalNo = (string) $receiptData['receiptGlobalNo'];
        $receiptDate = $receiptData['receiptDate'];
        
        // Receipt total in cents (multiply by 100, no decimal)
        $receiptTotalCents = (string) round((float) $receiptData['receiptTotal'] * 100);
        
        // Receipt taxes: taxPercent + salesAmountWithTax for each tax group
        $taxParts = [];
        foreach ($receiptData['receiptTaxes'] as $tax) {
            $taxPercent = number_format((float) $tax['taxPercent'], 2, '.', '');
            $salesWithTax = (string) round((float) $tax['salesAmountWithTax'] * 100);
            $taxParts[] = $taxPercent . $salesWithTax;
        }
        $taxString = implode('', $taxParts);

        // Canonical string: concatenate all parts
        $canonicalString = $deviceId
            . $receiptType
            . $receiptCurrency
            . $receiptGlobalNo
            . $receiptDate
            . $receiptTotalCents
            . $taxString
            . $previousHash;

        Log::info('CANONICAL_STRING_PARTS', [
            'deviceID' => $deviceId,
            'receiptType' => $receiptType,
            'receiptCurrency' => $receiptCurrency,
            'receiptGlobalNo' => $receiptGlobalNo,
            'receiptDate' => $receiptDate,
            'receiptTotal_cents' => $receiptTotalCents,
            'receiptTaxes' => $taxString,
            'previousReceiptHash' => $previousHash,
            'full_string' => $canonicalString,
        ]);

        Log::info('CANONICAL_STRING_FOR_SIGNING', [
            'canonical_string' => $canonicalString,
            'length' => strlen($canonicalString),
            'format' => 'deviceID||receiptType||receiptCurrency||receiptGlobalNo||receiptDate||receiptTotal(cents)||receiptTaxes',
        ]);

        return $canonicalString;
    }

    /**
     * Sign the canonical string
     * Returns hash and signature
     */
    public function sign(string $canonicalString): array
    {
        // SHA256 hash
        $hash = hash('sha256', $canonicalString, true);
        $hashBase64 = base64_encode($hash);

        Log::debug('ZIMRA signCanonicalString - Hash', [
            'canonical_string' => $canonicalString,
            'hash_hex' => bin2hex($hash),
            'hash_base64' => $hashBase64,
        ]);

        // ECDSA signature
        $signature = '';
        $result = openssl_sign($canonicalString, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        
        if (!$result) {
            throw new \Exception('Failed to sign canonical string: ' . openssl_error_string());
        }

        $signatureBase64 = base64_encode($signature);

        Log::debug('ZIMRA signCanonicalString - Signature', [
            'signature_base64' => $signatureBase64,
            'signature_length' => strlen($signature),
        ]);

        // Verify locally
        $verifyResult = openssl_verify($canonicalString, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        
        Log::info('ZIMRA signCanonicalString - Local Verification', [
            'local_signature_valid' => $verifyResult,
            'verify_meaning' => $verifyResult === 1 ? 'VALID' : ($verifyResult === 0 ? 'INVALID' : 'ERROR'),
        ]);

        return [
            'hash' => $hashBase64,
            'signature' => $signatureBase64,
        ];
    }

    /**
     * Get the previous receipt hash for chain continuity
     * 
     * CRITICAL: Must query by receipt_global_no (lifetime counter), not receipt_counter
     * The fiscal chain is based on global receipt sequence, not per-day sequence.
     * 
     * @param int $nextGlobalNo The next receipt_global_no being created
     */
    public function getPreviousReceiptHash(int $nextGlobalNo): string
    {
        if ($nextGlobalNo <= 1) {
            // First receipt ever - use empty hash
            return '';
        }

        // Query by receipt_global_no - this is the CORRECT chain order
        $previousReceipt = Receipt::where('device_id', $this->deviceId)
            ->where('receipt_global_no', $nextGlobalNo - 1)
            ->where('status', 'finalized')
            ->first();

        if (!$previousReceipt) {
            Log::warning('Previous receipt not found for chain - checking device_state', [
                'device_id' => $this->deviceId,
                'next_global_no' => $nextGlobalNo,
                'expected_previous_global_no' => $nextGlobalNo - 1,
            ]);
            
            // Fallback: try to get from device_state (may have been stored there)
            $deviceState = \App\Models\DeviceState::where('device_id', $this->deviceId)->first();
            if ($deviceState && $deviceState->last_receipt_hash && $deviceState->last_receipt_global_no === ($nextGlobalNo - 1)) {
                return $deviceState->last_receipt_hash;
            }
            
            return '';
        }

        $hash = $previousReceipt->receipt_hash ?? '';

        Log::info('PREVIOUS_RECEIPT_HASH_RETRIEVED', [
            'device_id' => $this->deviceId,
            'next_global_no' => $nextGlobalNo,
            'previous_global_no' => $nextGlobalNo - 1,
            'previous_receipt_id' => $previousReceipt->id,
            'previous_hash' => $hash,
        ]);

        return $hash;
    }

    /**
     * Sign a receipt and return the signature block
     * 
     * @param array $receiptData Must contain receiptGlobalNo
     * @param int $receiptCounter Kept for backward compatibility but not used for hash chain
     */
    public function signReceipt(array $receiptData, int $receiptCounter): array
    {
        // Use receiptGlobalNo for hash chain (correct), not receiptCounter
        $globalNo = $receiptData['receiptGlobalNo'] ?? $receiptCounter;
        $previousHash = $this->getPreviousReceiptHash($globalNo);
        $canonicalString = $this->buildCanonicalString($receiptData, $previousHash);
        $signature = $this->sign($canonicalString);

        return [
            'receiptDeviceSignature' => $signature,
            'canonicalString' => $canonicalString,
            'previousHash' => $previousHash,
        ];
    }

    /**
     * Verify a receipt signature
     */
    public function verifySignature(string $canonicalString, string $signatureBase64): bool
    {
        $signature = base64_decode($signatureBase64);
        $result = openssl_verify($canonicalString, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }
}
