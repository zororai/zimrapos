<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$configs = \App\Models\ZimraConfig::all(['id', 'company_name', 'is_active']);

echo "Total configs: " . $configs->count() . "\n";
foreach ($configs as $c) {
    echo $c->id . " - " . $c->company_name . " (" . ($c->is_active ? "Active" : "Inactive") . ")\n";
}
