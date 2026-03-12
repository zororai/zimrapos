<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ZimraConfig;

$config = ZimraConfig::where('device_id', 32558)->first();

if (!$config) {
    echo "No config found for device 32558\n";
    exit(1);
}

echo "ZIMRA Config for Device 32558:\n";
echo "  QR URL: " . ($config->qr_url ?? 'NULL') . "\n";
echo "  Base URL: {$config->base_url}\n";
echo "  Device Operating Mode: " . ($config->device_operating_mode ?? 'NULL') . "\n\n";

echo "Expected QR URL formats:\n";
echo "  Production: https://invoice.zimra.co.zw\n";
echo "  Test: https://fdmstest.zimra.co.zw (or similar)\n\n";

if ($config->qr_url) {
    echo "Current QR URL is set to: {$config->qr_url}\n";
    
    // Test if it's the correct format
    if (strpos($config->qr_url, 'invoice.zimra.co.zw') !== false) {
        echo "⚠️  WARNING: Using PRODUCTION validation portal!\n";
        echo "   Test receipts won't be found on production portal.\n";
    } elseif (strpos($config->qr_url, 'fdmstest.zimra.co.zw') !== false) {
        echo "✓ Using test validation portal\n";
    } else {
        echo "⚠️  Unknown QR URL format\n";
    }
} else {
    echo "❌ QR URL is not set! Call getConfig to retrieve it from FDMS.\n";
}
