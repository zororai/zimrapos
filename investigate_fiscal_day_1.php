<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Investigating Fiscal Day 1 ===\n\n";

// Get all receipts for fiscal day 1
$fd1Receipts = DB::table('receipts')
    ->where('fiscal_day_no', 1)
    ->orderBy('receipt_counter')
    ->get(['id', 'receipt_counter', 'receipt_global_no', 'is_valid', 'invoice_no', 'created_at']);

echo "All receipts in Fiscal Day 1:\n";
echo str_repeat("-", 100) . "\n";
printf("%-5s | %-10s | %-12s | %-10s | %-15s | %-20s\n", 
    "ID", "Counter", "Global No", "Valid", "Invoice", "Created");
echo str_repeat("-", 100) . "\n";

foreach ($fd1Receipts as $r) {
    printf("%-5s | %-10s | %-12s | %-10s | %-15s | %-20s\n",
        $r->id,
        $r->receipt_counter,
        $r->receipt_global_no,
        $r->is_valid,
        $r->invoice_no,
        $r->created_at
    );
}

echo str_repeat("-", 100) . "\n\n";

// Check fiscal_days table
$fd1 = DB::table('fiscal_days')
    ->where('fiscal_day_no', 1)
    ->where('device_id', 32558)
    ->first();

echo "Fiscal Day 1 record:\n";
echo "  Stored receipt_counter: {$fd1->receipt_counter}\n";
echo "  Status: {$fd1->status}\n";
echo "  Opened at: {$fd1->opened_at}\n\n";

// Find missing counters
$existingCounters = $fd1Receipts->pluck('receipt_counter')->toArray();
$expectedCounters = range(1, $fd1->receipt_counter);
$missingCounters = array_diff($expectedCounters, $existingCounters);

if (count($missingCounters) > 0) {
    echo "⚠️  MISSING COUNTERS: " . implode(', ', $missingCounters) . "\n\n";
    
    // Check if these receipts exist in other fiscal days
    foreach ($missingCounters as $counter) {
        echo "Searching for receipt with counter {$counter} in other fiscal days...\n";
        $found = DB::table('receipts')
            ->where('receipt_counter', $counter)
            ->where('device_id', 32558)
            ->get(['id', 'fiscal_day_no', 'receipt_global_no', 'invoice_no']);
        
        if ($found->count() > 0) {
            foreach ($found as $r) {
                echo "  Found: ID {$r->id}, Fiscal Day {$r->fiscal_day_no}, Global {$r->receipt_global_no}, Invoice {$r->invoice_no}\n";
            }
        } else {
            echo "  Not found anywhere\n";
        }
    }
}

echo "\n=== Analysis ===\n";
echo "Fiscal day 1 was closed with receipt_counter=9\n";
echo "This means ZIMRA expects 9 receipts were issued in fiscal day 1\n";
echo "But the database only has receipts with counters: " . implode(', ', $existingCounters) . "\n\n";

echo "The missing counters (8, 9) might have been:\n";
echo "1. Deleted from the database\n";
echo "2. Moved to a different fiscal day\n";
echo "3. Never created (database corruption)\n\n";

echo "ZIMRA's validation is failing because the historical data doesn't match.\n";
