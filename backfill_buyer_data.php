<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== BACKFILLING BUYER DATA FOR EXISTING CREDIT/DEBIT NOTES ===\n\n";

// Get all credit notes with NULL buyer_data
$creditNotes = \App\Models\Receipt::where('receipt_type', 'CreditNote')
    ->whereNull('buyer_data')
    ->get();

echo "Found " . $creditNotes->count() . " credit notes with NULL buyer_data\n\n";

$updatedCount = 0;
$skippedCount = 0;

foreach ($creditNotes as $creditNote) {
    $originalReceipt = \App\Models\Receipt::find($creditNote->original_receipt_id);
    
    if (!$originalReceipt) {
        echo "⚠️  Credit Note ID {$creditNote->id} - Original receipt not found (ID: {$creditNote->original_receipt_id})\n";
        $skippedCount++;
        continue;
    }
    
    if (!$originalReceipt->buyer_data || empty($originalReceipt->buyer_data)) {
        echo "⚠️  Credit Note ID {$creditNote->id} - Original receipt has no buyer_data\n";
        $skippedCount++;
        continue;
    }
    
    // Copy buyer_data from original receipt
    // Use updateQuietly to bypass model events (fiscal day validation)
    try {
        $creditNote->updateQuietly([
            'buyer_data' => $originalReceipt->buyer_data
        ]);
        
        $buyerName = $originalReceipt->buyer_data['buyerRegisterName'] ?? 'Unknown';
        echo "✅ Credit Note ID {$creditNote->id} ({$creditNote->invoice_no}) - Copied buyer_data from original receipt (Buyer: {$buyerName})\n";
        $updatedCount++;
    } catch (\Exception $e) {
        echo "❌ Credit Note ID {$creditNote->id} - Error: {$e->getMessage()}\n";
        $skippedCount++;
    }
}

echo "\n";

// Get all debit notes with NULL buyer_data
$debitNotes = \App\Models\Receipt::where('receipt_type', 'DebitNote')
    ->whereNull('buyer_data')
    ->get();

echo "Found " . $debitNotes->count() . " debit notes with NULL buyer_data\n\n";

foreach ($debitNotes as $debitNote) {
    $originalReceipt = null;
    
    // Try to find original receipt by original_receipt_id first
    if ($debitNote->original_receipt_id) {
        $originalReceipt = \App\Models\Receipt::find($debitNote->original_receipt_id);
    }
    
    // If not found, try to find by creditDebitNote reference in zimra_response
    if (!$originalReceipt && $debitNote->zimra_response) {
        $zimraResponse = is_string($debitNote->zimra_response) 
            ? json_decode($debitNote->zimra_response, true) 
            : $debitNote->zimra_response;
        
        if (isset($zimraResponse['receiptGlobalNo'])) {
            $originalReceipt = \App\Models\Receipt::where('receipt_global_no', $zimraResponse['receiptGlobalNo'])
                ->where('device_id', $debitNote->device_id)
                ->first();
        }
    }
    
    if (!$originalReceipt) {
        echo "⚠️  Debit Note ID {$debitNote->id} - Original receipt not found\n";
        $skippedCount++;
        continue;
    }
    
    if (!$originalReceipt->buyer_data || empty($originalReceipt->buyer_data)) {
        echo "⚠️  Debit Note ID {$debitNote->id} - Original receipt has no buyer_data\n";
        $skippedCount++;
        continue;
    }
    
    // Copy buyer_data from original receipt
    // Use updateQuietly to bypass model events (fiscal day validation)
    try {
        $debitNote->updateQuietly([
            'buyer_data' => $originalReceipt->buyer_data
        ]);
        
        $buyerName = $originalReceipt->buyer_data['buyerRegisterName'] ?? 'Unknown';
        echo "✅ Debit Note ID {$debitNote->id} ({$debitNote->invoice_no}) - Copied buyer_data from original receipt (Buyer: {$buyerName})\n";
        $updatedCount++;
    } catch (\Exception $e) {
        echo "❌ Debit Note ID {$debitNote->id} - Error: {$e->getMessage()}\n";
        $skippedCount++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total updated: {$updatedCount}\n";
echo "Total skipped: {$skippedCount}\n";
echo "\nBackfill complete!\n";
