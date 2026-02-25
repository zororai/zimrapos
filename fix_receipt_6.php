<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Fixing Receipt #6 ===\n\n";

$receipt6 = DB::table('receipts')->where('receipt_global_no', 6)->first();

if (!$receipt6) {
    die("Receipt #6 not found\n");
}

echo "Current state:\n";
echo "  ID: {$receipt6->id}\n";
echo "  Fiscal Day: {$receipt6->fiscal_day_no}\n";
echo "  Is Valid: {$receipt6->is_valid}\n";
echo "  Invoice: {$receipt6->invoice_no}\n\n";

// Mark as invalid since it was never submitted to ZIMRA
echo "Setting is_valid = 0 (not submitted to ZIMRA)...\n";

DB::table('receipts')
    ->where('id', $receipt6->id)
    ->update(['is_valid' => 0]);

echo "✓ Receipt #6 marked as invalid\n\n";

// Verify
$updated = DB::table('receipts')->where('receipt_global_no', 6)->first();
echo "Updated state:\n";
echo "  ID: {$updated->id}\n";
echo "  Is Valid: {$updated->is_valid}\n\n";

// Check fiscal day 1 valid receipts
$fd1ValidCount = DB::table('receipts')
    ->where('fiscal_day_no', 1)
    ->where('is_valid', true)
    ->count();

echo "Fiscal Day 1 valid receipts: {$fd1ValidCount}\n";

// Check total receipts
$totalReceipts = DB::table('receipts')->count();
$validReceipts = DB::table('receipts')->where('is_valid', true)->count();

echo "Total receipts: {$totalReceipts}\n";
echo "Valid receipts: {$validReceipts}\n\n";

echo "✓ Fix applied. Retry closing fiscal day 11.\n";
