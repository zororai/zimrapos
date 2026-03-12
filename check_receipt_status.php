<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Receipt;

$receiptId = $argv[1] ?? 221;

$receipt = Receipt::find($receiptId);

if (!$receipt) {
    echo "Receipt not found\n";
    exit(1);
}

echo "Receipt #{$receipt->id} - {$receipt->invoice_no}\n";
echo "Status: {$receipt->status}\n";
echo "FDMS Receipt ID: " . ($receipt->fdms_receipt_id ?? 'NULL') . "\n";
echo "Validation Code: " . ($receipt->validation_code ?? 'NULL') . "\n";
echo "Is Valid: " . ($receipt->is_valid ? 'Yes' : 'No') . "\n";
echo "Has Red Errors: " . ($receipt->has_red_errors ? 'Yes' : 'No') . "\n";
echo "Has Gray Errors: " . ($receipt->has_gray_errors ? 'Yes' : 'No') . "\n\n";

echo "QR Code: {$receipt->receipt_qr_code}\n\n";

// Check if receipt was actually submitted to FDMS
if ($receipt->status === 'pending') {
    echo "⚠️  WARNING: Receipt is still PENDING - not yet submitted to FDMS!\n";
    echo "   This means the receipt was created but submission failed or is incomplete.\n\n";
    
    // Check for errors in zimra_response
    if ($receipt->zimra_response && isset($receipt->zimra_response['error'])) {
        echo "FDMS Error:\n";
        echo "  " . ($receipt->zimra_response['message'] ?? 'Unknown error') . "\n";
    }
} elseif ($receipt->status === 'failed') {
    echo "❌ Receipt submission FAILED\n";
    if ($receipt->zimra_response && isset($receipt->zimra_response['error'])) {
        echo "Error: " . ($receipt->zimra_response['message'] ?? 'Unknown error') . "\n";
    }
} elseif ($receipt->status === 'submitted' || $receipt->status === 'finalized') {
    echo "✓ Receipt successfully submitted to FDMS\n";
    echo "  FDMS Receipt ID: {$receipt->fdms_receipt_id}\n";
    echo "  Validation: {$receipt->validation_code}\n";
}
