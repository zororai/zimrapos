<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\FiscalDay;
use App\Models\Receipt;

$fiscalDay = FiscalDay::where('fiscal_day_no', 12)
    ->where('device_id', 32558)
    ->first();

if (!$fiscalDay) {
    echo "Fiscal day 12 not found\n";
    exit(1);
}

$receipt = Receipt::where('device_id', 32558)
    ->where('fiscal_day_no', 12)
    ->first();

if (!$receipt) {
    echo "No receipt found for fiscal day 12\n";
    exit(1);
}

echo "=== Receipt for Fiscal Day 12 ===\n\n";
echo "Receipt ID: {$receipt->id}\n";
echo "Receipt Counter: {$receipt->receipt_counter}\n";
echo "Receipt Type: {$receipt->receipt_type}\n";
echo "Receipt Total: {$receipt->receipt_total}\n";
echo "Receipt Global No: {$receipt->receipt_global_no}\n";
echo "Invoice No: {$receipt->invoice_no}\n\n";

// Check all possible payload fields
$payloadFields = ['submit_payload', 'zimra_response', 'receipt_lines', 'receipt_taxes', 'receipt_payments'];
$foundPayload = null;

foreach ($payloadFields as $field) {
    if (isset($receipt->$field) && !empty($receipt->$field)) {
        echo "=== Found data in field: {$field} ===\n\n";
        if (is_string($receipt->$field)) {
            $foundPayload = json_decode($receipt->$field, true);
        } else {
            $foundPayload = $receipt->$field;
        }
        echo json_encode($foundPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        echo "\n\n";
    }
}

// Show all receipt attributes
echo "=== All Receipt Attributes ===\n";
foreach ($receipt->getAttributes() as $key => $value) {
    if (!in_array($key, ['receipt_lines', 'receipt_taxes', 'receipt_payments', 'buyer_data', 'receipt_signature', 'zimra_response', 'validation_errors'])) {
        echo "{$key}: {$value}\n";
    }
}
echo "\n";
