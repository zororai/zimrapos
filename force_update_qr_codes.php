<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Receipt;
use App\Models\ZimraConfig;

echo "=== Force Update QR Codes (Bypass Fiscal Day Protection) ===\n\n";

// Target invoices with their correct QR codes
$updates = [
    [
        'invoice_no' => 'INV-188',
        'qr_code' => 'https://fdmstest.zimra.co.zw/0000032558060320260000000188D55028878159A615',
        'verification_code' => 'D550-2887-8159-A615',
    ],
    [
        'invoice_no' => 'DN-1772821028',
        'qr_code' => 'https://fdmstest.zimra.co.zw/00000325580603202600000001908182552D1BE1DA40',
        'verification_code' => '8182-552D-1BE1-DA40',
    ],
    [
        'invoice_no' => 'INV-187',
        'qr_code' => 'https://fdmstest.zimra.co.zw/0000032558060320260000000187581F984CAB386293',
        'verification_code' => '581F-984C-AB38-6293',
    ],
    [
        'invoice_no' => 'CN-1772819143-c1ec',
        'qr_code' => 'https://fdmstest.zimra.co.zw/000003255806032026000000018998505D460514DAA5',
        'verification_code' => '9850-5D46-0514-DAA5',
    ],
];

foreach ($updates as $update) {
    echo "Updating: {$update['invoice_no']}\n";
    
    // Use raw SQL to bypass model events
    $affected = DB::table('receipts')
        ->where('invoice_no', $update['invoice_no'])
        ->update([
            'receipt_qr_code' => $update['qr_code'],
            'verification_code' => $update['verification_code'],
            'updated_at' => now(),
        ]);
    
    if ($affected > 0) {
        echo "  ✅ Updated successfully\n";
        echo "  QR Code: {$update['qr_code']}\n";
        echo "  Verification: {$update['verification_code']}\n\n";
    } else {
        echo "  ❌ No receipt found with invoice number: {$update['invoice_no']}\n\n";
    }
}

echo "Done! QR codes have been updated in the database.\n";
echo "You can now download fresh PDFs from the system.\n";
