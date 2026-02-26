<?php

namespace App\Services;

use App\Models\DeviceState;
use App\Models\FiscalDay;
use App\Models\Receipt;
use App\Models\ZimraConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * REFACTORED submitReceipt() Method
 * 
 * CRITICAL ARCHITECTURAL CHANGES:
 * 
 * 1. Single Source of Truth: device_state table stores last known counters
 * 2. Atomic Transaction: All operations wrapped in single DB transaction
 * 3. Counter Consistency: Same counter variables used for signing, FDMS, and DB
 * 4. Reconciliation Detection: Detects FDMS success + DB failure scenarios
 * 5. No Recalculation: Counters NEVER recalculated from receipts table
 * 
 * This prevents:
 * - CountersMismatch errors during CloseDay
 * - Duplicate entry errors on unique constraints
 * - FDMS/DB state divergence
 * - Race conditions in concurrent requests
 */
class ZimraDeviceServiceRefactored
{
    /**
     * Submit Receipt to ZIMRA FDMS with Atomic Fiscal State Handling
     * 
     * CRITICAL FLOW:
     * 1. Check device_state.requires_reconciliation (block if true)
     * 2. Start DB transaction with row lock on device_state
     * 3. Calculate counters ONCE from device_state (not receipts table)
     * 4. Store counters in local variables: $nextReceiptCounter, $nextGlobalNo, $fdmsFiscalDayNo
     * 5. Build canonical string using EXACT counter values
     * 6. Sign canonical string
     * 7. Send to FDMS using EXACT counter values
     * 8. If FDMS accepts:
     *    a. Try to persist receipt to DB using EXACT counter values
     *    b. If DB success: increment device_state counters and commit
     *    c. If DB fails: mark device_state.requires_reconciliation = true, log CRITICAL error
     * 9. If FDMS rejects: rollback transaction, throw exception
     * 
     * @param array $receiptData Receipt payload data
     * @return array Response with success/error status
     * @throws \Exception On validation errors or system failures
     */
    public function submitReceipt(array $receiptData)
    {
        $zimraConfig = ZimraConfig::getActive();

        if (!$zimraConfig) {
            throw new \Exception('No active ZIMRA configuration found.');
        }

        $deviceId = $zimraConfig->device_id;

        if (!$deviceId) {
            throw new \Exception('No device ID found in configuration.');
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 0: Check Reconciliation Status (CRITICAL SAFETY CHECK)
        |--------------------------------------------------------------------------
        | If device requires reconciliation, block ALL receipt submissions
        | This prevents further state divergence until manual intervention
        |--------------------------------------------------------------------------
        */
        $deviceState = DeviceState::where('device_id', $deviceId)->first();
        
        if (!$deviceState) {
            throw new \Exception(
                "CRITICAL: No device_state record found for device {$deviceId}. " .
                "Run migration: php artisan migrate"
            );
        }
        
        if ($deviceState->requiresReconciliation()) {
            throw new \Exception(
                "CRITICAL: Device {$deviceId} requires reconciliation. " .
                "FDMS accepted a receipt but DB persistence failed. " .
                "Error: {$deviceState->reconciliation_error}. " .
                "Manual intervention required. Contact system administrator."
            );
        }

        $baseUrl = $zimraConfig->base_url;

        // Write certificates from database to files for mTLS
        if ($zimraConfig->certificate && $zimraConfig->private_key) {
            Storage::put('zimra/device_certificate.pem', $zimraConfig->certificate);
            Storage::put('zimra/device_private.key', $zimraConfig->private_key);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 1: Get Fiscal Day Status from FDMS
        |--------------------------------------------------------------------------
        */
        $fdmsStatus = $this->getStatus($deviceId);
        
        if (isset($fdmsStatus['error'])) {
            throw new \Exception('Failed to get device status from FDMS: ' . json_encode($fdmsStatus));
        }

        if (!isset($fdmsStatus['fiscalDayStatus'], $fdmsStatus['lastFiscalDayNo'])) {
            throw new \Exception('Invalid FDMS status response - missing fiscalDayStatus or lastFiscalDayNo');
        }

        $fdmsFiscalDayStatus = $fdmsStatus['fiscalDayStatus'];
        $fdmsFiscalDayNo = (int) $fdmsStatus['lastFiscalDayNo'];

        Log::info('ZIMRA SubmitReceipt - FDMS Status', [
            'fiscalDayStatus' => $fdmsFiscalDayStatus,
            'lastFiscalDayNo' => $fdmsFiscalDayNo,
        ]);

        // Verify fiscal day is open on FDMS
        if ($fdmsFiscalDayStatus !== 'FiscalDayOpened') {
            throw new \Exception(
                "RCPT021: FDMS fiscal day not open. Current status: {$fdmsFiscalDayStatus}. " .
                "Please open a fiscal day before submitting receipts."
            );
        }

        // Check local fiscal day exists
        $fiscalDay = FiscalDay::getCurrentOpen($deviceId);
        if (!$fiscalDay) {
            throw new \Exception('No open fiscal day locally. Open a fiscal day before submitting receipts.');
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 2: Get Tax Configuration and Validate
        |--------------------------------------------------------------------------
        */
        $configResponse = $this->getConfig($deviceId);
        $fdmsTaxes = $this->getFdmsTaxConfig($zimraConfig);
        $vatNumber = $configResponse['vatNumber'] ?? null;
        
        Log::info('ZIMRA SubmitReceipt - Tax Config from FDMS', [
            'taxes' => $fdmsTaxes,
            'vatNumber' => $vatNumber ?? 'NOT_REGISTERED',
        ]);

        // Validate VAT registration
        if (!$vatNumber || $vatNumber === 'NOT_REGISTERED') {
            foreach ($receiptData['receiptLines'] ?? [] as $line) {
                $lineTaxPercent = (float) ($line['taxPercent'] ?? 0);
                if ($lineTaxPercent > 0) {
                    throw new \Exception(
                        "RCPT021: Device not VAT registered. Only 0% tax allowed. Line has {$lineTaxPercent}% tax."
                    );
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 3: Validate Invoice Number Uniqueness
        |--------------------------------------------------------------------------
        */
        $invoiceNo = $receiptData['invoiceNo'] ?? null;
        if ($invoiceNo) {
            $existingReceipt = Receipt::where('device_id', $deviceId)
                ->where('invoice_no', $invoiceNo)
                ->first();
            
            if ($existingReceipt) {
                throw new \Exception(
                    "Invoice number '{$invoiceNo}' already exists for this device. Use a unique invoice number."
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 4: ATOMIC TRANSACTION - Calculate Counters, Submit to FDMS, Persist to DB
        |--------------------------------------------------------------------------
        | CRITICAL: This entire block runs in a single transaction
        | Counters are calculated ONCE and used consistently throughout
        |--------------------------------------------------------------------------
        */
        
        // These variables will hold the EXACT counter values used for signing and DB
        $nextReceiptCounter = null;
        $nextGlobalNo = null;
        $fdmsReceiptId = null;
        $fdmsResponse = null;
        $receiptValidationCode = null;
        $hasRedErrors = false;
        $hasGrayErrors = false;
        $parsedErrors = [];
        $receipt = null;
        
        try {
            DB::transaction(function () use (
                $deviceId,
                $fdmsFiscalDayNo,
                &$nextReceiptCounter,
                &$nextGlobalNo,
                &$receiptData,
                $deviceState,
                $zimraConfig,
                $baseUrl,
                $fdmsTaxes,
                $vatNumber,
                &$fdmsReceiptId,
                &$fdmsResponse,
                &$receiptValidationCode,
                &$hasRedErrors,
                &$hasGrayErrors,
                &$parsedErrors,
                &$receipt,
                $fiscalDay
            ) {
                /*
                |----------------------------------------------------------------------
                | STEP 4A: Lock device_state row and calculate counters
                |----------------------------------------------------------------------
                | CRITICAL: lockForUpdate() prevents concurrent requests from getting
                | the same counter values (prevents race conditions)
                |----------------------------------------------------------------------
                */
                $lockedDeviceState = DeviceState::where('device_id', $deviceId)
                    ->lockForUpdate()
                    ->first();
                
                if (!$lockedDeviceState) {
                    throw new \Exception("CRITICAL: device_state row disappeared during transaction");
                }
                
                // Calculate next counters from device_state (NOT from receipts table)
                $nextReceiptCounter = $lockedDeviceState->getNextReceiptCounter($fdmsFiscalDayNo);
                $nextGlobalNo = $lockedDeviceState->getNextGlobalNo();
                
                Log::info('ZIMRA SubmitReceipt - Counters from device_state (LOCKED)', [
                    'device_state_last_fiscal_day' => $lockedDeviceState->last_fiscal_day_no,
                    'device_state_last_counter' => $lockedDeviceState->last_receipt_counter,
                    'device_state_last_global' => $lockedDeviceState->last_receipt_global_no,
                    'fdms_fiscal_day_no' => $fdmsFiscalDayNo,
                    'next_receipt_counter' => $nextReceiptCounter,
                    'next_global_no' => $nextGlobalNo,
                ]);
                
                // CRITICAL: Inject counters into receiptData
                // These EXACT values will be used for signing, FDMS submission, and DB persistence
                $receiptData['receiptCounter'] = $nextReceiptCounter;
                $receiptData['receiptGlobalNo'] = $nextGlobalNo;
                
                /*
                |----------------------------------------------------------------------
                | STEP 4B: Build canonical receipt and sign
                |----------------------------------------------------------------------
                | Uses the EXACT counter values calculated above
                |----------------------------------------------------------------------
                */
                $canonicalReceipt = $this->buildAndValidateReceiptBCMath(
                    $receiptData,
                    $fdmsFiscalDayNo,
                    $fdmsTaxes
                );
                
                $this->validateReceiptTotals($canonicalReceipt);
                
                // Build canonical string for signature using EXACT counters
                $canonicalString = $this->buildCanonicalStringForSignature(
                    $canonicalReceipt,
                    $deviceId,
                    $fdmsFiscalDayNo
                );
                
                Log::info('CANONICAL_STRING_FOR_SIGNING', [
                    'canonical_string' => $canonicalString,
                    'receipt_counter' => $nextReceiptCounter,
                    'global_no' => $nextGlobalNo,
                    'fiscal_day_no' => $fdmsFiscalDayNo,
                ]);
                
                // Sign the canonical string
                $signatureData = $this->signCanonicalString($canonicalString);
                
                // Build request payload
                $requestPayload = [
                    'receipt' => $canonicalReceipt,
                ];
                $requestPayload['receipt']['receiptDeviceSignature'] = $signatureData;
                
                $finalJson = json_encode($requestPayload, JSON_UNESCAPED_SLASHES);
                
                Log::info('FINAL_JSON_SENT', [
                    'json' => $finalJson,
                    'receipt_counter' => $nextReceiptCounter,
                    'global_no' => $nextGlobalNo,
                ]);
                
                /*
                |----------------------------------------------------------------------
                | STEP 4C: Submit to FDMS using EXACT counter values
                |----------------------------------------------------------------------
                | CRITICAL: If this fails, transaction rolls back and counters are NOT incremented
                |----------------------------------------------------------------------
                */
                $response = Http::withOptions([
                    'cert' => storage_path('app/zimra/device_certificate.pem'),
                    'ssl_key' => storage_path('app/zimra/device_private.key'),
                ])->withHeaders([
                    'DeviceModelName' => $zimraConfig->device_model,
                    'DeviceModelVersion' => $zimraConfig->device_version,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->withBody($finalJson, 'application/json')
                  ->post("{$baseUrl}/Device/v1/{$deviceId}/SubmitReceipt");
                
                $fdmsResponse = $response->json();
                
                Log::info('ZIMRA SubmitReceipt Response', [
                    'status' => $response->status(),
                    'response' => $fdmsResponse,
                    'receipt_counter' => $nextReceiptCounter,
                    'global_no' => $nextGlobalNo,
                ]);
                
                if (!$response->successful()) {
                    // FDMS rejected - rollback transaction, counters NOT incremented
                    Log::error('ZIMRA SubmitReceipt Failed - Rolling back transaction', [
                        'status' => $response->status(),
                        'body' => $fdmsResponse,
                        'receipt_counter' => $nextReceiptCounter,
                        'global_no' => $nextGlobalNo,
                    ]);
                    
                    throw new \Exception(
                        "FDMS rejected receipt: " . json_encode($fdmsResponse)
                    );
                }
                
                /*
                |----------------------------------------------------------------------
                | STEP 4D: Parse FDMS validation errors
                |----------------------------------------------------------------------
                */
                $validationErrors = $fdmsResponse['validationErrors'] ?? [];
                $fdmsReceiptId = $fdmsResponse['receiptID'] ?? null;
                
                foreach ($validationErrors as $error) {
                    $errorCode = $error['validationErrorCode'] ?? $error['errorCode'] ?? null;
                    $errorColor = $error['validationErrorColor'] ?? null;
                    $errorMessage = $error['errorMessage'] ?? $error['message'] ?? "Unknown error ({$errorCode})";
                    
                    if ($errorColor === 'Red') {
                        $hasRedErrors = true;
                    } elseif ($errorColor === 'Gray' || $errorColor === 'Grey') {
                        $hasGrayErrors = true;
                    }
                    
                    $parsedErrors[] = [
                        'validationErrorCode' => $errorCode,
                        'validationErrorColor' => $errorColor,
                        'errorMessage' => $errorMessage,
                        'field' => $error['field'] ?? null,
                    ];
                }
                
                if ($hasRedErrors) {
                    $receiptValidationCode = 'Red';
                } elseif ($hasGrayErrors) {
                    $receiptValidationCode = 'Gray';
                } else {
                    $receiptValidationCode = $fdmsResponse['receiptValidationCode'] ?? 'Green';
                }
                
                $isValid = !$hasRedErrors && !$hasGrayErrors && empty($validationErrors);
                
                /*
                |----------------------------------------------------------------------
                | STEP 4E: Persist receipt to DB using EXACT counter values
                |----------------------------------------------------------------------
                | CRITICAL: If this fails, we have a FDMS/DB divergence scenario
                | The catch block will handle this by marking device for reconciliation
                |----------------------------------------------------------------------
                */
                $primaryTax = $requestPayload['receipt']['receiptTaxes'][0] ?? [];
                $defaultReceiptType = (!$vatNumber || $vatNumber === 'NOT_REGISTERED') ? 'FiscalReceipt' : 'FiscalInvoice';
                
                try {
                    $receipt = Receipt::create([
                        'device_id' => $deviceId,
                        'invoice_no' => $receiptData['invoiceNo'] ?? 'N/A',
                        'receipt_type' => $receiptData['receiptType'] ?? $defaultReceiptType,
                        'receipt_currency' => $receiptData['receiptCurrency'] ?? 'USD',
                        'receipt_counter' => $nextReceiptCounter, // EXACT value from device_state
                        'receipt_global_no' => $nextGlobalNo, // EXACT value from device_state
                        'fiscal_day_no' => $fdmsFiscalDayNo, // EXACT value from FDMS
                        'receipt_total' => $receiptData['receiptTotal'],
                        'tax_amount' => $primaryTax['taxAmount'] ?? 0,
                        'tax_code' => $primaryTax['taxCode'] ?? 'A',
                        'tax_percent' => $primaryTax['taxPercent'] ?? 0,
                        'payment_method' => $receiptData['receiptPayments'][0]['moneyTypeCode'] ?? 'Cash',
                        'receipt_lines' => $receiptData['receiptLines'],
                        'receipt_taxes' => $receiptData['receiptTaxes'],
                        'receipt_payments' => $receiptData['receiptPayments'],
                        'buyer_data' => $receiptData['buyerData'] ?? null,
                        'receipt_hash' => $signatureData['hash'] ?? null,
                        'receipt_signature' => $signatureData ?? null,
                        'zimra_response' => $fdmsResponse,
                        'receipt_date' => $receiptData['receiptDate'] ?? now(),
                        'validation_code' => $receiptValidationCode,
                        'validation_errors' => $parsedErrors,
                        'is_valid' => $isValid,
                        'has_red_errors' => $hasRedErrors,
                        'has_gray_errors' => $hasGrayErrors,
                        'fdms_receipt_id' => $fdmsReceiptId,
                    ]);
                    
                    Log::info('Receipt Saved to Database', [
                        'receipt_id' => $receipt->id,
                        'fdms_receipt_id' => $fdmsReceiptId,
                        'receipt_counter' => $nextReceiptCounter,
                        'global_no' => $nextGlobalNo,
                        'fiscal_day_no' => $fdmsFiscalDayNo,
                    ]);
                    
                } catch (\Exception $dbException) {
                    /*
                    |------------------------------------------------------------------
                    | CRITICAL RECONCILIATION SCENARIO
                    |------------------------------------------------------------------
                    | FDMS accepted the receipt, but DB persistence failed
                    | This creates a divergence between FDMS and local DB state
                    | 
                    | Actions:
                    | 1. Mark device_state.requires_reconciliation = true
                    | 2. Log CRITICAL error with all details
                    | 3. Throw exception to rollback transaction
                    | 4. Block all future receipt submissions until manual fix
                    |------------------------------------------------------------------
                    */
                    $errorDetails = [
                        'error' => 'FDMS_DB_DIVERGENCE',
                        'fdms_status' => 'ACCEPTED',
                        'db_status' => 'FAILED',
                        'fdms_receipt_id' => $fdmsReceiptId,
                        'receipt_counter' => $nextReceiptCounter,
                        'global_no' => $nextGlobalNo,
                        'fiscal_day_no' => $fdmsFiscalDayNo,
                        'db_error' => $dbException->getMessage(),
                        'timestamp' => now()->toIso8601String(),
                    ];
                    
                    Log::critical('FDMS/DB DIVERGENCE DETECTED', $errorDetails);
                    
                    // Mark device for reconciliation (this will be committed even if transaction rolls back)
                    DB::table('device_state')
                        ->where('device_id', $deviceId)
                        ->update([
                            'requires_reconciliation' => true,
                            'reconciliation_error' => json_encode($errorDetails),
                            'reconciliation_error_at' => now(),
                        ]);
                    
                    throw new \Exception(
                        "CRITICAL: FDMS accepted receipt (ID: {$fdmsReceiptId}) but DB persistence failed. " .
                        "Error: {$dbException->getMessage()}. " .
                        "Device marked for reconciliation. Manual intervention required."
                    );
                }
                
                /*
                |----------------------------------------------------------------------
                | STEP 4F: Increment device_state counters (SUCCESS PATH)
                |----------------------------------------------------------------------
                | CRITICAL: Only reached if BOTH FDMS acceptance AND DB persistence succeeded
                | This ensures device_state always reflects successfully persisted receipts
                |----------------------------------------------------------------------
                */
                $lockedDeviceState->incrementCounters($fdmsFiscalDayNo, $nextReceiptCounter, $nextGlobalNo);
                
                Log::info('device_state counters incremented', [
                    'device_id' => $deviceId,
                    'new_fiscal_day_no' => $fdmsFiscalDayNo,
                    'new_receipt_counter' => $nextReceiptCounter,
                    'new_global_no' => $nextGlobalNo,
                ]);
                
                // Update fiscal day counters
                $this->updateFiscalCounters($fiscalDay, $receiptData);
            });
            
            // Transaction committed successfully
            Log::info('ZIMRA Receipt Submitted Successfully - Transaction Committed', [
                'receipt_id' => $fdmsReceiptId,
                'db_receipt_id' => $receipt->id,
                'receipt_counter' => $nextReceiptCounter,
                'global_no' => $nextGlobalNo,
                'fiscal_day_no' => $fdmsFiscalDayNo,
            ]);
            
        } catch (\Exception $e) {
            // Transaction rolled back
            Log::error('ZIMRA SubmitReceipt Transaction Failed - Rolled Back', [
                'error' => $e->getMessage(),
                'receipt_counter' => $nextReceiptCounter,
                'global_no' => $nextGlobalNo,
            ]);
            
            throw $e;
        }
        
        /*
        |--------------------------------------------------------------------------
        | STEP 5: Throw exceptions for validation errors (after successful persistence)
        |--------------------------------------------------------------------------
        */
        if ($hasRedErrors) {
            $errorSummary = collect($parsedErrors)->map(function ($e) {
                return "{$e['validationErrorCode']}: {$e['errorMessage']}";
            })->implode('; ');
            
            throw new \Exception(
                "ZIMRA Receipt Validation Failed [RED]: {$errorSummary}. " .
                "Receipt saved to database (ID: {$receipt->id}). CloseDay will fail with this receipt."
            );
        }
        
        if ($hasGrayErrors) {
            $errorSummary = collect($parsedErrors)->map(function ($e) {
                return "{$e['validationErrorCode']}: {$e['errorMessage']}";
            })->implode('; ');
            
            throw new \Exception(
                "ZIMRA Receipt Validation Warning [GRAY]: {$errorSummary}. " .
                "Receipt saved to database (ID: {$receipt->id}). CloseDay will fail with this receipt."
            );
        }
        
        return [
            'success' => true,
            'data' => $fdmsResponse,
            'fiscal_day_no' => $fdmsFiscalDayNo,
            'validation_code' => $receiptValidationCode,
            'db_receipt_id' => $receipt->id,
            'receipt_counter' => $nextReceiptCounter,
            'global_no' => $nextGlobalNo,
        ];
    }
    
    // Placeholder methods (implement from existing ZimraDeviceService)
    private function getStatus($deviceId) { /* ... */ }
    private function getConfig($deviceId) { /* ... */ }
    private function getFdmsTaxConfig($zimraConfig) { /* ... */ }
    private function buildAndValidateReceiptBCMath($receiptData, $fiscalDayNo, $fdmsTaxes) { /* ... */ }
    private function validateReceiptTotals($canonicalReceipt) { /* ... */ }
    private function buildCanonicalStringForSignature($canonicalReceipt, $deviceId, $fiscalDayNo) { /* ... */ }
    private function signCanonicalString($canonicalString) { /* ... */ }
    private function updateFiscalCounters($fiscalDay, $receiptData) { /* ... */ }
}
