<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Receipts for Fiscal Day 11 ===\n\n";

$receipts = DB::table('receipts')
    ->where('fiscal_day_no', 11)
    ->orderBy('receipt_counter')
    ->get(['id', 'invoice_no', 'receipt_counter', 'receipt_global_no', 'fiscal_day_no', 'created_at']);

echo "Total receipts found: " . $receipts->count() . "\n\n";

if ($receipts->count() > 0) {
    echo "Receipt Details:\n";
    echo str_repeat("-", 100) . "\n";
    printf("%-5s | %-15s | %-8s | %-10s | %-20s\n", "ID", "Invoice No", "Counter", "Global No", "Created At");
    echo str_repeat("-", 100) . "\n";
    
    foreach ($receipts as $receipt) {
        printf("%-5s | %-15s | %-8s | %-10s | %-20s\n", 
            $receipt->id,
            $receipt->invoice_no,
            $receipt->receipt_counter,
            $receipt->receipt_global_no,
            $receipt->created_at
        );
    }
    
    echo str_repeat("-", 100) . "\n\n";
    
    echo "Summary:\n";
    echo "- First receipt counter: " . $receipts->min('receipt_counter') . "\n";
    echo "- Last receipt counter: " . $receipts->max('receipt_counter') . "\n";
    echo "- First global no: " . $receipts->min('receipt_global_no') . "\n";
    echo "- Last global no: " . $receipts->max('receipt_global_no') . "\n";
    echo "- Expected last global no (from ZIMRA): 61\n";
    echo "- Counter range: " . ($receipts->max('receipt_counter') - $receipts->min('receipt_counter') + 1) . "\n";
    echo "- Actual count: " . $receipts->count() . "\n";
    
    $expectedCount = $receipts->max('receipt_counter') - $receipts->min('receipt_counter') + 1;
    if ($expectedCount != $receipts->count()) {
        echo "\n⚠️  WARNING: Missing receipts detected!\n";
        echo "   Expected " . $expectedCount . " receipts but found " . $receipts->count() . "\n";
    }
    
    if ($receipts->max('receipt_global_no') != 61) {
        echo "\n⚠️  WARNING: Global number mismatch!\n";
        echo "   ZIMRA expects last global no: 61\n";
        echo "   Database has last global no: " . $receipts->max('receipt_global_no') . "\n";
    }
} else {
    echo "No receipts found for fiscal day 11\n";
}
