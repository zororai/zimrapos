<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Full Receipt Audit ===\n\n";

// Get all receipts grouped by fiscal day
$receiptsByDay = DB::table('receipts')
    ->orderBy('fiscal_day_no')
    ->orderBy('receipt_global_no')
    ->get(['id', 'fiscal_day_no', 'invoice_no', 'receipt_counter', 'receipt_global_no', 'is_valid', 'created_at']);

echo "Total receipts in database: " . $receiptsByDay->count() . "\n\n";

// Group by fiscal day
$grouped = $receiptsByDay->groupBy('fiscal_day_no');

foreach ($grouped as $fiscalDay => $receipts) {
    echo "=== Fiscal Day {$fiscalDay} ===\n";
    echo "Receipt count: " . $receipts->count() . "\n";
    echo "Global numbers: " . $receipts->pluck('receipt_global_no')->implode(', ') . "\n";
    echo "Counter range: " . $receipts->min('receipt_counter') . " - " . $receipts->max('receipt_counter') . "\n";
    
    // Check for duplicates
    $globalNos = $receipts->pluck('receipt_global_no')->toArray();
    $duplicates = array_diff_assoc($globalNos, array_unique($globalNos));
    if (count($duplicates) > 0) {
        echo "⚠️  DUPLICATES: " . implode(', ', array_unique($duplicates)) . "\n";
    }
    
    echo "\n";
}

echo "\n=== Duplicate Global Numbers Detail ===\n";
$duplicateGlobalNos = DB::select("
    SELECT receipt_global_no, COUNT(*) as count, GROUP_CONCAT(id) as ids
    FROM receipts
    GROUP BY receipt_global_no
    HAVING COUNT(*) > 1
    ORDER BY receipt_global_no
");

foreach ($duplicateGlobalNos as $dup) {
    echo "Global No {$dup->receipt_global_no}: {$dup->count} receipts (IDs: {$dup->ids})\n";
    
    $details = DB::table('receipts')
        ->whereIn('id', explode(',', $dup->ids))
        ->get(['id', 'fiscal_day_no', 'invoice_no', 'receipt_counter', 'receipt_global_no', 'is_valid']);
    
    foreach ($details as $d) {
        echo "  - ID {$d->id}: Fiscal Day {$d->fiscal_day_no}, Invoice {$d->invoice_no}, Counter {$d->receipt_counter}, Valid: {$d->is_valid}\n";
    }
    echo "\n";
}

echo "\n=== Missing Global Numbers ===\n";
$maxGlobal = DB::table('receipts')->max('receipt_global_no');
$existingGlobals = DB::table('receipts')->pluck('receipt_global_no')->unique()->sort()->values()->toArray();

$missing = [];
for ($i = 1; $i <= $maxGlobal; $i++) {
    if (!in_array($i, $existingGlobals)) {
        $missing[] = $i;
    }
}

if (count($missing) > 0) {
    echo "Missing global numbers: " . implode(', ', $missing) . "\n";
} else {
    echo "No missing global numbers\n";
}

echo "\n=== Recommendation ===\n";
echo "The CountersMismatch error is caused by:\n";
echo "1. Duplicate global numbers in the database\n";
echo "2. Missing global number(s)\n";
echo "3. Data integrity issues from previous operations\n\n";
echo "You need to clean up the receipts table to have:\n";
echo "- Unique global numbers from 1 to N\n";
echo "- Correct fiscal_day_no assignments\n";
echo "- No duplicates\n";
