<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check for receipts with RED errors
$failedReceipts = DB::table('receipts')
    ->where('device_id', 32857)
    ->where('has_red_errors', true)
    ->get();

echo "Found " . $failedReceipts->count() . " receipts with RED errors:\n\n";

foreach ($failedReceipts as $receipt) {
    echo "ID: {$receipt->id}, Invoice: {$receipt->invoice_no}, ";
    echo "Counter: {$receipt->receipt_counter}, Global: {$receipt->receipt_global_no}, ";
    echo "Total: {$receipt->receipt_total}, Validation: {$receipt->validation_code}\n";
}

if ($failedReceipts->count() > 0) {
    echo "\nDo you want to delete these failed receipts? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    
    if (trim($line) === 'yes') {
        $deleted = DB::table('receipts')
            ->where('device_id', 32857)
            ->where('has_red_errors', true)
            ->delete();
        
        echo "\nDeleted {$deleted} failed receipts.\n";
        echo "You can now close the fiscal day without errors.\n";
    } else {
        echo "\nNo receipts deleted.\n";
    }
    
    fclose($handle);
} else {
    echo "\nNo failed receipts to clean up. You can close the fiscal day.\n";
}
