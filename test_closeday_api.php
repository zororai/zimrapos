<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ZimraDeviceService;
use App\Models\FiscalDay;

echo "=== Testing CloseDay API Call with Fixed Signature ===\n\n";

$deviceId = 32558;
$fiscalDayNo = 12;

$fiscalDay = FiscalDay::where('fiscal_day_no', $fiscalDayNo)
    ->where('device_id', $deviceId)
    ->first();

if (!$fiscalDay) {
    echo "ERROR: Fiscal day {$fiscalDayNo} not found\n";
    exit(1);
}

echo "Attempting to close fiscal day {$fiscalDayNo} for device {$deviceId}...\n\n";

try {
    $zimraService = new ZimraDeviceService();
    
    // closeDay(array $payload = null, int $deviceId = null)
    // Pass null for payload to auto-build from fiscal day
    $result = $zimraService->closeDay(null, $deviceId);
    
    echo "✅ CloseDay SUCCESS!\n\n";
    echo "Response:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ CloseDay FAILED\n\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    // Show trace for debugging
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
