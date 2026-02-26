<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$deviceId = 32857;

echo "=== Testing CloseDay Calculation for Day 4 ===\n\n";

// Get receipts for Day 4
$receipts = DB::table('receipts')
    ->where('device_id', $deviceId)
    ->where('fiscal_day_no', 4)
    ->where('is_valid', true)
    ->get();

echo "Fiscal Day 4 Receipts:\n";
echo "  Count: " . $receipts->count() . "\n";

$totalSales = 0;
$lastCounter = 0;

foreach ($receipts as $r) {
    echo "  Global #{$r->receipt_global_no}: Counter {$r->receipt_counter}, Total: \${$r->receipt_total}\n";
    $totalSales += $r->receipt_total;
    if ($r->receipt_counter > $lastCounter) {
        $lastCounter = $r->receipt_counter;
    }
}

echo "\nCalculated Payload:\n";
echo "  receiptCounter: {$lastCounter}\n";
echo "  SaleByTax (0%): \${$totalSales}\n";

echo "\n=== FDMS Status ===\n";
$zimraService = app(\App\Services\ZimraDeviceService::class);
$fdmsStatus = $zimraService->getStatus($deviceId);

echo "  Fiscal Day: {$fdmsStatus['lastFiscalDayNo']}\n";
echo "  Status: {$fdmsStatus['fiscalDayStatus']}\n";
echo "  Last Global No: {$fdmsStatus['lastReceiptGlobalNo']}\n";

if ($fdmsStatus['fiscalDayStatus'] === 'FiscalDayCloseFailed') {
    echo "\n⚠️  Day {$fdmsStatus['lastFiscalDayNo']} has FiscalDayCloseFailed status\n";
    echo "  Error: {$fdmsStatus['fiscalDayClosingErrorCode']}\n";
    echo "\nFDMS likely has different totals than what we calculated.\n";
    echo "This could be because:\n";
    echo "  1. FDMS has receipts we don't have in local DB\n";
    echo "  2. FDMS has different amounts for the same receipts\n";
    echo "  3. Previous failed receipts are still counted by FDMS\n";
}

echo "\n=== Recommendation ===\n";
echo "All receipts from debug files have been recreated.\n";
echo "Missing: Global #1-5 (no debug files exist)\n";
echo "\nOptions:\n";
echo "  1. Contact ZIMRA support to reset device 32857\n";
echo "  2. Switch to device 32558 (clean slate)\n";
echo "  3. Accept that Day 1-3 cannot be closed, focus on Day 4+\n";
