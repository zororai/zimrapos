<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Syncing companies table to zimra_configs...\n\n";

// Get all companies from companies table
$companies = \App\Models\Company::all();

echo "Found {$companies->count()} companies in companies table\n\n";

foreach ($companies as $company) {
    echo "Processing: {$company->name}\n";
    
    // Check if this company already exists in zimra_configs
    $config = \App\Models\ZimraConfig::where('company_tin', $company->tin)->first();
    
    if ($config) {
        // Update existing config with device_id from companies table
        $config->update([
            'company_name' => $company->name,
            'device_id' => $company->device_id,
            'is_active' => $company->is_active,
        ]);
        echo "  ✓ Updated existing config (ID: {$config->id})\n";
    } else {
        // Create new config from company data
        $config = \App\Models\ZimraConfig::create([
            'company_name' => $company->name,
            'company_tin' => $company->tin,
            'device_id' => $company->device_id,
            'base_url' => 'https://fdmsapitest.zimra.co.zw',
            'device_model' => 'Server',
            'device_version' => 'v1',
            'is_active' => $company->is_active,
        ]);
        echo "  ✓ Created new config (ID: {$config->id})\n";
    }
}

echo "\n\nFinal state of zimra_configs:\n";
$allConfigs = \App\Models\ZimraConfig::all(['id', 'company_name', 'company_tin', 'device_id', 'is_active']);

foreach ($allConfigs as $config) {
    echo "ID: {$config->id} - {$config->company_name} (Device: " . ($config->device_id ?: 'Not registered') . ") - " . ($config->is_active ? 'Active' : 'Inactive') . "\n";
}

echo "\nTotal configs: {$allConfigs->count()}\n";
echo "\nSync complete!\n";
