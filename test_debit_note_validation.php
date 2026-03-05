<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Receipt;
use App\Services\ZimraDeviceService;

// Find a successful fiscal invoice to use as reference
$originalReceipt = Receipt::where('receipt_type', 'FiscalInvoice')
    ->where('device_id', 32558)
    ->whereNotNull('fdms_receipt_id')
    ->where('validation_code', 'Green')
    ->orderBy('id', 'desc')
    ->first();

if (!$originalReceipt) {
    echo "No valid fiscal invoice found to reference\n";
    exit(1);
}

echo "Original Receipt Found:\n";
echo "  ID: {$originalReceipt->id}\n";
echo "  Invoice No: {$originalReceipt->invoice_no}\n";
echo "  Global No: {$originalReceipt->receipt_global_no}\n";
echo "  FDMS ID: {$originalReceipt->fdms_receipt_id}\n";
echo "  Date: {$originalReceipt->receipt_date}\n";
echo "  Type: {$originalReceipt->receipt_type}\n";
echo "  Validation: {$originalReceipt->validation_code}\n\n";

// Get the receipt lines from the original
$receiptLines = json_decode($originalReceipt->receipt_lines, true);
$receiptTaxes = json_decode($originalReceipt->receipt_taxes, true);

echo "Original Receipt Lines:\n";
print_r($receiptLines);
echo "\nOriginal Receipt Taxes:\n";
print_r($receiptTaxes);

// Build a minimal debit note payload
$debitNotePayload = [
    'receiptType' => 'DebitNote',
    'receiptCurrency' => 'USD',
    'invoiceNo' => 'DN-TEST-' . time(),
    'receiptNotes' => 'Test debit note for validation',
    'receiptLinesTaxInclusive' => false,
    'creditDebitNote' => [
        'creditDebitNoteReceiptGlobalNo' => (int)$originalReceipt->receipt_global_no,
        'creditDebitNoteDate' => $originalReceipt->receipt_date instanceof \Carbon\Carbon
            ? $originalReceipt->receipt_date->format('Y-m-d\TH:i:s')
            : \Carbon\Carbon::parse($originalReceipt->receipt_date)->format('Y-m-d\TH:i:s'),
    ],
    'receiptDate' => now()->format('Y-m-d\TH:i:s'),
    'receiptLines' => $receiptLines,
    'receiptTaxes' => $receiptTaxes,
    'receiptPayments' => [
        [
            'moneyTypeCode' => 'Cash',
            'paymentAmount' => (float)$originalReceipt->receipt_total,
        ]
    ],
    'receiptTotal' => (float)$originalReceipt->receipt_total,
];

echo "\n\nDebit Note Payload to Test:\n";
echo json_encode($debitNotePayload, JSON_PRETTY_PRINT) . "\n";

echo "\n\nDo you want to submit this test debit note to FDMS? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) != 'yes') {
    echo "Test cancelled\n";
    exit(0);
}

echo "\nSubmitting to FDMS...\n";
$zimraService = app(ZimraDeviceService::class);
$result = $zimraService->submitReceipt($debitNotePayload, 32558);

echo "\n\nResult:\n";
print_r($result);
