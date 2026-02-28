<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FiscalDay;

$fd = FiscalDay::where('fiscal_day_no', 14)->first();

if (!$fd) {
    echo "Fiscal Day 14 not found in database\n";
    exit(1);
}

echo "=== Fiscal Day 14 Status ===\n\n";
echo "ID: {$fd->id}\n";
echo "Device ID: {$fd->device_id}\n";
echo "Fiscal Day No: {$fd->fiscal_day_no}\n";
echo "Status: {$fd->status}\n";
echo "Opened At: {$fd->opened_at}\n";
echo "Closed At: " . ($fd->closed_at ?? 'NULL') . "\n";
echo "Error Code: " . ($fd->error_code ?? 'NULL') . "\n";
echo "Error Message: " . ($fd->error_message ?? 'NULL') . "\n";
echo "Created At: {$fd->created_at}\n";
echo "Updated At: {$fd->updated_at}\n";

echo "\n=== All Fiscal Days ===\n\n";

$allDays = FiscalDay::orderBy('fiscal_day_no', 'desc')->get();

foreach ($allDays as $day) {
    echo "Day {$day->fiscal_day_no}: {$day->status}";
    if ($day->error_code) {
        echo " (Error: {$day->error_code})";
    }
    echo "\n";
}
