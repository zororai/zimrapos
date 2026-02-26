<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$deviceId = 32857;

// Check if fiscal day 3 exists
$day3 = DB::table('fiscal_days')
    ->where('device_id', $deviceId)
    ->where('fiscal_day_no', 3)
    ->first();

if (!$day3) {
    echo "Fiscal day 3 not found. Creating...\n";
    
    DB::table('fiscal_days')->insert([
        'device_id' => $deviceId,
        'fiscal_day_no' => 3,
        'status' => 'open',
        'opened_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    echo "✅ Created fiscal day 3 with status: open\n";
} else {
    echo "Fiscal day 3 exists with status: {$day3->status}\n";
    
    if ($day3->status !== 'open') {
        DB::table('fiscal_days')
            ->where('device_id', $deviceId)
            ->where('fiscal_day_no', 3)
            ->update(['status' => 'open', 'updated_at' => now()]);
        
        echo "✅ Updated fiscal day 3 status to: open\n";
    }
}

// Now retry closeDay
echo "\nRetrying closeDay for fiscal day 3...\n";

$zimraService = app(\App\Services\ZimraDeviceService::class);

try {
    $result = $zimraService->closeDay(null, $deviceId);
    
    echo "\nCloseDay Result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT);
    echo "\n";
    
    if (isset($result['success']) && $result['success']) {
        echo "\n✅ Fiscal Day #3 closed successfully!\n";
    } else {
        echo "\n❌ CloseDay failed\n";
        if (isset($result['error_code'])) {
            echo "Error Code: {$result['error_code']}\n";
        }
    }
} catch (\Exception $e) {
    echo "\n❌ Exception: " . $e->getMessage() . "\n";
}
