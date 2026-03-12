<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Receipt;

echo "=== QR Code Validation Test ===\n\n";

// Get latest receipt
$receipt = Receipt::orderBy('id', 'desc')->first();

if (!$receipt) {
    echo "No receipts found\n";
    exit(1);
}

echo "Testing QR code for: {$receipt->invoice_no}\n";
echo "FDMS Receipt ID: {$receipt->fdms_receipt_id}\n";
echo "QR Code: {$receipt->receipt_qr_code}\n\n";

echo "Possible reasons for 'Invoice not found':\n\n";

echo "1. FDMS Test Portal Delay\n";
echo "   - The test portal may take time to index receipts\n";
echo "   - Wait 5-10 minutes and try again\n\n";

echo "2. Wrong Validation Portal\n";
echo "   - Current: https://fdmstest.zimra.co.zw\n";
echo "   - Production: https://invoice.zimra.co.zw\n";
echo "   - Test receipts won't appear on production portal\n\n";

echo "3. QR Validation Not Supported in Test Environment\n";
echo "   - The test environment might not have QR validation enabled\n";
echo "   - QR validation might only work in production\n\n";

echo "4. Receipt Status\n";
echo "   - Current status: {$receipt->status}\n";
echo "   - Expected: finalized\n";
if ($receipt->status !== 'finalized') {
    echo "   ⚠️  Receipt is not finalized - this might prevent QR validation\n";
}
echo "\n";

echo "Recommendations:\n";
echo "1. Wait 5-10 minutes for FDMS to index the receipt\n";
echo "2. Try scanning the QR code again\n";
echo "3. Contact ZIMRA support to confirm QR validation is enabled in test environment\n";
echo "4. Verify the receipt appears in FDMS portal under your device receipts\n";
