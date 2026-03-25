<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed device_state records for any device in zimra_configs that is missing one.
     * Fixes devices registered after the initial create_device_state_table migration.
     */
    public function up(): void
    {
        $deviceIds = DB::table('zimra_configs')
            ->whereNotNull('device_id')
            ->pluck('device_id');

        foreach ($deviceIds as $deviceId) {
            if (DB::table('device_state')->where('device_id', $deviceId)->exists()) {
                continue;
            }

            $maxGlobalNo = DB::table('receipts')
                ->where('device_id', $deviceId)
                ->max('receipt_global_no') ?? 0;

            $currentFiscalDay = DB::table('fiscal_days')
                ->where('device_id', $deviceId)
                ->where('status', 'open')
                ->orderBy('fiscal_day_no', 'desc')
                ->first();

            $lastFiscalDayNo = $currentFiscalDay ? $currentFiscalDay->fiscal_day_no : 0;

            $maxReceiptCounter = 0;
            if ($lastFiscalDayNo > 0) {
                $maxReceiptCounter = DB::table('receipts')
                    ->where('device_id', $deviceId)
                    ->where('fiscal_day_no', $lastFiscalDayNo)
                    ->max('receipt_counter') ?? 0;
            }

            DB::table('device_state')->insert([
                'device_id'              => $deviceId,
                'last_receipt_global_no' => $maxGlobalNo,
                'last_receipt_counter'   => $maxReceiptCounter,
                'last_fiscal_day_no'     => $lastFiscalDayNo,
                'requires_reconciliation' => false,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            echo "✓ Created device_state for device {$deviceId}: Global #{$maxGlobalNo}, Day {$lastFiscalDayNo}, Counter {$maxReceiptCounter}\n";
        }
    }

    public function down(): void
    {
        // No rollback — do not delete device_state records that may have grown
    }
};
