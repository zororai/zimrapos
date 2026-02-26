<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check receipt 85
$receipt = DB::table('receipts')->where('id', 85)->first();

if ($receipt) {
    echo "Receipt #85 Details:\n";
    echo "==================\n";
    echo "Invoice No: {$receipt->invoice_no}\n";
    echo "Receipt Counter: {$receipt->receipt_counter}\n";
    echo "Receipt Global No: {$receipt->receipt_global_no}\n";
    echo "Receipt Total: {$receipt->receipt_total}\n";
    echo "Receipt Type: {$receipt->receipt_type}\n";
    echo "Validation Code: {$receipt->validation_code}\n";
    echo "Has RED Errors: " . ($receipt->has_red_errors ? 'YES' : 'NO') . "\n";
    echo "Has GRAY Errors: " . ($receipt->has_gray_errors ? 'YES' : 'NO') . "\n";
    echo "Is Valid: " . ($receipt->is_valid ? 'YES' : 'NO') . "\n";
    echo "FDMS Receipt ID: {$receipt->fdms_receipt_id}\n";
    echo "\nValidation Errors:\n";
    if ($receipt->validation_errors) {
        $errors = json_decode($receipt->validation_errors, true);
        print_r($errors);
    } else {
        echo "None\n";
    }
} else {
    echo "Receipt #85 not found\n";
}

// Check all receipts for fiscal day 2
echo "\n\nAll receipts for Fiscal Day 2:\n";
echo "==============================\n";
$receipts = DB::table('receipts')
    ->where('device_id', 32857)
    ->where('fiscal_day_no', 2)
    ->orderBy('receipt_counter')
    ->get();

foreach ($receipts as $r) {
    echo "ID: {$r->id}, Counter: {$r->receipt_counter}, Global: {$r->receipt_global_no}, ";
    echo "Invoice: {$r->invoice_no}, Total: {$r->receipt_total}, ";
    echo "Valid: " . ($r->is_valid ? 'YES' : 'NO') . ", ";
    echo "RED: " . ($r->has_red_errors ? 'YES' : 'NO') . ", ";
    echo "GRAY: " . ($r->has_gray_errors ? 'YES' : 'NO') . "\n";
}
