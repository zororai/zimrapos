<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$deviceId = 32857;

echo "Attempting to close Fiscal Day #3...\n\n";

// Get FDMS status first
$zimraService = app(\App\Services\ZimraDeviceService::class);
$fdmsStatus = $zimraService->getStatus($deviceId);

echo "FDMS Current Status:\n";
echo "  Fiscal Day: {$fdmsStatus['lastFiscalDayNo']}\n";
echo "  Status: {$fdmsStatus['fiscalDayStatus']}\n";
echo "  Last Receipt Global No: {$fdmsStatus['lastReceiptGlobalNo']}\n";

if (isset($fdmsStatus['lastReceiptCounter'])) {
    echo "  Last Receipt Counter: {$fdmsStatus['lastReceiptCounter']}\n";
}

echo "\n";

// Attempt to close the day
try {
    $result = $zimraService->closeDay(null, $deviceId);
    
    echo "CloseDay Result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT);
    echo "\n";
    
    if (isset($result['success']) && $result['success']) {
        echo "\n✅ Fiscal Day #3 closed successfully!\n";
    } else {
        echo "\n❌ CloseDay failed:\n";
        if (isset($result['error'])) {
            echo "  Error: {$result['error']}\n";
        }
        if (isset($result['error_code'])) {
            echo "  Error Code: {$result['error_code']}\n";
        }
    }
} catch (\Exception $e) {
    echo "\n❌ Exception: " . $e->getMessage() . "\n";
}
