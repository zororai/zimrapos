<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\FiscalDay;

$fiscalDay = FiscalDay::where('fiscal_day_no', 12)
    ->where('device_id', 32558)
    ->first();

if (!$fiscalDay) {
    echo "Fiscal day 12 not found\n";
    exit(1);
}

echo "Fiscal Day 12 Details:\n";
echo "Opened at: {$fiscalDay->opened_at}\n";
echo "Closed at: " . ($fiscalDay->closed_at ?? 'NULL') . "\n";
echo "fiscalDayDate (YYYY-MM-DD): " . $fiscalDay->opened_at->format('Y-m-d') . "\n";
echo "fiscalDayClosed (YYYY-MM-DDTHH:mm:ss): " . now()->format('Y-m-d\TH:i:s') . "\n";
