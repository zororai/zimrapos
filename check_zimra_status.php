<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ZimraDeviceService;

echo "=== Checking ZIMRA Device Status ===\n\n";

$zimraService = new ZimraDeviceService();

try {
    $status = $zimraService->getStatus();
    
    echo "ZIMRA Status Response:\n";
    echo json_encode($status, JSON_PRETTY_PRINT) . "\n\n";
    
    if (isset($status['lastReceiptGlobalNo'])) {
        echo "ZIMRA's last receipt global no: {$status['lastReceiptGlobalNo']}\n";
    }
    
    if (isset($status['lastFiscalDayNo'])) {
        echo "ZIMRA's last fiscal day no: {$status['lastFiscalDayNo']}\n";
    }
    
    if (isset($status['fiscalDayStatus'])) {
        echo "Fiscal day status: {$status['fiscalDayStatus']}\n";
    }
    
    if (isset($status['fiscalDayClosingErrorCode'])) {
        echo "Error code: {$status['fiscalDayClosingErrorCode']}\n";
    }
    
    // Compare with local database
    echo "\n=== Local Database ===\n";
    $localMaxGlobal = DB::table('receipts')->max('receipt_global_no');
    $localCount = DB::table('receipts')->count();
    
    echo "Local max global no: {$localMaxGlobal}\n";
    echo "Local receipt count: {$localCount}\n";
    
    if (isset($status['lastReceiptGlobalNo'])) {
        $zimraMax = $status['lastReceiptGlobalNo'];
        if ($zimraMax != $localMaxGlobal) {
            echo "\n⚠️  MISMATCH: ZIMRA has {$zimraMax}, local has {$localMaxGlobal}\n";
        }
        
        if ($zimraMax != $localCount) {
            echo "⚠️  MISSING RECEIPTS: ZIMRA expects {$zimraMax} receipts, local has {$localCount}\n";
            echo "Missing count: " . ($zimraMax - $localCount) . "\n";
        }
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
