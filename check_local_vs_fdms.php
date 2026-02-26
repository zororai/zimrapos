<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Local Database State:\n";
echo "====================\n\n";

// Check fiscal days
$fiscalDays = DB::table('fiscal_days')
    ->where('device_id', 32857)
    ->orderBy('fiscal_day_no')
    ->get();

echo "Fiscal Days:\n";
foreach ($fiscalDays as $day) {
    echo "  Day #{$day->fiscal_day_no}: {$day->status}, opened: {$day->opened_at}\n";
}

// Check device state
$deviceState = DB::table('device_state')
    ->where('device_id', 32857)
    ->first();

echo "\nDevice State:\n";
echo "  Last Fiscal Day No: {$deviceState->last_fiscal_day_no}\n";
echo "  Last Receipt Counter: {$deviceState->last_receipt_counter}\n";
echo "  Last Receipt Global No: {$deviceState->last_receipt_global_no}\n";

// Check receipts for day 3
$receiptsDay3 = DB::table('receipts')
    ->where('device_id', 32857)
    ->where('fiscal_day_no', 3)
    ->orderBy('receipt_counter')
    ->get();

echo "\nReceipts for Fiscal Day 3:\n";
if ($receiptsDay3->count() > 0) {
    foreach ($receiptsDay3 as $r) {
        echo "  ID: {$r->id}, Counter: {$r->receipt_counter}, Global: {$r->receipt_global_no}, ";
        echo "Invoice: {$r->invoice_no}, Total: {$r->receipt_total}, Valid: " . ($r->is_valid ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "  No receipts found\n";
}

// Check all receipts
$allReceipts = DB::table('receipts')
    ->where('device_id', 32857)
    ->orderBy('receipt_global_no')
    ->get();

echo "\nAll Receipts (by global no):\n";
foreach ($allReceipts as $r) {
    echo "  Global #{$r->receipt_global_no}: Day {$r->fiscal_day_no}, Counter {$r->receipt_counter}, ";
    echo "Invoice: {$r->invoice_no}, Valid: " . ($r->is_valid ? 'YES' : 'NO') . "\n";
}

echo "\n\nFDMS Status:\n";
echo "============\n";
echo "Last Fiscal Day No: 3\n";
echo "Last Receipt Global No: 13\n";
echo "Fiscal Day Status: FiscalDayCloseFailed\n";
echo "Error: CountersMismatch\n";
