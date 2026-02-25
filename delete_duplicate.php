<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Deleting Duplicate Receipt ===\n\n";

$receipt = DB::table('receipts')->where('id', 72)->first();

if ($receipt) {
    echo "Found receipt to delete:\n";
    echo "  ID: {$receipt->id}\n";
    echo "  Invoice: {$receipt->invoice_no}\n";
    echo "  Global No: {$receipt->receipt_global_no}\n";
    echo "  Hash: " . ($receipt->receipt_hash ?: 'EMPTY') . "\n\n";
    
    DB::table('receipts')->where('id', 72)->delete();
    
    echo "✓ Receipt ID 72 deleted successfully\n\n";
    
    $remaining = DB::table('receipts')->where('fiscal_day_no', 11)->count();
    echo "Remaining receipts for fiscal day 11: {$remaining}\n";
} else {
    echo "Receipt ID 72 not found\n";
}
