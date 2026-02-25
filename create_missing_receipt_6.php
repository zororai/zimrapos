<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Creating Missing Receipt #6 ===\n\n";

// Get receipt #5 and #7 to understand the context
$receipt5 = DB::table('receipts')->where('receipt_global_no', 5)->first();
$receipt7 = DB::table('receipts')->where('receipt_global_no', 7)->first();

echo "Context:\n";
echo "Receipt #5: Fiscal Day {$receipt5->fiscal_day_no}, Counter {$receipt5->receipt_counter}, Created {$receipt5->created_at}\n";
echo "Receipt #7: Fiscal Day {$receipt7->fiscal_day_no}, Counter {$receipt7->receipt_counter}, Created {$receipt7->created_at}\n\n";

// Check if we can create a void/cancelled receipt
echo "Creating void receipt #6 to fill the gap...\n\n";

// Use receipt #5 as template
$newReceipt = [
    'device_id' => $receipt5->device_id,
    'invoice_no' => 'VOID-006',
    'receipt_type' => 'NORMAL',
    'receipt_currency' => 'USD',
    'receipt_counter' => 6,
    'receipt_global_no' => 6,
    'fiscal_day_no' => 1, // Should be in fiscal day 1
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
    'receipt_date' => '2026-02-20 14:06:00', // Between #5 and #7
    'is_valid' => 0, // Mark as invalid/void
    'created_at' => '2026-02-20 14:06:00',
    'updated_at' => now(),
];

echo "Proposed receipt #6 data:\n";
foreach ($newReceipt as $key => $value) {
    if (!is_array($value) && !is_object($value)) {
        echo "  {$key}: {$value}\n";
    }
}

echo "\n⚠️  WARNING: This creates a VOID receipt to fill the gap.\n";
echo "⚠️  ZIMRA may still reject this if they don't have receipt #6 in their system.\n";
echo "⚠️  This is a workaround, not a proper fix.\n\n";

echo "Do you want to proceed? (This script does NOT auto-execute)\n";
echo "To execute, uncomment the DB::table()->insert() line below.\n\n";

// Uncomment to execute:
// DB::table('receipts')->insert($newReceipt);
// echo "✓ Receipt #6 created successfully\n";

echo "=== Alternative: Check ZIMRA's Receipt History ===\n";
echo "You should call ZIMRA's GetReceipts API to see if receipt #6 exists on their end.\n";
echo "If it doesn't exist on ZIMRA's side either, the issue is more complex.\n";
