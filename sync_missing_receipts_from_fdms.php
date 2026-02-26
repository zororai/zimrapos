<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$deviceId = 32857;

echo "Checking for missing receipts in local database...\n\n";

// Get FDMS status
$zimraService = app(\App\Services\ZimraDeviceService::class);
$fdmsStatus = $zimraService->getStatus($deviceId);

$fdmsLastGlobalNo = $fdmsStatus['lastReceiptGlobalNo'];
echo "FDMS Last Receipt Global No: {$fdmsLastGlobalNo}\n";

// Get local receipts
$localReceipts = DB::table('receipts')
    ->where('device_id', $deviceId)
    ->orderBy('receipt_global_no')
    ->pluck('receipt_global_no')
    ->toArray();

echo "Local Receipt Global Numbers: " . implode(', ', $localReceipts) . "\n\n";

// Find missing global numbers
$missingGlobalNos = [];
for ($i = 1; $i <= $fdmsLastGlobalNo; $i++) {
    if (!in_array($i, $localReceipts)) {
        $missingGlobalNos[] = $i;
    }
}

if (empty($missingGlobalNos)) {
    echo "✅ No missing receipts. Local database is in sync with FDMS.\n";
    exit(0);
}

echo "❌ Missing Receipt Global Numbers: " . implode(', ', $missingGlobalNos) . "\n\n";
echo "These receipts exist in FDMS but not in local database.\n";
echo "They were likely deleted locally but remain in FDMS permanently.\n\n";

echo "Creating placeholder records for missing receipts...\n\n";

// For each missing receipt, we need to determine which fiscal day it belongs to
// We'll use the existing receipts as reference
$existingReceipts = DB::table('receipts')
    ->where('device_id', $deviceId)
    ->orderBy('receipt_global_no')
    ->get();

foreach ($missingGlobalNos as $globalNo) {
    // Determine fiscal day based on surrounding receipts
    $fiscalDayNo = 2; // Default to day 2 (where most missing receipts are)
    
    // Find the closest existing receipt to determine fiscal day
    $closestReceipt = null;
    $minDiff = PHP_INT_MAX;
    
    foreach ($existingReceipts as $receipt) {
        $diff = abs($receipt->receipt_global_no - $globalNo);
        if ($diff < $minDiff) {
            $minDiff = $diff;
            $closestReceipt = $receipt;
        }
    }
    
    if ($closestReceipt) {
        $fiscalDayNo = $closestReceipt->fiscal_day_no;
    }
    
    // Determine receipt counter (sequential within fiscal day)
    $maxCounterInDay = DB::table('receipts')
        ->where('device_id', $deviceId)
        ->where('fiscal_day_no', $fiscalDayNo)
        ->max('receipt_counter') ?? 0;
    
    $receiptCounter = $maxCounterInDay + 1;
    
    // Create placeholder receipt
    DB::table('receipts')->insert([
        'device_id' => $deviceId,
        'invoice_no' => "MISSING-{$globalNo}",
        'receipt_type' => 'FiscalInvoice',
        'receipt_currency' => 'USD',
        'receipt_counter' => $receiptCounter,
        'receipt_global_no' => $globalNo,
        'fiscal_day_no' => $fiscalDayNo,
        'receipt_total' => 0.00,
        'receipt_date' => now(),
        'receipt_notes' => 'Placeholder for receipt deleted locally but exists in FDMS',
        'validation_code' => 'Red',
        'is_valid' => false,
        'has_red_errors' => true,
        'has_gray_errors' => false,
        'fdms_receipt_id' => "UNKNOWN-{$globalNo}",
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    echo "  ✓ Created placeholder for Global #{$globalNo} (Day {$fiscalDayNo}, Counter {$receiptCounter})\n";
}

echo "\n✅ Sync complete. Created " . count($missingGlobalNos) . " placeholder receipts.\n";
echo "\nNote: These placeholders have RED errors and will block closeDay.\n";
echo "You need to contact ZIMRA support to reset the device or manually close the fiscal day.\n";
