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

echo "Finalizing receipt #{$receipt->id} - {$receipt->invoice_no}\n";
echo "Current status: {$receipt->status}\n";

if ($receipt->status === 'finalized') {
    echo "✓ Receipt is already finalized\n";
    exit(0);
}

if (!$receipt->fdms_receipt_id) {
    echo "❌ Cannot finalize - receipt has no FDMS Receipt ID\n";
    exit(1);
}

// Update status to finalized
$receipt->update(['status' => 'finalized']);

echo "✓ Receipt status updated to: finalized\n";
echo "  FDMS Receipt ID: {$receipt->fdms_receipt_id}\n";
echo "  QR Code: {$receipt->receipt_qr_code}\n\n";

echo "Try scanning the QR code again. If it still shows 'Invoice not found':\n";
echo "1. Wait 5-10 minutes for FDMS to index the receipt\n";
echo "2. Contact ZIMRA to confirm QR validation is enabled in test environment\n";
