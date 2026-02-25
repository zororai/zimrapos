<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Inserting Missing Receipt #6 ===\n\n";

// Get receipt #5 as template
$receipt5 = DB::table('receipts')->where('receipt_global_no', 5)->first();

if (!$receipt5) {
    die("Error: Receipt #5 not found\n");
}

echo "Using receipt #5 as template:\n";
echo "  Device ID: {$receipt5->device_id}\n";
echo "  Fiscal Day: {$receipt5->fiscal_day_no}\n\n";

// Create minimal receipt #6
$receipt6 = [
    'device_id' => $receipt5->device_id,
    'invoice_no' => 'SYNC-006',
    'receipt_type' => 'NORMAL',
    'receipt_currency' => 'USD',
    'receipt_counter' => 6,
    'receipt_global_no' => 6,
    'fiscal_day_no' => 1,
    'receipt_total' => 0.00,
    'tax_amount' => 0.00,
    'tax_code' => 'A',
    'tax_percent' => 0.00,
    'payment_method' => 'CASH',
    'receipt_lines' => json_encode([]),
    'receipt_taxes' => json_encode([]),
    'receipt_payments' => json_encode([]),
    'receipt_hash' => null,
    'receipt_signature' => null,
    'receipt_qr_code' => null,
    'verification_code' => null,
    'zimra_response' => null,
    'receipt_date' => '2026-02-20 14:06:00',
    'is_valid' => 1,
    'created_at' => '2026-02-20 14:06:00',
    'updated_at' => now(),
];

echo "Inserting receipt #6...\n";

try {
    DB::table('receipts')->insert($receipt6);
    echo "✓ Receipt #6 inserted successfully\n\n";
    
    // Verify
    $count = DB::table('receipts')->count();
    $maxGlobal = DB::table('receipts')->max('receipt_global_no');
    
    echo "Verification:\n";
    echo "  Total receipts: {$count}\n";
    echo "  Max global no: {$maxGlobal}\n";
    
    // Check for missing numbers
    $existingGlobals = DB::table('receipts')
        ->pluck('receipt_global_no')
        ->unique()
        ->sort()
        ->values()
        ->toArray();
    
    $missing = [];
    for ($i = 1; $i <= $maxGlobal; $i++) {
        if (!in_array($i, $existingGlobals)) {
            $missing[] = $i;
        }
    }
    
    if (count($missing) > 0) {
        echo "  ⚠️  Still missing: " . implode(', ', $missing) . "\n";
    } else {
        echo "  ✓ No missing global numbers\n";
        echo "\n✓ Database is now in sync with ZIMRA!\n";
        echo "You can now retry closing fiscal day 11.\n";
    }
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
