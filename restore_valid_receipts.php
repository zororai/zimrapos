<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Restoring Valid Receipts ===\n\n";

// Get fiscal days that are closed
$closedDays = DB::table('fiscal_days')
    ->where('device_id', 32558)
    ->where('status', 'closed')
    ->where('receipt_counter', '>', 0)
    ->get();

echo "Closed fiscal days with receipts:\n";
foreach ($closedDays as $fd) {
    echo "  Day {$fd->fiscal_day_no}: Stored counter = {$fd->receipt_counter}\n";
}

echo "\n";

// For each closed fiscal day, mark receipts as valid up to the stored counter
foreach ($closedDays as $fd) {
    echo "Processing Fiscal Day {$fd->fiscal_day_no}...\n";
    
    $receipts = DB::table('receipts')
        ->where('fiscal_day_no', $fd->fiscal_day_no)
        ->where('device_id', 32558)
        ->orderBy('receipt_counter')
        ->get();
    
    $currentValid = $receipts->where('is_valid', true)->count();
    echo "  Current valid receipts: {$currentValid}\n";
    echo "  Expected (from stored counter): {$fd->receipt_counter}\n";
    
    if ($currentValid != $fd->receipt_counter) {
        echo "  ⚠️  MISMATCH - Fixing...\n";
        
        // Mark receipts as valid up to the stored counter
        $updated = DB::table('receipts')
            ->where('fiscal_day_no', $fd->fiscal_day_no)
            ->where('device_id', 32558)
            ->where('receipt_counter', '<=', $fd->receipt_counter)
            ->update(['is_valid' => 1]);
        
        echo "  ✓ Updated {$updated} receipts to is_valid=1\n";
    } else {
        echo "  ✓ Already correct\n";
    }
    
    echo "\n";
}

echo "=== Verification ===\n";
$totalValid = DB::table('receipts')->where('is_valid', 1)->count();
echo "Total valid receipts: {$totalValid}\n";

echo "\n✓ Receipts restored. Retry closing fiscal day 11.\n";
