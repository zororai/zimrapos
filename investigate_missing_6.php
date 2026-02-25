<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Investigating Missing Receipt #6 ===\n\n";

// Check receipts around #6
echo "Receipts around #6:\n";
$around = DB::table('receipts')
    ->whereIn('receipt_global_no', [4, 5, 7, 8])
    ->orderBy('receipt_global_no')
    ->get(['id', 'receipt_global_no', 'fiscal_day_no', 'invoice_no', 'created_at', 'receipt_counter']);

foreach ($around as $r) {
    echo "  Global #{$r->receipt_global_no}: Created {$r->created_at}, Fiscal Day {$r->fiscal_day_no}, Counter {$r->receipt_counter}\n";
}

echo "\nTime gap analysis:\n";
$receipt5 = DB::table('receipts')->where('receipt_global_no', 5)->first();
$receipt7 = DB::table('receipts')->where('receipt_global_no', 7)->first();

if ($receipt5 && $receipt7) {
    echo "  Receipt #5 created: {$receipt5->created_at}\n";
    echo "  Receipt #7 created: {$receipt7->created_at}\n";
    
    $time5 = strtotime($receipt5->created_at);
    $time7 = strtotime($receipt7->created_at);
    $diff = $time7 - $time5;
    
    echo "  Time gap: " . abs($diff) . " seconds\n";
    
    if ($diff < 0) {
        echo "  ⚠️  WARNING: Receipt #7 was created BEFORE receipt #5!\n";
        echo "  This suggests the global_no assignment logic has issues.\n";
    }
}

echo "\n=== Possible Solutions ===\n\n";

echo "Option 1: Create a placeholder receipt #6\n";
echo "  - Create a dummy/void receipt with global_no=6\n";
echo "  - Set fiscal_day_no to match the sequence (likely day 1)\n";
echo "  - Mark it as voided or cancelled\n";
echo "  - Risk: ZIMRA may reject artificial receipts\n\n";

echo "Option 2: Check if receipt #6 was soft-deleted\n";
echo "  - Check if your receipts table has soft deletes (deleted_at column)\n";
echo "  - If found, restore it\n\n";

$hasSoftDeletes = DB::select("SHOW COLUMNS FROM receipts LIKE 'deleted_at'");
if (count($hasSoftDeletes) > 0) {
    echo "  ✓ receipts table HAS soft deletes column\n";
    $softDeleted = DB::table('receipts')
        ->where('receipt_global_no', 6)
        ->whereNotNull('deleted_at')
        ->first();
    
    if ($softDeleted) {
        echo "  ✓ FOUND soft-deleted receipt #6!\n";
        echo "    ID: {$softDeleted->id}\n";
        echo "    Invoice: {$softDeleted->invoice_no}\n";
        echo "    Deleted at: {$softDeleted->deleted_at}\n";
    } else {
        echo "  ✗ No soft-deleted receipt #6 found\n";
    }
} else {
    echo "  ✗ receipts table does NOT have soft deletes\n";
}

echo "\nOption 3: Contact ZIMRA Support\n";
echo "  - Explain that receipt #6 is missing from your local database\n";
echo "  - Ask if they can provide the receipt details\n";
echo "  - Request guidance on how to proceed\n\n";

echo "Option 4: Check fiscal day 1 receipts\n";
echo "  - Receipt #6 should logically be in fiscal day 1 (between #5 and #7)\n";
echo "  - Check if any fiscal day 1 receipt should have been #6\n\n";

$day1Receipts = DB::table('receipts')
    ->where('fiscal_day_no', 1)
    ->orderBy('receipt_counter')
    ->get(['id', 'receipt_global_no', 'receipt_counter', 'invoice_no', 'created_at']);

echo "Fiscal Day 1 receipts:\n";
foreach ($day1Receipts as $r) {
    echo "  Counter {$r->receipt_counter}: Global #{$r->receipt_global_no}, Invoice {$r->invoice_no}\n";
}

$expectedCounters = range(1, $day1Receipts->max('receipt_counter'));
$actualCounters = $day1Receipts->pluck('receipt_counter')->toArray();
$missingCounters = array_diff($expectedCounters, $actualCounters);

if (count($missingCounters) > 0) {
    echo "\n  ⚠️  Missing counters in fiscal day 1: " . implode(', ', $missingCounters) . "\n";
}
