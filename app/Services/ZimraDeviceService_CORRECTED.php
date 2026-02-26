<?php

namespace App\Services;

use App\Models\DeviceState;
use App\Models\Receipt;
use App\Models\FiscalDay;
use App\Models\ZimraConfig;
use App\Models\CompanyDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * STRUCTURALLY CORRECTED ZimraDeviceService
 * 
 * CRITICAL FIXES:
 * 1. NO device fallback - device_id MUST be passed from middleware
 * 2. Counters ONLY from device_states (never from receipts table)
 * 3. FDMS state alignment check before counter increment
 * 4. Proper company-device authorization
 */
class ZimraDeviceService_CORRECTED
{
    /**
     * Submit receipt to ZIMRA FDMS
     * 
     * CRITICAL: device_id MUST be provided (no fallback to config)
     * 
     * @param array $receiptData Receipt data
     * @param int $deviceId Device ID (REQUIRED - from middleware)
     * @return array
     * @throws \Exception
     */
    public function submitReceipt(array $receiptData, int $deviceId): array
    {
        // CRITICAL: NO FALLBACK - device_id is REQUIRED
        if (!$deviceId) {
            throw new \Exception(
                'CRITICAL: device_id is required. ' .
                'Device ID must be provided by ResolveCompanyDevice middleware. ' .
                'Do not call this method directly without middleware.'
            );
        }
        
        Log::info('ZIMRA SubmitReceipt - START', [
            'device_id' => $deviceId,
            'invoice_no' => $receiptData['invoiceNo'] ?? 'N/A',
        ]);
        
        // Get FDMS fiscal day status
        $fdmsStatus = $this->getStatus($deviceId);
        $fdmsFiscalDayNo = $fdmsStatus['lastFiscalDayNo'] ?? null;
        
        if (!$fdmsFiscalDayNo) {
            throw new \Exception('Cannot determine current fiscal day from FDMS');
        }
        
        /*
        |--------------------------------------------------------------------------
        | STEP 1: Check Device State Reconciliation Status
        |--------------------------------------------------------------------------
        */
        $deviceState = DeviceState::where('device_id', $deviceId)->first();
        
        if (!$deviceState) {
            throw new \Exception(
                "CRITICAL: No device_state record found for device {$deviceId}. " .
                "Run migration: php artisan migrate"
            );
        }
        
        if ($deviceState->requires_reconciliation) {
            throw new \Exception(
                "CRITICAL: Device {$deviceId} requires reconciliation. " .
                "FDMS accepted a receipt but DB persistence failed. " .
                "Error: {$deviceState->reconciliation_error}. " .
                "Manual intervention required."
            );
        }
        
        /*
        |--------------------------------------------------------------------------
        | STEP 2: FDMS State Alignment Check
        |--------------------------------------------------------------------------
        | Compare device_state with FDMS before proceeding
        |--------------------------------------------------------------------------
        */
        $this->verifyFdmsAlignment($deviceState, $fdmsStatus, $deviceId);
        
        /*
        |--------------------------------------------------------------------------
        | STEP 3: Atomic Transaction with device_state Counter Calculation
        |--------------------------------------------------------------------------
        | CRITICAL: Counters come ONLY from device_states, NOT from receipts
        |--------------------------------------------------------------------------
        */
        $nextReceiptCounter = null;
        $nextGlobalNo = null;
        $dbReceiptId = null;
        
        DB::transaction(function () use (
            $deviceId,
            $fdmsFiscalDayNo,
            &$receiptData,
            &$nextReceiptCounter,
            &$nextGlobalNo,
            &$dbReceiptId
        ) {
            // Lock device_state row (prevents concurrent access)
            $lockedDeviceState = DeviceState::where('device_id', $deviceId)
                ->lockForUpdate()
                ->first();
            
            if (!$lockedDeviceState) {
                throw new \Exception("CRITICAL: device_state row disappeared during transaction");
            }
            
            // Calculate next counters from device_state (NOT from receipts table)
            $nextReceiptCounter = $lockedDeviceState->getNextReceiptCounter($fdmsFiscalDayNo);
            $nextGlobalNo = $lockedDeviceState->getNextGlobalNo();
            
            Log::info('Counters from device_state (LOCKED)', [
                'device_state_last_fiscal_day' => $lockedDeviceState->last_fiscal_day_no,
                'device_state_last_counter' => $lockedDeviceState->last_receipt_counter,
                'device_state_last_global' => $lockedDeviceState->last_receipt_global_no,
                'fdms_fiscal_day_no' => $fdmsFiscalDayNo,
                'next_receipt_counter' => $nextReceiptCounter,
                'next_global_no' => $nextGlobalNo,
            ]);
            
            // Set counters in receipt data
            $receiptData['receiptCounter'] = $nextReceiptCounter;
            $receiptData['receiptGlobalNo'] = $nextGlobalNo;
            
            // Build canonical string and sign
            $canonicalString = $this->buildCanonicalStringForSignature(
                $receiptData,
                $deviceId,
                $fdmsFiscalDayNo
            );
            
            $signature = $this->signCanonicalString($canonicalString);
            $receiptData['receiptDeviceSignature'] = $signature;
            
            // Send to FDMS
            $fdmsResponse = $this->sendReceiptToFdms($receiptData, $deviceId);
            
            if (!$fdmsResponse['success']) {
                // FDMS rejected - rollback transaction (counters NOT incremented)
                throw new \Exception("FDMS rejected receipt: " . ($fdmsResponse['error'] ?? 'Unknown error'));
            }
            
            // FDMS accepted - now save to DB with EXACT same counters
            try {
                $receipt = Receipt::create([
                    'device_id' => $deviceId,
                    'fiscal_day_no' => $fdmsFiscalDayNo,
                    'receipt_counter' => $nextReceiptCounter,
                    'receipt_global_no' => $nextGlobalNo,
                    'invoice_no' => $receiptData['invoiceNo'] ?? null,
                    'receipt_type' => $receiptData['receiptType'] ?? 'FiscalReceipt',
                    'receipt_currency' => $receiptData['receiptCurrency'] ?? 'USD',
                    'receipt_total' => $receiptData['receiptTotal'] ?? 0,
                    'receipt_date' => $receiptData['receiptDate'] ?? now(),
                    'receipt_lines' => $receiptData['receiptLines'] ?? [],
                    'receipt_taxes' => $receiptData['receiptTaxes'] ?? [],
                    'receipt_payments' => $receiptData['receiptPayments'] ?? [],
                    'receipt_signature' => $signature,
                    'fdms_receipt_id' => $fdmsResponse['fdms_receipt_id'] ?? null,
                    'is_valid' => true,
                ]);
                
                $dbReceiptId = $receipt->id;
                
            } catch (\Exception $dbException) {
                // CRITICAL: FDMS accepted but DB failed - RECONCILIATION REQUIRED
                $errorDetails = [
                    'error' => 'FDMS_DB_DIVERGENCE',
                    'fdms_status' => 'ACCEPTED',
                    'db_status' => 'FAILED',
                    'fdms_receipt_id' => $fdmsResponse['fdms_receipt_id'] ?? null,
                    'receipt_counter' => $nextReceiptCounter,
                    'global_no' => $nextGlobalNo,
                    'fiscal_day_no' => $fdmsFiscalDayNo,
                    'db_error' => $dbException->getMessage(),
                    'timestamp' => now()->toIso8601String(),
                ];
                
                Log::critical('FDMS/DB DIVERGENCE DETECTED', $errorDetails);
                
                // Mark device for reconciliation
                $lockedDeviceState->markForReconciliation(json_encode($errorDetails));
                
                throw new \Exception(
                    "CRITICAL: FDMS accepted receipt but DB persistence failed. " .
                    "Device marked for reconciliation. " .
                    "Error: {$dbException->getMessage()}"
                );
            }
            
            // SUCCESS: Increment device_state counters
            $lockedDeviceState->incrementCounters($fdmsFiscalDayNo, $nextReceiptCounter, $nextGlobalNo);
            
            Log::info('Receipt submitted successfully', [
                'device_id' => $deviceId,
                'db_receipt_id' => $dbReceiptId,
                'receipt_counter' => $nextReceiptCounter,
                'global_no' => $nextGlobalNo,
                'fiscal_day_no' => $fdmsFiscalDayNo,
            ]);
        });
        
        return [
            'success' => true,
            'db_receipt_id' => $dbReceiptId,
            'receipt_counter' => $nextReceiptCounter,
            'global_no' => $nextGlobalNo,
            'fiscal_day_no' => $fdmsFiscalDayNo,
        ];
    }
    
