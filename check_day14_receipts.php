<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Receipt;

$receipts = Receipt::where('fiscal_day_no', 14)->get();

echo "=== Day 14 Receipts ===\n\n";
echo "Total: {$receipts->count()}\n\n";

foreach ($receipts as $r) {
    echo "Receipt #{$r->receipt_counter}\n";
    echo "  Type: {$r->receipt_type}\n";
    echo "  Total: {$r->receipt_total} {$r->receipt_currency}\n";
    echo "  FDMS Receipt ID: " . ($r->fdms_receipt_id ?? 'NULL') . "\n";
    echo "  Valid: " . ($r->is_valid ? 'Yes' : 'No') . "\n";
    echo "  Global No: {$r->receipt_global_no}\n";
    echo "\n";
}
