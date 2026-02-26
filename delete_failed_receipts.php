<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Delete receipts with RED errors for device 32857
$deleted = DB::table('receipts')
    ->where('device_id', 32857)
    ->where('has_red_errors', true)
    ->delete();

echo "Deleted {$deleted} receipts with RED validation errors.\n";
echo "You can now close the fiscal day.\n";
