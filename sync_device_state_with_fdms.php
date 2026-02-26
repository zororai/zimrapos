<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$deviceId = 32857;

// Get FDMS status
$zimraService = app(\App\Services\ZimraDeviceService::class);
$fdmsStatus = $zimraService->getStatus($deviceId);

echo "FDMS Status:\n";
echo "  Last Fiscal Day No: {$fdmsStatus['lastFiscalDayNo']}\n";
echo "  Last Receipt Global No: {$fdmsStatus['lastReceiptGlobalNo']}\n";
echo "  Fiscal Day Status: {$fdmsStatus['fiscalDayStatus']}\n";

// Update device_state to match FDMS
DB::table('device_state')
    ->where('device_id', $deviceId)
    ->update([
        'last_fiscal_day_no' => $fdmsStatus['lastFiscalDayNo'],
        'last_receipt_global_no' => $fdmsStatus['lastReceiptGlobalNo'],
        'last_receipt_counter' => 1, // Day 3 has 1 receipt
        'updated_at' => now(),
    ]);

echo "\nUpdated device_state to match FDMS:\n";
echo "  last_fiscal_day_no: {$fdmsStatus['lastFiscalDayNo']}\n";
echo "  last_receipt_global_no: {$fdmsStatus['lastReceiptGlobalNo']}\n";
echo "  last_receipt_counter: 1\n";

// Update fiscal_days table
DB::table('fiscal_days')
    ->where('device_id', $deviceId)
    ->where('fiscal_day_no', 3)
    ->update(['status' => 'open']);

DB::table('fiscal_days')
    ->where('device_id', $deviceId)
    ->where('fiscal_day_no', '<', 3)
    ->update(['status' => 'closed']);

echo "\nUpdated fiscal_days:\n";
echo "  Day #3: open\n";
echo "  Days #1-2: closed\n";

echo "\n✅ Sync complete. System is now aligned with FDMS.\n";
