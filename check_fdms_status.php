<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get FDMS status for device 32558 (ACTIVE device)
$zimraService = app(\App\Services\ZimraDeviceService::class);

try {
    $status = $zimraService->getStatus(32558);
    
    echo "FDMS Device Status:\n";
    echo "==================\n";
    echo json_encode($status, JSON_PRETTY_PRINT);
    echo "\n\n";
    
    if (isset($status['lastFiscalDayNo'])) {
        echo "Last Fiscal Day No: {$status['lastFiscalDayNo']}\n";
    }
    if (isset($status['fiscalDayStatus'])) {
        echo "Fiscal Day Status: {$status['fiscalDayStatus']}\n";
    }
    if (isset($status['lastReceiptCounter'])) {
        echo "Last Receipt Counter: {$status['lastReceiptCounter']}\n";
    }
    if (isset($status['lastReceiptGlobalNo'])) {
        echo "Last Receipt Global No: {$status['lastReceiptGlobalNo']}\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
