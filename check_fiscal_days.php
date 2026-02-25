<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Fiscal Day Analysis ===\n\n";

// Check fiscal_days table
$fiscalDays = DB::table('fiscal_days')->orderBy('fiscal_day_no')->get();

echo "Fiscal Days in Database:\n";
echo str_repeat("-", 100) . "\n";
printf("%-8s | %-12s | %-15s | %-15s | %-20s\n", 
    "Day No", "Status", "Receipt Counter", "Device ID", "Opened At");
echo str_repeat("-", 100) . "\n";

foreach ($fiscalDays as $fd) {
    printf("%-8s | %-12s | %-15s | %-15s | %-20s\n",
        $fd->fiscal_day_no,
        $fd->status,
        $fd->receipt_counter ?? 'N/A',
        $fd->device_id,
        $fd->opened_at ?? 'N/A'
    );
}

echo str_repeat("-", 100) . "\n\n";

// Check receipts per fiscal day
echo "Receipts per Fiscal Day:\n";
echo str_repeat("-", 100) . "\n";

$receiptsByDay = DB::table('receipts')
    ->select('fiscal_day_no', 
        DB::raw('COUNT(*) as count'),
        DB::raw('MAX(receipt_counter) as max_counter'),
        DB::raw('SUM(receipt_total) as total_value'),
        DB::raw('SUM(CASE WHEN is_valid = 1 THEN 1 ELSE 0 END) as valid_count')
    )
    ->groupBy('fiscal_day_no')
    ->orderBy('fiscal_day_no')
    ->get();

printf("%-8s | %-10s | %-12s | %-15s | %-12s\n", 
    "Day No", "Receipts", "Max Counter", "Total Value", "Valid Count");
echo str_repeat("-", 100) . "\n";

foreach ($receiptsByDay as $day) {
    printf("%-8s | %-10s | %-12s | %-15s | %-12s\n",
        $day->fiscal_day_no,
        $day->count,
        $day->max_counter,
        number_format($day->total_value, 2),
        $day->valid_count
    );
}

echo str_repeat("-", 100) . "\n\n";

// Check the newly inserted receipt #6
echo "=== Receipt #6 Details ===\n";
$receipt6 = DB::table('receipts')->where('receipt_global_no', 6)->first();
if ($receipt6) {
    echo "ID: {$receipt6->id}\n";
    echo "Fiscal Day: {$receipt6->fiscal_day_no}\n";
    echo "Receipt Counter: {$receipt6->receipt_counter}\n";
    echo "Global No: {$receipt6->receipt_global_no}\n";
    echo "Is Valid: {$receipt6->is_valid}\n";
    echo "Invoice: {$receipt6->invoice_no}\n";
    echo "Total: {$receipt6->receipt_total}\n";
}

echo "\n=== Problem Analysis ===\n";
echo "Receipt #6 was inserted into fiscal day 1.\n";
echo "If fiscal day 1 is already CLOSED, this new receipt breaks ZIMRA validation.\n";
echo "ZIMRA expects fiscal day counters to match what was submitted at close time.\n\n";

// Check if fiscal day 1 is closed
$fd1 = DB::table('fiscal_days')->where('fiscal_day_no', 1)->first();
if ($fd1) {
    echo "Fiscal Day 1 Status: {$fd1->status}\n";
    if ($fd1->status === 'closed') {
        echo "⚠️  PROBLEM: Fiscal day 1 is CLOSED but we added receipt #6 to it!\n";
        echo "This will cause CountersMismatch for all subsequent fiscal day closes.\n";
    }
}
