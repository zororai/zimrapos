<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Detecting Duplicate Receipt Counters ===\n\n";

// Find duplicates
$duplicates = DB::select("
    SELECT device_id, fiscal_day_no, receipt_counter, COUNT(*) as total
    FROM receipts
    GROUP BY device_id, fiscal_day_no, receipt_counter
    HAVING COUNT(*) > 1
    ORDER BY fiscal_day_no, receipt_counter
");

if (empty($duplicates)) {
    echo "✓ No duplicate receipt counters found\n";
    exit(0);
}

echo "⚠️  DUPLICATE RECEIPT COUNTERS FOUND:\n\n";
echo str_repeat("-", 80) . "\n";
printf("%-12s | %-12s | %-15s | %-10s\n", 
    "Device ID", "Fiscal Day", "Counter", "Duplicates");
echo str_repeat("-", 80) . "\n";

foreach ($duplicates as $dup) {
    printf("%-12s | %-12s | %-15s | %-10s\n",
        $dup->device_id,
        $dup->fiscal_day_no,
        $dup->receipt_counter,
        $dup->total
    );
}

echo str_repeat("-", 80) . "\n\n";

// Show details for each duplicate
echo "=== Duplicate Details ===\n\n";

foreach ($duplicates as $dup) {
    echo "Fiscal Day {$dup->fiscal_day_no}, Counter {$dup->receipt_counter}:\n";
    
    $receipts = DB::table('receipts')
        ->where('device_id', $dup->device_id)
        ->where('fiscal_day_no', $dup->fiscal_day_no)
        ->where('receipt_counter', $dup->receipt_counter)
        ->orderBy('id')
        ->get(['id', 'receipt_global_no', 'invoice_no', 'created_at', 'is_valid']);
    
    foreach ($receipts as $r) {
        $keep = $r->id === $receipts->first()->id ? '✓ KEEP' : '✗ DELETE';
        echo "  ID {$r->id}: Global #{$r->receipt_global_no}, Invoice {$r->invoice_no}, " .
             "Created: {$r->created_at}, Valid: {$r->is_valid} [{$keep}]\n";
    }
    echo "\n";
}

echo "Total duplicate groups: " . count($duplicates) . "\n";
