<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Analyzing Duplicate Receipt Issue ===\n\n";

$duplicates = DB::table('receipts')
    ->where('fiscal_day_no', 11)
    ->get();

echo "Full details of both receipts:\n\n";

foreach ($duplicates as $receipt) {
    echo "Receipt ID: {$receipt->id}\n";
    echo "  Invoice No: {$receipt->invoice_no}\n";
    echo "  Receipt Counter: {$receipt->receipt_counter}\n";
    echo "  Receipt Global No: {$receipt->receipt_global_no}\n";
    echo "  Fiscal Day No: {$receipt->fiscal_day_no}\n";
    echo "  Receipt Total: {$receipt->receipt_total}\n";
    echo "  Created At: {$receipt->created_at}\n";
    echo "  Updated At: {$receipt->updated_at}\n";
    echo "  Receipt Hash: {$receipt->receipt_hash}\n";
    echo "  Verification Code: {$receipt->verification_code}\n";
    echo str_repeat("-", 80) . "\n\n";
}

echo "\n=== Suggested Fix ===\n";
echo "Option 1: Delete the incorrect duplicate (ID 72 with global_no=1)\n";
echo "   Command: DB::table('receipts')->where('id', 72)->delete();\n\n";
echo "Option 2: Investigate why the duplicate was created and fix the root cause\n";
echo "   Check the receipt creation logic in ZimraDeviceService.php\n\n";
echo "⚠️  WARNING: Do not execute deletion without user confirmation!\n";
