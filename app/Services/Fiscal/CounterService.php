<?php

namespace App\Services\Fiscal;

use App\Models\DeviceState;
use App\Models\FiscalDay;
use App\Models\ZimraConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CounterService
{
    protected int $deviceId;
    protected ?DeviceState $deviceState = null;
    protected ?FiscalDay $currentFiscalDay = null;

    public function __construct(?int $deviceId = null)
    {
        if ($deviceId) {
            $this->setDeviceId($deviceId);
        }
    }

    public function setDeviceId(int $deviceId): self
    {
        $this->deviceId = $deviceId;
        $this->loadDeviceState();
        return $this;
    }

    protected function loadDeviceState(): void
    {
        $this->deviceState = DeviceState::where('device_id', $this->deviceId)
            ->lockForUpdate()
            ->first();

        if (!$this->deviceState) {
            $this->deviceState = DeviceState::create([
                'device_id' => $this->deviceId,
                'last_receipt_counter' => 0,
                'last_receipt_global_no' => 0,
                'last_fiscal_day_no' => 0,
            ]);
        }
    }

    public function getCurrentFiscalDayNo(): int
    {
        return $this->deviceState->last_fiscal_day_no ?? 0;
    }

    public function getLastReceiptCounter(): int
    {
        return $this->deviceState->last_receipt_counter ?? 0;
    }

    public function getLastReceiptGlobalNo(): int
    {
        return $this->deviceState->last_receipt_global_no ?? 0;
    }

    /**
     * Reserve counters for a new receipt
     * Must be called within a database transaction
     * 
     * @param int $fiscalDayNo
     * @return array{receiptCounter: int, receiptGlobalNo: int, fiscalDayNo: int}
     */
    public function reserveCounters(int $fiscalDayNo): array
    {
        // Reload with lock
        $this->deviceState = DeviceState::where('device_id', $this->deviceId)
            ->lockForUpdate()
            ->first();

        // Check if fiscal day changed - reset counter
        if ($fiscalDayNo !== $this->deviceState->last_fiscal_day_no) {
            $nextCounter = 1;
        } else {
            $nextCounter = $this->deviceState->last_receipt_counter + 1;
        }

        $nextGlobalNo = $this->deviceState->last_receipt_global_no + 1;

        Log::info('CounterService: Reserving counters', [
            'device_id' => $this->deviceId,
            'fiscal_day_no' => $fiscalDayNo,
            'receipt_counter' => $nextCounter,
            'receipt_global_no' => $nextGlobalNo,
            'previous_counter' => $this->deviceState->last_receipt_counter,
            'previous_global_no' => $this->deviceState->last_receipt_global_no,
        ]);

        return [
            'receiptCounter' => $nextCounter,
            'receiptGlobalNo' => $nextGlobalNo,
            'fiscalDayNo' => $fiscalDayNo,
        ];
    }

    /**
     * Commit counters after successful FDMS submission
     * Must be called within the same database transaction as reserveCounters
     * 
     * @param int $fiscalDayNo
     * @param int $receiptCounter
     * @param int $receiptGlobalNo
     * @param string $receiptHash For chain continuity recovery
     * @param int $receiptId For crash recovery
     */
    public function commitCounters(
        int $fiscalDayNo,
        int $receiptCounter,
        int $receiptGlobalNo,
        string $receiptHash,
        int $receiptId
    ): void {
        $this->deviceState->update([
            'last_fiscal_day_no' => $fiscalDayNo,
            'last_receipt_counter' => $receiptCounter,
            'last_receipt_global_no' => $receiptGlobalNo,
            'last_receipt_hash' => $receiptHash,
            'last_receipt_id' => $receiptId,
        ]);

        Log::info('CounterService: Counters committed', [
            'device_id' => $this->deviceId,
            'fiscal_day_no' => $fiscalDayNo,
            'receipt_counter' => $receiptCounter,
            'receipt_global_no' => $receiptGlobalNo,
            'receipt_hash' => $receiptHash,
            'receipt_id' => $receiptId,
        ]);
    }

    /**
     * Get last receipt hash for chain continuity
     * Uses device_state first (faster), falls back to receipts table
     */
    public function getLastReceiptHash(): string
    {
        // Try device_state first (fast path)
        if ($this->deviceState && $this->deviceState->last_receipt_hash) {
            return $this->deviceState->last_receipt_hash;
        }

        // Fallback: query receipts table by receipt_global_no (correct chain)
        $lastReceipt = \App\Models\Receipt::where('device_id', $this->deviceId)
            ->where('status', 'finalized')
            ->orderBy('receipt_global_no', 'desc')
            ->first();

        return $lastReceipt?->receipt_hash ?? '';
    }

    /**
     * Sync counters from FDMS (in case of drift)
     */
    public function syncFromFdms(int $fdmsFiscalDayNo, int $fdmsLastGlobalNo): void
    {
        $currentGlobalNo = $this->deviceState->last_receipt_global_no;

        if ($fdmsLastGlobalNo > $currentGlobalNo) {
            Log::warning('CounterService: Syncing counters from FDMS (drift detected)', [
                'device_id' => $this->deviceId,
                'local_global_no' => $currentGlobalNo,
                'fdms_global_no' => $fdmsLastGlobalNo,
            ]);

            $this->deviceState->update([
                'last_receipt_global_no' => $fdmsLastGlobalNo,
                'last_fiscal_day_no' => $fdmsFiscalDayNo,
            ]);
        }
    }

    /**
     * Open a new fiscal day
     */
    public function openFiscalDay(int $fiscalDayNo): FiscalDay
    {
        $existingDay = FiscalDay::where('device_id', $this->deviceId)
            ->where('fiscal_day_no', $fiscalDayNo)
            ->first();

        if ($existingDay) {
            return $existingDay;
        }

        $fiscalDay = FiscalDay::create([
            'device_id' => $this->deviceId,
            'fiscal_day_no' => $fiscalDayNo,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->deviceState->update([
            'last_fiscal_day_no' => $fiscalDayNo,
            'last_receipt_counter' => 0,
        ]);

        Log::info('CounterService: Fiscal day opened', [
            'device_id' => $this->deviceId,
            'fiscal_day_no' => $fiscalDayNo,
        ]);

        return $fiscalDay;
    }

    /**
     * Get device state for debugging
     */
    public function getDeviceState(): ?DeviceState
    {
        return $this->deviceState;
    }
}
