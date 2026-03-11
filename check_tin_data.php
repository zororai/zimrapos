<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ZimraConfig;

echo "Checking ZIMRA Config TIN Data:\n";
echo str_repeat("=", 50) . "\n\n";

$configs = ZimraConfig::all();

foreach ($configs as $config) {
    echo "ID: {$config->id}\n";
    echo "Company Name: {$config->company_name}\n";
    echo "Company TIN: " . ($config->company_tin ?? 'NULL') . "\n";
    echo "Device ID: " . ($config->device_id ?? 'NULL') . "\n";
    echo str_repeat("-", 50) . "\n";
}

echo "\nTotal configs found: " . $configs->count() . "\n";
