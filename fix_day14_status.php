<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FiscalDay;

$fd = FiscalDay::where('device_id', 32558)
    ->where('fiscal_day_no', 14)
    ->first();

if (!$fd) {
    echo "Fiscal Day 14 not found\n";
    exit(1);
}

echo "Current Status: {$fd->status}\n";
echo "Current Closed At: " . ($fd->closed_at ?? 'NULL') . "\n";
echo "\nFDMS Status: FiscalDayCloseFailed (CountersMismatch)\n";
echo "Updating local status to 'open' to allow retry...\n\n";

$fd->update([
    'status' => 'open',
    'closed_at' => null,
    'close_operation_id' => null,
]);

echo "✅ Status updated successfully\n";
echo "\nFiscal Day 14 status:\n";
echo "  - Status: open (ready for retry)\n";
echo "  - Closed At: NULL\n";
echo "  - Counter fix: Applied (negative values preserved)\n";
echo "\nYou can now retry CloseDay for fiscal day 14.\n";
