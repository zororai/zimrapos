<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ZimraConfig;
use App\Models\FiscalDay;
use App\Models\Receipt;

echo "=== Device Configuration ===\n\n";

$configs = ZimraConfig::all();

foreach ($configs as $config) {
    echo "Device ID: {$config->device_id}\n";
    echo "Status: " . ($config->is_active ? 'Active' : 'Inactive') . "\n";
    echo "Device Name: {$config->device_name}\n";
    
    // Get fiscal days for this device
    $fiscalDays = FiscalDay::where('device_id', $config->device_id)
        ->orderBy('fiscal_day_no', 'desc')
        ->limit(5)
        ->get();
    
    echo "Recent Fiscal Days:\n";
    foreach ($fiscalDays as $day) {
        echo "  Day {$day->fiscal_day_no}: {$day->status}";
        if ($day->closed_at) {
            echo " (closed: {$day->closed_at})";
        }
        echo "\n";
    }
    
    // Get receipt count
    $receiptCount = Receipt::where('device_id', $config->device_id)->count();
    echo "Total Receipts: {$receiptCount}\n";
    
    echo "\n";
}

echo "=== FDMS Status Check ===\n\n";
echo "FDMS reports:\n";
echo "  Last Fiscal Day No: 4\n";
echo "  Last Receipt Global No: 14\n";
echo "  Status: FiscalDayClosed\n\n";

echo "This suggests FDMS is tracking a DIFFERENT device or fiscal day sequence.\n";
echo "Your local Day 14 receipts (79-86) exist on FDMS but may be on a different fiscal day.\n";
