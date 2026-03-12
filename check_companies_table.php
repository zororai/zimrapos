<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Companies table:\n";
$companies = \App\Models\Company::all(['id', 'name', 'tin', 'device_id', 'is_active']);

echo "Total companies: " . $companies->count() . "\n\n";

foreach ($companies as $company) {
    echo "ID: {$company->id}\n";
    echo "Name: {$company->name}\n";
    echo "TIN: {$company->tin}\n";
    echo "Device ID: {$company->device_id}\n";
    echo "Active: " . ($company->is_active ? "Yes" : "No") . "\n";
    echo "---\n";
}

echo "\n\nZimra Configs table:\n";
$configs = \App\Models\ZimraConfig::all(['id', 'company_name', 'company_tin', 'device_id', 'is_active']);

echo "Total configs: " . $configs->count() . "\n\n";

foreach ($configs as $config) {
    echo "ID: {$config->id}\n";
    echo "Company Name: {$config->company_name}\n";
    echo "TIN: {$config->company_tin}\n";
    echo "Device ID: {$config->device_id}\n";
    echo "Active: " . ($config->is_active ? "Yes" : "No") . "\n";
    echo "---\n";
}
