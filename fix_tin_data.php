<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ZimraConfig;

echo "Fixing ZIMRA Config TIN Data:\n";
echo str_repeat("=", 50) . "\n\n";

// Get the config for Carlosprogramming PVT
$config = ZimraConfig::where('company_name', 'Carlosprogramming PVT')->first();

if ($config) {
    echo "Current Data:\n";
    echo "  Company Name: {$config->company_name}\n";
    echo "  Company TIN: {$config->company_tin}\n\n";
    
    // Prompt for the actual TIN number
    echo "Enter the actual TIN number for Carlosprogramming PVT: ";
    $tin = trim(fgets(STDIN));
    
    if (!empty($tin)) {
        $config->company_tin = $tin;
        $config->save();
        
        echo "\n✓ Successfully updated!\n";
        echo "  Company Name: {$config->company_name}\n";
        echo "  Company TIN: {$config->company_tin}\n";
    } else {
        echo "\n✗ No TIN entered. Update cancelled.\n";
    }
} else {
    echo "Config for 'Carlosprogramming PVT' not found.\n";
}
