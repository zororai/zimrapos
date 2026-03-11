<?php

namespace App\Services\Fiscal;

use App\Enums\ReceiptType;
use App\Models\Receipt;
use App\Models\ZimraConfig;
use App\Services\ZimraDeviceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiptService
{
    protected CounterService $counterService;
    protected SignatureService $signatureService;
    protected ZimraDeviceService $zimraService;
    protected ?int $deviceId = null;

    public function __construct(
        CounterService $counterService,
        SignatureService $signatureService,
        ZimraDeviceService $zimraService
    ) {
        $this->counterService = $counterService;
        $this->signatureService = $signatureService;
        $this->zimraService = $zimraService;
    }

    public function setDeviceId(int $deviceId): self
    {
        $this->deviceId = $deviceId;
        $this->counterService->setDeviceId($deviceId);
        $this->signatureService->setDeviceId($deviceId);
        return $this;
    }

    protected function getDeviceId(): int
    {
        if ($this->deviceId) {
            return $this->deviceId;
        }

        $config = ZimraConfig::getActive();
        if (!$config || !$config->device_id) {
            throw new \Exception('No active ZIMRA device configured');
        }

        $this->setDeviceId($config->device_id);
        return $this->deviceId;
    }

    /**
     * Unified receipt creation - all receipt types flow through here
     * 
     * CORRECT LIFECYCLE (crash-safe):
     * 1. Create receipt record (status = pending)
     * 2. Reserve counters (with row locking)
     * 3. Sign canonical string
     * 4. SubmitReceipt → FDMS
     * 5. Update receipt with fdms_receipt_id (status = submitted)
     * 6. Commit counters
     * 7. Mark receipt = finalized
     * 
     * @param ReceiptType $type The receipt type
     * @param array $data Receipt data (lines, taxes, payments, etc.)
     * @param Receipt|null $originalReceipt Required for CreditNote/DebitNote
     */
    public function createReceipt(ReceiptType $type, array $data, ?Receipt $originalReceipt = null): array
    {
        $deviceId = $this->getDeviceId();

        // Validate original receipt for credit/debit notes
        if ($type->requiresOriginalReceipt()) {
            if (!$originalReceipt) {
                throw new \Exception("{$type->value} requires an original receipt");
            }
            $this->validateOriginalReceipt($originalReceipt);
        }

        return DB::transaction(function () use ($type, $data, $originalReceipt, $deviceId) {
            // STEP 1: Get fiscal day status from FDMS
            $fdmsStatus = $this->zimraService->getFiscalDayStatus($deviceId);
            $fiscalDayNo = $fdmsStatus['fiscalDayNo'] ?? $fdmsStatus['lastFiscalDayNo'] ?? 1;

            // STEP 2: Reserve counters (with row locking in CounterService)
            $counters = $this->counterService->reserveCounters($fiscalDayNo);

            // STEP 3: Create PENDING receipt record (crash-safe)
            $receipt = $this->createPendingReceipt($type, $data, $counters, $originalReceipt, $deviceId);

            Log::info('ReceiptService: Pending receipt created', [
                'receipt_id' => $receipt->id,
                'device_id' => $deviceId,
                'receipt_type' => $type->value,
                'fiscal_day_no' => $fiscalDayNo,
                'receipt_counter' => $counters['receiptCounter'],
                'receipt_global_no' => $counters['receiptGlobalNo'],
            ]);

            // STEP 4: Build canonical string and sign
            $receiptData = $this->buildReceiptData($type, $data, $counters, $originalReceipt, $deviceId);
            $signatureResult = $this->signatureService->signReceipt($receiptData, $counters['receiptCounter']);
            $receiptData['receiptDeviceSignature'] = $signatureResult['receiptDeviceSignature'];

            // Update receipt with signature
            $receipt->update([
                'receipt_hash' => $signatureResult['receiptDeviceSignature']['hash'],
                'receipt_signature' => $signatureResult['receiptDeviceSignature'],
            ]);

            // STEP 5: Submit to FDMS
            $fdmsResult = $this->zimraService->submitReceiptToFdms($receiptData, $deviceId);

            if (isset($fdmsResult['error'])) {
                // Mark receipt as failed
                $receipt->update(['status' => 'failed']);
                throw new \Exception($fdmsResult['message'] ?? 'FDMS submission failed');
            }

            // STEP 6: Update receipt with FDMS response (status = submitted)
            // Extract server signature hash for QR code verification
            $serverSignatureHash = $fdmsResult['receiptServerSignature']['hash'] ?? null;
            $receiptDate = $receiptData['receiptDate'] ?? now()->format('m/d/Y H:i:s');
            
            // Generate verification code from server signature (hexadecimal format)
            $verificationCode = null;
            if ($serverSignatureHash) {
                // Decode base64 hash and convert to hexadecimal
                $binary = base64_decode($serverSignatureHash);
                $hex = strtoupper(bin2hex($binary));
                
                // Take first 16 hex characters
                $code = substr($hex, 0, 16);
                
                // Format as XXXX-XXXX-XXXX-XXXX
                $verificationCode = sprintf(
                    '%s-%s-%s-%s',
                    substr($code, 0, 4),
                    substr($code, 4, 4),
                    substr($code, 8, 4),
                    substr($code, 12, 4)
                );
            }
            
            $receipt->update([
                'status' => 'submitted',
                'fdms_receipt_id' => $fdmsResult['receiptID'] ?? null,
                'fdms_operation_id' => $fdmsResult['operationID'] ?? null,
                'fdms_server_date' => isset($fdmsResult['serverDate']) ? \Carbon\Carbon::parse($fdmsResult['serverDate']) : null,
                'fdms_certificate_thumbprint' => $fdmsResult['receiptServerSignature']['certificateThumbprint'] ?? null,
                'receipt_qr_code' => $this->buildQrCodeUrl($deviceId, $fdmsResult['receiptID'] ?? 0, $counters['fiscalDayNo'], $counters['receiptGlobalNo'], $receiptDate, $verificationCode),
                'verification_code' => $verificationCode,
                'zimra_response' => $fdmsResult,
                'validation_code' => $this->getValidationCode($fdmsResult),
                'validation_errors' => $fdmsResult['validationErrors'] ?? null,
                'is_valid' => $this->isReceiptValid($fdmsResult),
                'has_red_errors' => $this->hasRedErrors($fdmsResult),
                'has_gray_errors' => $this->hasGrayErrors($fdmsResult),
            ]);

            // STEP 7: Commit counters (update device_state)
            $this->counterService->commitCounters(
                $counters['fiscalDayNo'],
                $counters['receiptCounter'],
                $counters['receiptGlobalNo'],
                $signatureResult['receiptDeviceSignature']['hash'],
                $receipt->id
            );

            // STEP 8: Mark receipt as finalized
            $receipt->update(['status' => 'finalized']);

            Log::info('ReceiptService: Receipt finalized', [
                'receipt_id' => $receipt->id,
                'fdms_receipt_id' => $fdmsResult['receiptID'] ?? null,
                'receipt_type' => $type->value,
                'receipt_global_no' => $counters['receiptGlobalNo'],
                'validation_code' => $this->getValidationCode($fdmsResult),
            ]);

            return [
                'success' => true,
                'receipt' => $receipt->fresh(),
                'fdms_receipt_id' => $fdmsResult['receiptID'] ?? null,
                'validation_code' => $this->getValidationCode($fdmsResult),
            ];
        });
    }

    /**
     * Create pending receipt record before FDMS submission (crash-safe)
     */
    protected function createPendingReceipt(
        ReceiptType $type,
        array $data,
        array $counters,
        ?Receipt $originalReceipt,
        int $deviceId
    ): Receipt {
        $invoiceNo = $data['invoiceNo'] ?? $type->invoicePrefix() . '-' . $counters['receiptGlobalNo'];

        return Receipt::create([
            'status' => 'pending',
            'device_id' => $deviceId,
            'invoice_no' => $invoiceNo,
            'receipt_type' => $type->value,
            'original_receipt_id' => $originalReceipt?->id,
            'external_reference' => $data['external_reference'] ?? null,
            'receipt_currency' => $data['receiptCurrency'] ?? 'USD',
            'receipt_counter' => $counters['receiptCounter'],
            'receipt_global_no' => $counters['receiptGlobalNo'],
            'fiscal_day_no' => $counters['fiscalDayNo'],
            'receipt_total' => $data['receiptTotal'],
            'receipt_notes' => $data['receiptNotes'] ?? null,
            'receipt_lines' => $data['receiptLines'] ?? [],
            'receipt_taxes' => $data['receiptTaxes'] ?? [],
            'receipt_payments' => $data['receiptPayments'] ?? [],
            'buyer_data' => $data['buyerData'] ?? null,
            'receipt_date' => $data['receiptDate'] ?? now()->format('Y-m-d\TH:i:s'),
        ]);
    }

    /**
     * Build receipt data for FDMS submission
     */
    protected function buildReceiptData(
        ReceiptType $type,
        array $data,
        array $counters,
        ?Receipt $originalReceipt,
        int $deviceId
    ): array {
        $receiptData = [
            'receiptType' => $type->value,
            'receiptCurrency' => $data['receiptCurrency'] ?? 'USD',
            'receiptCounter' => $counters['receiptCounter'],
            'receiptGlobalNo' => $counters['receiptGlobalNo'],
            'invoiceNo' => $data['invoiceNo'] ?? $type->invoicePrefix() . '-' . $counters['receiptGlobalNo'],
            'receiptDate' => $data['receiptDate'] ?? now()->format('Y-m-d\TH:i:s'),
            'receiptLinesTaxInclusive' => $data['receiptLinesTaxInclusive'] ?? false,
            'receiptLines' => $data['receiptLines'],
            'receiptTaxes' => $data['receiptTaxes'],
            'receiptPayments' => $data['receiptPayments'],
            'receiptTotal' => $data['receiptTotal'],
            'receiptPrintForm' => $data['receiptPrintForm'] ?? 'Receipt48',
        ];

        // Add optional fields
        if (!empty($data['buyerData'])) {
            $receiptData['buyerData'] = $data['buyerData'];
        }
        if (!empty($data['receiptNotes'])) {
            $receiptData['receiptNotes'] = $data['receiptNotes'];
        }

        // Add creditDebitNote for CreditNote/DebitNote
        // FDMS requires: creditDebitNoteReceiptGlobalNo + creditDebitNoteDate
        if ($type->requiresOriginalReceipt() && $originalReceipt) {
            $receiptData['creditDebitNote'] = [
                'creditDebitNoteReceiptGlobalNo' => $originalReceipt->receipt_global_no,
                'creditDebitNoteDate' => $originalReceipt->receipt_date instanceof \Carbon\Carbon 
                    ? $originalReceipt->receipt_date->format('Y-m-d\TH:i:s')
                    : $originalReceipt->receipt_date,
            ];
        }

        return $receiptData;
    }

    /**
     * Validate original receipt for credit/debit notes
     */
    protected function validateOriginalReceipt(Receipt $originalReceipt): void
    {
        if (!$originalReceipt->fdms_receipt_id) {
            throw new \Exception("Original receipt #{$originalReceipt->id} is not fiscalized");
        }

        if ($originalReceipt->is_voided) {
            throw new \Exception("Original receipt #{$originalReceipt->id} is voided");
        }

        if ($originalReceipt->status !== 'finalized') {
            throw new \Exception("Original receipt #{$originalReceipt->id} is not finalized");
        }

        $deviceId = $this->getDeviceId();
        if ($originalReceipt->device_id !== $deviceId) {
            throw new \Exception("Original receipt device_id does not match current device");
        }
    }

    /**
     * Store receipt in the fiscal ledger
     */
    protected function storeReceipt(array $data, array $fdmsResult, array $signatureResult): Receipt
    {
        $deviceId = $this->getDeviceId();
        
        // Build QR code URL
        $qrCodeUrl = $this->buildQrCodeUrl(
            $deviceId,
            $fdmsResult['receiptID'] ?? 0,
            $data['fiscalDayNo'],
            $data['receiptGlobalNo']
        );

        $receipt = Receipt::create([
            'device_id' => $deviceId,
            'invoice_no' => $data['invoiceNo'] ?? $this->generateInvoiceNo($data['receiptType'], $data['receiptGlobalNo']),
            'receipt_type' => $data['receiptType'],
            'original_receipt_id' => $data['original_receipt_id'] ?? null,
            'external_reference' => $data['external_reference'] ?? null,
            'receipt_currency' => $data['receiptCurrency'],
            'receipt_counter' => $data['receiptCounter'],
            'receipt_global_no' => $data['receiptGlobalNo'],
            'fiscal_day_no' => $data['fiscalDayNo'],
            'receipt_total' => $data['receiptTotal'],
            'receipt_notes' => $data['receiptNotes'] ?? null,
            'receipt_lines' => $data['receiptLines'] ?? [],
            'receipt_taxes' => $data['receiptTaxes'] ?? [],
            'receipt_payments' => $data['receiptPayments'] ?? [],
            'buyer_data' => $data['buyerData'] ?? null,
            'receipt_hash' => $signatureResult['receiptDeviceSignature']['hash'] ?? null,
            'receipt_signature' => $signatureResult['receiptDeviceSignature'] ?? null,
            'receipt_qr_code' => $qrCodeUrl,
            'verification_code' => $this->generateVerificationCode($qrCodeUrl),
            'receipt_date' => $data['receiptDate'],
            'fdms_receipt_id' => $fdmsResult['receiptID'] ?? null,
            'zimra_response' => $fdmsResult,
            'validation_code' => $this->getValidationCode($fdmsResult),
            'validation_errors' => $fdmsResult['validationErrors'] ?? null,
            'is_valid' => $this->isReceiptValid($fdmsResult),
            'has_red_errors' => $this->hasRedErrors($fdmsResult),
            'has_gray_errors' => $this->hasGrayErrors($fdmsResult),
        ]);

        return $receipt;
    }

    protected function generateInvoiceNo(string $receiptType, int $globalNo): string
    {
        $prefix = match($receiptType) {
            'CreditNote' => 'CN',
            'DebitNote' => 'DN',
            default => 'INV',
        };
        
        return "{$prefix}-{$globalNo}";
    }

    protected function buildQrCodeUrl(int $deviceId, int $receiptId, int $fiscalDayNo, int $globalNo, ?string $receiptDate = null, ?string $verificationCode = null): string
    {
        $config = ZimraConfig::where('device_id', $deviceId)->first();
        $baseUrl = $config->qr_url ?? 'https://fdmstest.zimra.co.zw';
        
        // Format device ID with leading zeros (10 digits)
        $formattedDeviceId = str_pad($deviceId, 10, '0', STR_PAD_LEFT);
        
        // Format receipt counter/global number with leading zeros (10 digits)
        $formattedGlobalNo = str_pad($globalNo, 10, '0', STR_PAD_LEFT);
        
        // Format receipt date (default to current time if not provided)
        $formattedDate = $receiptDate ? urlencode($receiptDate) : urlencode(now()->format('m/d/Y H:i:s'));
        
        // Use verification code if provided, otherwise generate placeholder
        $qrData = $verificationCode ?? $this->generateVerificationCode('');
        
        // Build URL with correct ZIMRA format
        return "{$baseUrl}/Receipt/Result?DeviceId={$formattedDeviceId}&ReceiptDate={$formattedDate}&ReceiptCounterReceiptGlobalNo={$formattedGlobalNo}&ReceiptQrData={$qrData}";
    }

    protected function generateVerificationCode(string $url, ?int $deviceId = null, ?int $receiptId = null, ?int $fiscalDayNo = null, ?int $globalNo = null): string
    {
        // If URL is provided, try to parse parameters from it
        if (!empty($url)) {
            $parsed = parse_url($url);
            $params = [];
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $params);
            }

            $codeString = sprintf(
                '%s%s%s%s',
                $params['DeviceId'] ?? $params['deviceID'] ?? '',
                $params['ReceiptCounterReceiptGlobalNo'] ?? $params['receiptGlobalNo'] ?? '',
                $params['fiscalDayNo'] ?? '',
                $params['ReceiptDate'] ?? ''
            );
        } else {
            // Use provided parameters directly
            $codeString = sprintf(
                '%s%s%s',
                $deviceId ?? '',
                $globalNo ?? '',
                $fiscalDayNo ?? ''
            );
        }

        $hash = strtoupper(substr(md5($codeString), 0, 16));
        
        return sprintf(
            '%s-%s-%s-%s',
            substr($hash, 0, 4),
            substr($hash, 4, 4),
            substr($hash, 8, 4),
            substr($hash, 12, 4)
        );
    }

    protected function getValidationCode(array $fdmsResult): string
    {
        $errors = $fdmsResult['validationErrors'] ?? [];
        
        if (empty($errors)) {
            return 'Green';
        }

        foreach ($errors as $error) {
            if (($error['validationErrorColor'] ?? '') === 'Red') {
                return 'Red';
            }
            if (($error['validationErrorColor'] ?? '') === 'Grey') {
                return 'Grey';
            }
        }

        return 'Yellow';
    }

    protected function isReceiptValid(array $fdmsResult): bool
    {
        return !$this->hasRedErrors($fdmsResult) && !$this->hasGrayErrors($fdmsResult);
    }

    protected function hasRedErrors(array $fdmsResult): bool
    {
        foreach ($fdmsResult['validationErrors'] ?? [] as $error) {
            if (($error['validationErrorColor'] ?? '') === 'Red') {
                return true;
            }
        }
        return false;
    }

    protected function hasGrayErrors(array $fdmsResult): bool
    {
        foreach ($fdmsResult['validationErrors'] ?? [] as $error) {
            if (($error['validationErrorColor'] ?? '') === 'Grey') {
                return true;
            }
        }
        return false;
    }
}