    /**
     * Close fiscal day
     * 
     * CRITICAL: device_id MUST be provided (no fallback to config)
     * 
     * @param int $deviceId Device ID (REQUIRED - from middleware)
     * @return array
     * @throws \Exception
     */
    public function closeDay(int $deviceId): array
    {
        // CRITICAL: NO FALLBACK - device_id is REQUIRED
        if (!$deviceId) {
            throw new \Exception(
                'CRITICAL: device_id is required. ' .
                'Device ID must be provided by ResolveCompanyDevice middleware.'
            );
        }
        
        Log::info('ZIMRA CloseDay - START', ['device_id' => $deviceId]);
        
        // Get current open fiscal day for this device
        $fiscalDay = FiscalDay::where('device_id', $deviceId)
            ->where('is_closed', false)
            ->orderBy('fiscal_day_no', 'desc')
            ->first();
        
        if (!$fiscalDay) {
            return [
                'success' => false,
                'error' => 'No open fiscal day found to close',
            ];
        }
        
        // Build close day payload from receipts for THIS device
        $payload = $this->buildCloseDayPayload($fiscalDay, $deviceId);
        
        // Send to FDMS
        $response = $this->sendCloseDayToFdms($payload, $deviceId);
        
        if ($response['success']) {
            // Mark fiscal day as closed
            $fiscalDay->update([
                'is_closed' => true,
                'closed_at' => now(),
                'close_response' => $response,
            ]);
        }
        
        return $response;
    }
    
