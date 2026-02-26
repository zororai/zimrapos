<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DeviceState Model
 * 
 * CRITICAL: Single source of truth for fiscal device counters.
 * This table ensures atomic counter generation and prevents:
 * - Race conditions in concurrent receipt submissions
 * - CountersMismatch errors with FDMS
 * - Duplicate receipt counters
 * - FDMS/DB state divergence
 * 
 * Counter Increment Rules:
 * 1. Counters are incremented ONLY after BOTH FDMS acceptance AND DB persistence
 * 2. If FDMS accepts but DB fails, requires_reconciliation flag is set
 * 3. Counters are NEVER recalculated from receipts table after this migration
 * 4. All counter reads MUST use lockForUpdate() in transactions
 */
class DeviceState extends Model
{
    protected $table = 'device_state';
    
    protected $fillable = [
        'device_id',
        'last_receipt_global_no',
        'last_receipt_counter',
        'last_fiscal_day_no',
        'requires_reconciliation',
        'reconciliation_error',
        'reconciliation_error_at',
        'locked_at',
        'locked_by',
    ];
    
    protected $casts = [
        'device_id' => 'integer',
        'last_receipt_global_no' => 'integer',
        'last_receipt_counter' => 'integer',
        'last_fiscal_day_no' => 'integer',
        'requires_reconciliation' => 'boolean',
        'reconciliation_error_at' => 'datetime',
        'locked_at' => 'datetime',
    ];
    
    /**
     * Get the next receipt counter for the current fiscal day
     * 
     * CRITICAL: This method MUST be called within a DB transaction with lockForUpdate()
     * 
     * @param int $currentFiscalDayNo The current open fiscal day number
     * @return int Next receipt counter (1-based)
     */
    public function getNextReceiptCounter(int $currentFiscalDayNo): int
    {
        // If fiscal day changed, reset counter to 1
        if ($this->last_fiscal_day_no !== $currentFiscalDayNo) {
            return 1;
        }
        
        // Otherwise increment from last counter
        return $this->last_receipt_counter + 1;
    }
    
    /**
     * Get the next global receipt number
     * 
     * CRITICAL: This method MUST be called within a DB transaction with lockForUpdate()
     * 
     * @return int Next global receipt number (1-based)
     */
    public function getNextGlobalNo(): int
    {
        return $this->last_receipt_global_no + 1;
    }
    
    /**
     * Increment counters after successful FDMS submission AND DB persistence
     * 
     * CRITICAL: This method MUST be called within a DB transaction
     * 
     * @param int $fiscalDayNo The fiscal day number for this receipt
     * @param int $receiptCounter The receipt counter that was used
     * @param int $globalNo The global number that was used
     * @return bool Success
     */
    public function incrementCounters(int $fiscalDayNo, int $receiptCounter, int $globalNo): bool
    {
        $this->last_fiscal_day_no = $fiscalDayNo;
        $this->last_receipt_counter = $receiptCounter;
        $this->last_receipt_global_no = $globalNo;
        
        return $this->save();
    }
    
    /**
     * Mark device as requiring reconciliation
     * 
     * CRITICAL: Called when FDMS accepts a receipt but DB persistence fails
     * This prevents further receipt submissions until manual intervention
     * 
     * @param string $error The error message
     * @return bool Success
     */
    public function markForReconciliation(string $error): bool
    {
        $this->requires_reconciliation = true;
        $this->reconciliation_error = $error;
        $this->reconciliation_error_at = now();
        
        return $this->save();
    }
    
    /**
     * Clear reconciliation flag after manual fix
     * 
     * @return bool Success
     */
    public function clearReconciliation(): bool
    {
        $this->requires_reconciliation = false;
        $this->reconciliation_error = null;
        $this->reconciliation_error_at = null;
        
        return $this->save();
    }
    
    /**
     * Check if device requires reconciliation
     * 
     * @return bool
     */
    public function requiresReconciliation(): bool
    {
        return $this->requires_reconciliation === true;
    }
}
