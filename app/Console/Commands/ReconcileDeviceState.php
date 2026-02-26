<?php

namespace App\Console\Commands;

use App\Models\DeviceState;
use App\Models\Receipt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconcile Device State Command
 * 
 * Handles FDMS/DB divergence scenarios where FDMS accepted a receipt
 * but local DB persistence failed.
 * 
 * Usage:
 *   php artisan zimra:reconcile-device-state {device_id}
 *   php artisan zimra:reconcile-device-state 32558 --check-only
 */
class ReconcileDeviceState extends Command
{
    protected $signature = 'zimra:reconcile-device-state 
                            {device_id : The device ID to reconcile}
                            {--check-only : Only check status, do not fix}
                            {--clear : Clear reconciliation flag (use after manual fix)}';

    protected $description = 'Reconcile device state after FDMS/DB divergence';

    public function handle()
    {
        $deviceId = $this->argument('device_id');
        $checkOnly = $this->option('check-only');
        $clear = $this->option('clear');

        $this->info("=== Device State Reconciliation ===");
        $this->info("Device ID: {$deviceId}");
        $this->newLine();

        // Get device state
        $deviceState = DeviceState::where('device_id', $deviceId)->first();

        if (!$deviceState) {
            $this->error("❌ No device_state record found for device {$deviceId}");
            return 1;
        }

        // Display current state
        $this->info("Current Device State:");
        $this->table(
            ['Field', 'Value'],
            [
                ['Device ID', $deviceState->device_id],
                ['Last Global No', $deviceState->last_receipt_global_no],
                ['Last Receipt Counter', $deviceState->last_receipt_counter],
                ['Last Fiscal Day No', $deviceState->last_fiscal_day_no],
                ['Requires Reconciliation', $deviceState->requires_reconciliation ? 'YES ⚠️' : 'NO ✓'],
                ['Reconciliation Error', $deviceState->reconciliation_error ?? 'None'],
                ['Error Occurred At', $deviceState->reconciliation_error_at ?? 'N/A'],
            ]
        );
        $this->newLine();

        // Check if reconciliation needed
        if (!$deviceState->requiresReconciliation()) {
            $this->info("✓ Device does not require reconciliation");
            
            if ($checkOnly) {
                return 0;
            }
            
            // Verify device_state matches receipts table
            $this->info("Verifying device_state matches receipts table...");
            $this->verifyDeviceState($deviceState);
            
            return 0;
        }

        // Parse reconciliation error
        $errorData = json_decode($deviceState->reconciliation_error, true);
        
        $this->error("⚠️  RECONCILIATION REQUIRED");
        $this->newLine();
        
        if ($errorData) {
            $this->info("Error Details:");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Error Type', $errorData['error'] ?? 'Unknown'],
                    ['FDMS Status', $errorData['fdms_status'] ?? 'Unknown'],
                    ['DB Status', $errorData['db_status'] ?? 'Unknown'],
                    ['FDMS Receipt ID', $errorData['fdms_receipt_id'] ?? 'Unknown'],
                    ['Receipt Counter', $errorData['receipt_counter'] ?? 'Unknown'],
                    ['Global No', $errorData['global_no'] ?? 'Unknown'],
                    ['Fiscal Day No', $errorData['fiscal_day_no'] ?? 'Unknown'],
                    ['DB Error', $errorData['db_error'] ?? 'Unknown'],
                    ['Timestamp', $errorData['timestamp'] ?? 'Unknown'],
                ]
            );
            $this->newLine();
        }

        if ($checkOnly) {
            $this->warn("Check-only mode. No changes made.");
            $this->info("\nNext Steps:");
            $this->info("1. Check FDMS portal for receipt: {$errorData['fdms_receipt_id']}");
            $this->info("2. Manually insert receipt into DB if missing");
            $this->info("3. Run: php artisan zimra:reconcile-device-state {$deviceId} --clear");
            return 0;
        }

        if ($clear) {
            if ($this->confirm('Clear reconciliation flag? (Only do this after manual fix)', false)) {
                $deviceState->clearReconciliation();
                $this->info("✓ Reconciliation flag cleared");
                $this->info("Device can now accept new receipts");
                return 0;
            } else {
                $this->warn("Reconciliation flag NOT cleared");
                return 1;
            }
        }

        // Automatic reconciliation attempt
        $this->warn("Automatic reconciliation not implemented yet.");
        $this->info("\nManual Steps Required:");
        $this->info("1. Check FDMS portal for receipt: {$errorData['fdms_receipt_id']}");
        $this->info("2. If receipt exists in FDMS:");
        $this->info("   - Manually insert into receipts table with correct counters");
        $this->info("   - Run: php artisan zimra:reconcile-device-state {$deviceId} --clear");
        $this->info("3. If receipt does NOT exist in FDMS:");
        $this->info("   - Run: php artisan zimra:reconcile-device-state {$deviceId} --clear");
        $this->info("   - System will retry with next counter");

        return 1;
    }

    /**
     * Verify device_state matches receipts table
     */
    private function verifyDeviceState(DeviceState $deviceState)
    {
        $deviceId = $deviceState->device_id;

        // Get max global no from receipts
        $maxGlobalNo = Receipt::where('device_id', $deviceId)
            ->max('receipt_global_no') ?? 0;

        // Get current fiscal day and max counter
        $currentFiscalDay = $deviceState->last_fiscal_day_no;
        $maxReceiptCounter = 0;
        
        if ($currentFiscalDay > 0) {
            $maxReceiptCounter = Receipt::where('device_id', $deviceId)
                ->where('fiscal_day_no', $currentFiscalDay)
                ->max('receipt_counter') ?? 0;
        }

        // Compare
        $issues = [];

        if ($deviceState->last_receipt_global_no !== $maxGlobalNo) {
            $issues[] = [
                'Field' => 'last_receipt_global_no',
                'device_state' => $deviceState->last_receipt_global_no,
                'receipts_table' => $maxGlobalNo,
                'Status' => '❌ MISMATCH',
            ];
        } else {
            $this->info("✓ last_receipt_global_no matches: {$maxGlobalNo}");
        }

        if ($deviceState->last_receipt_counter !== $maxReceiptCounter) {
            $issues[] = [
                'Field' => 'last_receipt_counter',
                'device_state' => $deviceState->last_receipt_counter,
                'receipts_table' => $maxReceiptCounter,
                'Status' => '❌ MISMATCH',
            ];
        } else {
            $this->info("✓ last_receipt_counter matches: {$maxReceiptCounter}");
        }

        if (!empty($issues)) {
            $this->newLine();
            $this->error("⚠️  Device state does NOT match receipts table:");
            $this->table(
                ['Field', 'device_state', 'receipts_table', 'Status'],
                $issues
            );
            $this->newLine();
            
            if ($this->confirm('Fix device_state to match receipts table?', false)) {
                DB::transaction(function () use ($deviceState, $maxGlobalNo, $maxReceiptCounter) {
                    $deviceState->last_receipt_global_no = $maxGlobalNo;
                    $deviceState->last_receipt_counter = $maxReceiptCounter;
                    $deviceState->save();
                });
                
                $this->info("✓ Device state updated to match receipts table");
            }
        } else {
            $this->info("✓ Device state matches receipts table");
        }
    }
}
