<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$debugDir = storage_path('app/zimra/debug');

// Map of missing receipts with their final submission data
$missingReceipts = [
    7 => 'receipt_2026-02-26_06-53-54_INV-006_final.json',
    8 => 'receipt_2026-02-26_07-09-56_INV-007_final.json',
    9 => 'receipt_2026-02-26_07-34-53_INV-008_final.json',
    10 => 'receipt_2026-02-26_07-35-34_INV-009_final.json',
    11 => 'receipt_2026-02-26_07-39-45_INV-010_final.json',
];

echo "=== Recreating Missing Receipts from Debug Files ===\n\n";

foreach ($missingReceipts as $globalNo => $filename) {
    $filepath = $debugDir . '/' . $filename;
    
    if (!file_exists($filepath)) {
        echo "❌ File not found: {$filename}\n";
        continue;
    }
    
    $data = json_decode(file_get_contents($filepath), true);
    
    if (!$data || !isset($data['receipt'])) {
        echo "❌ Invalid data in: {$filename}\n";
        continue;
    }
    
    $receipt = $data['receipt'];
    
    // Extract data
    $invoiceNo = $receipt['invoiceNo'] ?? 'UNKNOWN';
    $receiptCounter = $receipt['receiptCounter'] ?? 1;
    $receiptTotal = $receipt['receiptTotal'] ?? 0;
    $currency = $receipt['receiptCurrency'] ?? 'USD';
    $receiptDate = $receipt['receiptDate'] ?? now();
    
    $taxes = $receipt['receiptTaxes'] ?? [];
    $taxPercent = 0;
    $taxID = 513;
    $salesAmountWithTax = $receiptTotal;
    
    if (!empty($taxes)) {
        $taxPercent = $taxes[0]['taxPercent'] ?? 0;
        $taxID = $taxes[0]['taxID'] ?? 513;
        $salesAmountWithTax = $taxes[0]['salesAmountWithTax'] ?? $receiptTotal;
    }
    
    $payments = $receipt['receiptPayments'] ?? [];
    $paymentMethod = 'Cash';
    if (!empty($payments)) {
        $paymentMethod = $payments[0]['moneyTypeCode'] ?? 'Cash';
    }
    
    $lines = $receipt['receiptLines'] ?? [];
    
    // Determine fiscal day (Global 7-11 are Day 2)
    $fiscalDayNo = 2;
    
    echo "Global #{$globalNo}: {$invoiceNo}\n";
    echo "  Counter: {$receiptCounter}, Total: {$currency} {$receiptTotal}\n";
    echo "  Tax: {$taxPercent}% (ID: {$taxID}), Sales: {$salesAmountWithTax}\n";
    echo "  Payment: {$paymentMethod}\n";
    echo "  Fiscal Day: {$fiscalDayNo}\n";
    
    // Insert into database
    try {
        DB::table('receipts')->insert([
            'device_id' => 32857,
            'invoice_no' => $invoiceNo,
            'receipt_type' => 'FiscalInvoice',
            'receipt_currency' => $currency,
            'receipt_counter' => $receiptCounter,
            'receipt_global_no' => $globalNo,
            'fiscal_day_no' => $fiscalDayNo,
            'receipt_total' => $receiptTotal,
            'tax_amount' => 0, // Tax amount is 0 for 0% tax
            'tax_code' => 'A', // Default tax code
            'tax_percent' => $taxPercent,
            'payment_method' => $paymentMethod,
            'receipt_lines' => json_encode($lines),
            'receipt_taxes' => json_encode($taxes),
            'receipt_payments' => json_encode($payments),
            'receipt_date' => $receiptDate,
            'validation_code' => 'Red',
            'is_valid' => false,
            'has_red_errors' => true,
            'has_gray_errors' => false,
            'fdms_receipt_id' => 'RECOVERED-' . $globalNo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "  ✅ Created in database\n\n";
    } catch (\Exception $e) {
        echo "  ❌ Error: {$e->getMessage()}\n\n";
    }
}

echo "=== Summary ===\n";
$dbReceipts = DB::table('receipts')
    ->where('device_id', 32857)
    ->orderBy('receipt_global_no')
    ->pluck('receipt_global_no')
    ->toArray();

echo "Database now has global numbers: " . implode(', ', $dbReceipts) . "\n";

// Calculate totals for Day 2
$day2Receipts = DB::table('receipts')
    ->where('device_id', 32857)
    ->where('fiscal_day_no', 2)
    ->get();

$day2Total = 0;
foreach ($day2Receipts as $r) {
    $day2Total += $r->receipt_total;
}

echo "\nFiscal Day 2 Summary:\n";
echo "  Receipts: " . $day2Receipts->count() . "\n";
echo "  Total Sales: USD {$day2Total}\n";
echo "  Global Numbers: " . implode(', ', $day2Receipts->pluck('receipt_global_no')->toArray()) . "\n";
