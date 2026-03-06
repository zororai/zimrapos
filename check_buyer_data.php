<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECKING BUYER DATA STATUS ===\n\n";

// Check all credit notes
$creditNotes = \App\Models\Receipt::where('receipt_type', 'CreditNote')
    ->orderBy('id', 'desc')
    ->get();

echo "CREDIT NOTES:\n";
echo "Total: " . $creditNotes->count() . "\n";

$withData = 0;
$withoutData = 0;

foreach ($creditNotes as $note) {
    if ($note->buyer_data && !empty($note->buyer_data) && isset($note->buyer_data['buyerRegisterName'])) {
        $withData++;
        echo "✅ ID {$note->id} ({$note->invoice_no}) - HAS buyer_data: {$note->buyer_data['buyerRegisterName']}\n";
    } else {
        $withoutData++;
        echo "❌ ID {$note->id} ({$note->invoice_no}) - NO buyer_data (Original Receipt ID: {$note->original_receipt_id})\n";
    }
}

echo "\nCredit Notes Summary: {$withData} with data, {$withoutData} without data\n\n";

// Check all debit notes
$debitNotes = \App\Models\Receipt::where('receipt_type', 'DebitNote')
    ->orderBy('id', 'desc')
    ->get();

echo "DEBIT NOTES:\n";
echo "Total: " . $debitNotes->count() . "\n";

$withData = 0;
$withoutData = 0;

foreach ($debitNotes as $note) {
    if ($note->buyer_data && !empty($note->buyer_data) && isset($note->buyer_data['buyerRegisterName'])) {
        $withData++;
        echo "✅ ID {$note->id} ({$note->invoice_no}) - HAS buyer_data: {$note->buyer_data['buyerRegisterName']}\n";
    } else {
        $withoutData++;
        echo "❌ ID {$note->id} ({$note->invoice_no}) - NO buyer_data (Original Receipt ID: {$note->original_receipt_id})\n";
    }
}

echo "\nDebit Notes Summary: {$withData} with data, {$withoutData} without data\n";
