<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Analyzing Counter Mismatch Issue ===\n\n";

// Get all receipts with their global numbers
$allReceipts = DB::table('receipts')
    ->orderBy('receipt_global_no')
    ->get(['id', 'receipt_global_no', 'fiscal_day_no', 'receipt_counter', 'is_valid', 'invoice_no']);

echo "All Receipts (Global Number Sequence):\n";
echo str_repeat("-", 120) . "\n";
printf("%-10s | %-12s | %-12s | %-15s | %-10s | %-20s\n", 
    "Global No", "Fiscal Day", "Counter", "Is Valid", "ID", "Invoice");
echo str_repeat("-", 120) . "\n";

$lastGlobal = 0;
$gaps = [];

foreach ($allReceipts as $r) {
    // Check for gaps
    if ($r->receipt_global_no != $lastGlobal + 1 && $lastGlobal > 0) {
        for ($i = $lastGlobal + 1; $i < $r->receipt_global_no; $i++) {
            $gaps[] = $i;
            printf("%-10s | %-12s | %-12s | %-15s | %-10s | %-20s\n",
                $i, "MISSING", "-", "-", "-", "GAP IN SEQUENCE");
        }
    }
    
    printf("%-10s | %-12s | %-12s | %-15s | %-10s | %-20s\n",
        $r->receipt_global_no,
        $r->fiscal_day_no,
        $r->receipt_counter,
        $r->is_valid,
        $r->id,
        $r->invoice_no
    );
    
    $lastGlobal = $r->receipt_global_no;
}

echo str_repeat("-", 120) . "\n\n";

if (count($gaps) > 0) {
    echo "⚠️  GAPS IN SEQUENCE: " . implode(', ', $gaps) . "\n\n";
}

// Check what ZIMRA expects
echo "=== ZIMRA Expectations ===\n";
$maxGlobal = DB::table('receipts')->max('receipt_global_no');
$totalReceipts = DB::table('receipts')->count();

echo "Max global number: {$maxGlobal}\n";
echo "Total receipts: {$totalReceipts}\n";
echo "Expected receipts (1 to {$maxGlobal}): {$maxGlobal}\n";

if ($maxGlobal != $totalReceipts) {
    echo "⚠️  MISMATCH: Expected {$maxGlobal} receipts but have {$totalReceipts}\n";
    echo "Missing: " . ($maxGlobal - $totalReceipts) . " receipt(s)\n\n";
}

// Check for duplicate global numbers
echo "\n=== Checking for Duplicates ===\n";
$duplicates = DB::select("
    SELECT receipt_global_no, COUNT(*) as count
    FROM receipts
    GROUP BY receipt_global_no
    HAVING COUNT(*) > 1
");

if (count($duplicates) > 0) {
    echo "⚠️  DUPLICATE GLOBAL NUMBERS FOUND:\n";
    foreach ($duplicates as $dup) {
        echo "  Global #{$dup->receipt_global_no}: {$dup->count} receipts\n";
    }
} else {
    echo "✓ No duplicate global numbers\n";
}

// Analyze fiscal day counters
echo "\n=== Fiscal Day Counter Analysis ===\n";
$fiscalDays = DB::table('fiscal_days')
    ->where('device_id', 32558)
    ->orderBy('fiscal_day_no')
    ->get();

foreach ($fiscalDays as $fd) {
    echo "\nFiscal Day {$fd->fiscal_day_no} ({$fd->status}):\n";
    
    $receipts = DB::table('receipts')
        ->where('fiscal_day_no', $fd->fiscal_day_no)
        ->where('device_id', 32558)
        ->get();
    
    $validReceipts = $receipts->where('is_valid', true);
    
    echo "  Total receipts: {$receipts->count()}\n";
    echo "  Valid receipts: {$validReceipts->count()}\n";
    echo "  Stored counter: {$fd->receipt_counter}\n";
    
    if ($validReceipts->count() > 0) {
        $maxCounter = $validReceipts->max('receipt_counter');
        echo "  Max receipt counter: {$maxCounter}\n";
        
        if ($fd->receipt_counter != $maxCounter) {
            echo "  ⚠️  MISMATCH: Stored counter ({$fd->receipt_counter}) != Max counter ({$maxCounter})\n";
        }
    }
}

echo "\n=== Recommendation ===\n";
echo "The CountersMismatch error means ZIMRA's validation is failing.\n";
echo "This could be because:\n";
echo "1. Receipt sequence has gaps (missing global numbers)\n";
echo "2. Fiscal day counters don't match what was submitted to ZIMRA\n";
echo "3. Previous fiscal days were closed with different data than what's in the database\n";
