<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Current companies:\n";
$configs = \App\Models\ZimraConfig::all(['id', 'company_name', 'is_active']);
foreach ($configs as $c) {
    echo "  {$c->id} - {$c->company_name} (" . ($c->is_active ? "Active" : "Inactive") . ")\n";
}

echo "\nAdding test company...\n";

// Deactivate all existing configs
\App\Models\ZimraConfig::query()->update(['is_active' => false]);

// Create new test company
$newCompany = \App\Models\ZimraConfig::create([
    'company_name' => 'Test Company ' . time(),
    'company_tin' => '9999999999',
    'base_url' => 'https://fdmsapitest.zimra.co.zw',
    'device_model' => 'Server',
    'device_version' => 'v1',
    'is_active' => true,
]);

echo "New company created: {$newCompany->company_name}\n";

echo "\nAll companies after creation:\n";
$allConfigs = \App\Models\ZimraConfig::all(['id', 'company_name', 'is_active']);
foreach ($allConfigs as $c) {
    echo "  {$c->id} - {$c->company_name} (" . ($c->is_active ? "Active" : "Inactive") . ")\n";
}

echo "\nTotal companies: " . $allConfigs->count() . "\n";
