<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Analyzing CloseDay CountersMismatch Issue ===\n\n";

// Get all receipts for fiscal day 11
$receipts = DB::table('receipts')
    ->where('fiscal_day_no', 11)
    ->orderBy('receipt_counter')
    ->get();

echo "Receipts for Fiscal Day 11:\n";
echo str_repeat("-", 120) . "\n";
printf("%-5s | %-15s | %-8s | %-10s | %-12s | %-10s | %-20s\n", 
    "ID", "Invoice", "Counter", "Global No", "Total", "Valid", "Created");
echo str_repeat("-", 120) . "\n";

foreach ($receipts as $r) {
    $isValid = DB::table('receipts')->where('id', $r->id)->value('is_valid');
    printf("%-5s | %-15s | %-8s | %-10s | %-12s | %-10s | %-20s\n",
        $r->id,
        $r->invoice_no,
        $r->receipt_counter,
        $r->receipt_global_no,
        $r->receipt_total,
        $isValid ?? 'NULL',
        $r->created_at
    );
}

echo str_repeat("-", 120) . "\n\n";

// Check if is_valid column exists
$columns = DB::select("SHOW COLUMNS FROM receipts LIKE 'is_valid'");
echo "is_valid column exists: " . (count($columns) > 0 ? "YES" : "NO") . "\n";

if (count($columns) > 0) {
    $validReceipts = DB::table('receipts')
        ->where('fiscal_day_no', 11)
        ->where('is_valid', true)
        ->count();
    
    echo "Valid receipts (is_valid=true): {$validReceipts}\n";
}

echo "\nAll receipts count: " . $receipts->count() . "\n";

// Check what ZIMRA expects
echo "\n=== ZIMRA Status ===\n";
echo "Last Receipt Global No (from ZIMRA): 61\n";
echo "Last Fiscal Day No (from ZIMRA): 11\n";
echo "Error: CountersMismatch\n\n";

echo "=== Analysis ===\n";
echo "The receiptCounter sent to ZIMRA: 1\n";
echo "This represents the NUMBER of receipts for the fiscal day.\n";
echo "ZIMRA might be expecting a different count based on global numbers.\n\n";

// Check if there are missing global numbers
echo "=== Checking for Missing Receipts ===\n";
$allGlobalNos = DB::table('receipts')
    ->where('fiscal_day_no', '<=', 11)
    ->orderBy('receipt_global_no')
    ->pluck('receipt_global_no')
    ->toArray();

echo "All global numbers up to fiscal day 11:\n";
echo implode(", ", $allGlobalNos) . "\n\n";

if (count($allGlobalNos) > 0) {
    $expectedCount = max($allGlobalNos);
    $actualCount = count($allGlobalNos);
    
    echo "Expected receipts (based on max global no {$expectedCount}): {$expectedCount}\n";
    echo "Actual receipts in database: {$actualCount}\n";
    
    if ($expectedCount != $actualCount) {
        echo "\n⚠️  MISSING RECEIPTS DETECTED!\n";
        echo "Missing " . ($expectedCount - $actualCount) . " receipt(s)\n\n";
        
        // Find missing numbers
        $missing = [];
        for ($i = 1; $i <= $expectedCount; $i++) {
            if (!in_array($i, $allGlobalNos)) {
                $missing[] = $i;
            }
        }
        
        if (count($missing) > 0) {
            echo "Missing global numbers: " . implode(", ", $missing) . "\n";
        }
    }
}
