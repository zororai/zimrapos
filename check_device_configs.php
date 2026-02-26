<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ZimraConfig;

echo "=== ZIMRA DEVICE CONFIGURATIONS ===\n\n";

$configs = ZimraConfig::all();

foreach ($configs as $config) {
    echo "Device ID: {$config->device_id}\n";
    echo "Serial Number: {$config->serial_number}\n";
    echo "Base URL: {$config->base_url}\n";
    echo "Certificate Path: {$config->certificate_path}\n";
    echo "Private Key Path: {$config->private_key_path}\n";
    echo "Is Active: " . ($config->is_active ? 'YES' : 'NO') . "\n";
    echo "\n";
}

echo "=== CHECKING CERTIFICATE FILES ===\n\n";

foreach ($configs as $config) {
    echo "Device {$config->device_id} ({$config->serial_number}):\n";
    
    if (file_exists($config->certificate_path)) {
        echo "  ✓ Certificate exists: {$config->certificate_path}\n";
    } else {
        echo "  ❌ Certificate MISSING: {$config->certificate_path}\n";
    }
    
    if (file_exists($config->private_key_path)) {
        echo "  ✓ Private key exists: {$config->private_key_path}\n";
    } else {
        echo "  ❌ Private key MISSING: {$config->private_key_path}\n";
    }
    
    echo "\n";
}