    /**
     * Get device status from FDMS
     * 
     * CRITICAL: device_id MUST be provided (no fallback to config)
     * 
     * @param int $deviceId Device ID (REQUIRED - from middleware)
     * @return array
     * @throws \Exception
     */
    public function getStatus(int $deviceId): array
    {
        // CRITICAL: NO FALLBACK - device_id is REQUIRED
        if (!$deviceId) {
            throw new \Exception(
                'CRITICAL: device_id is required. ' .
                'Device ID must be provided by ResolveCompanyDevice middleware.'
            );
        }
        
        // Get ZIMRA config for this device
        $zimraConfig = ZimraConfig::where('device_id', $deviceId)->first();
        
        if (!$zimraConfig) {
            throw new \Exception("No ZIMRA configuration found for device {$deviceId}");
        }
        
        // Call FDMS GetStatus API
        $response = Http::withOptions([
            'cert' => $zimraConfig->certificate_path,
            'ssl_key' => $zimraConfig->private_key_path,
        ])->get("{$zimraConfig->base_url}/Device/v1/{$deviceId}/GetStatus");
        
        if (!$response->successful()) {
            throw new \Exception("FDMS GetStatus failed: " . $response->body());
        }
        
        return $response->json();
    }
    
    /**
     * Verify FDMS state alignment with device_state
     * 
     * CRITICAL: Detects state divergence before proceeding
     * 
     * @param DeviceState $deviceState
     * @param array $fdmsStatus
     * @param int $deviceId
     * @throws \Exception If misalignment detected
     */
    private function verifyFdmsAlignment(DeviceState $deviceState, array $fdmsStatus, int $deviceId): void
    {
        $fdmsLastGlobal = $fdmsStatus['lastReceiptGlobalNo'] ?? 0;
        $fdmsLastDay = $fdmsStatus['lastFiscalDayNo'] ?? 0;
        
        $deviceStateLastGlobal = $deviceState->last_receipt_global_no;
        $deviceStateLastDay = $deviceState->last_fiscal_day_no;
        
        // Check for misalignment
        if ($fdmsLastGlobal !== $deviceStateLastGlobal) {
            Log::critical('FDMS_DEVICE_STATE_MISALIGNMENT', [
                'device_id' => $deviceId,
                'fdms_last_global' => $fdmsLastGlobal,
                'device_state_last_global' => $deviceStateLastGlobal,
                'difference' => $fdmsLastGlobal - $deviceStateLastGlobal,
            ]);
            
            throw new \Exception(
                "CRITICAL: FDMS/device_state misalignment detected. " .
                "FDMS lastReceiptGlobalNo: {$fdmsLastGlobal}, " .
                "device_state last_receipt_global_no: {$deviceStateLastGlobal}. " .
                "Reconciliation required before proceeding."
            );
        }
        
        if ($fdmsLastDay !== $deviceStateLastDay) {
            Log::warning('FDMS_DEVICE_STATE_DAY_MISMATCH', [
                'device_id' => $deviceId,
                'fdms_last_day' => $fdmsLastDay,
                'device_state_last_day' => $deviceStateLastDay,
            ]);
            
            // Day mismatch may be acceptable if day just changed
            // But log it for monitoring
        }
        
        Log::info('FDMS alignment verified', [
            'device_id' => $deviceId,
            'fdms_last_global' => $fdmsLastGlobal,
            'device_state_last_global' => $deviceStateLastGlobal,
            'aligned' => true,
        ]);
    }
    
    /**
     * Build canonical string for signature (placeholder - implement actual logic)
     */
    private function buildCanonicalStringForSignature(array $receipt, int $deviceId, int $fiscalDayNo): string
    {
        // TODO: Implement actual canonical string building logic
        // This is a placeholder - use your existing implementation
        return "canonical_string_placeholder";
    }
    
    /**
     * Sign canonical string (placeholder - implement actual logic)
     */
    private function signCanonicalString(string $canonicalString): array
    {
        // TODO: Implement actual signing logic
        // This is a placeholder - use your existing implementation
        return ['signature' => 'placeholder'];
    }
    
    /**
     * Send receipt to FDMS (placeholder - implement actual logic)
     */
    private function sendReceiptToFdms(array $receiptData, int $deviceId): array
    {
        // TODO: Implement actual FDMS API call
        // This is a placeholder - use your existing implementation
        return ['success' => true, 'fdms_receipt_id' => 'placeholder'];
    }
    
    /**
     * Build close day payload (placeholder - implement actual logic)
     */
    private function buildCloseDayPayload(FiscalDay $fiscalDay, int $deviceId): array
    {
        // TODO: Implement actual payload building logic
        // This is a placeholder - use your existing implementation
        return [];
    }
    
    /**
     * Send close day to FDMS (placeholder - implement actual logic)
     */
    private function sendCloseDayToFdms(array $payload, int $deviceId): array
    {
        // TODO: Implement actual FDMS API call
        // This is a placeholder - use your existing implementation
        return ['success' => true];
    }
}
