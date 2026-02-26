<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$debugDir = storage_path('app/zimra/debug');
$files = glob($debugDir . '/*_final.json');

echo "=== Analyzing Debug Receipt Files ===\n\n";

$receipts = [];

foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);
    
    if (!$data || !isset($data['receipt'])) {
        continue;
    }
    
    $receipt = $data['receipt'];
    
    // Extract key info
    $globalNo = $receipt['receiptGlobalNo'] ?? null;
    $counter = $receipt['receiptCounter'] ?? null;
    $invoiceNo = $receipt['invoiceNo'] ?? 'N/A';
    $total = $receipt['receiptTotal'] ?? 0;
    $currency = $receipt['receiptCurrency'] ?? 'USD';
    $date = $receipt['receiptDate'] ?? 'N/A';
    
    // Get tax info
    $taxes = $receipt['receiptTaxes'] ?? [];
    $taxTotal = 0;
    $taxPercent = 0;
    $taxID = null;
    
    if (!empty($taxes)) {
        $taxTotal = $taxes[0]['salesAmountWithTax'] ?? 0;
        $taxPercent = $taxes[0]['taxPercent'] ?? 0;
        $taxID = $taxes[0]['taxID'] ?? null;
    }
    
    // Get payment info
    $payments = $receipt['receiptPayments'] ?? [];
    $paymentMethod = 'Cash';
    if (!empty($payments)) {
        $paymentMethod = $payments[0]['moneyTypeCode'] ?? 'Cash';
    }
    
    // Get lines
    $lines = $receipt['receiptLines'] ?? [];
    
    $receipts[] = [
        'global_no' => $globalNo,
        'counter' => $counter,
        'invoice_no' => $invoiceNo,
        'total' => $total,
        'currency' => $currency,
        'date' => $date,
        'tax_total' => $taxTotal,
        'tax_percent' => $taxPercent,
        'tax_id' => $taxID,
        'payment_method' => $paymentMethod,
        'lines' => $lines,
        'file' => basename($file),
    ];
}

// Sort by global number
usort($receipts, function($a, $b) {
    return ($a['global_no'] ?? 999) - ($b['global_no'] ?? 999);
});

echo "Found " . count($receipts) . " receipts in debug files:\n\n";

foreach ($receipts as $r) {
    echo "Global #{$r['global_no']}: Invoice {$r['invoice_no']}, Total: {$r['currency']} {$r['total']}, Tax: {$r['tax_percent']}%, Lines: " . count($r['lines']) . "\n";
}

// Check which ones are missing from DB
echo "\n=== Checking Database ===\n\n";

$dbReceipts = DB::table('receipts')
    ->where('device_id', 32857)
    ->pluck('receipt_global_no')
    ->toArray();

echo "Database has global numbers: " . implode(', ', $dbReceipts) . "\n\n";

$missingGlobalNos = [];
foreach ($receipts as $r) {
    if ($r['global_no'] && !in_array($r['global_no'], $dbReceipts)) {
        $missingGlobalNos[] = $r['global_no'];
    }
}

if (empty($missingGlobalNos)) {
    echo "✅ All debug receipts are in the database.\n";
} else {
    echo "❌ Missing from database: Global #" . implode(', #', $missingGlobalNos) . "\n\n";
    
    echo "=== Missing Receipt Details ===\n\n";
    
    foreach ($receipts as $r) {
        if (in_array($r['global_no'], $missingGlobalNos)) {
            echo "Global #{$r['global_no']}:\n";
            echo "  Invoice: {$r['invoice_no']}\n";
            echo "  Total: {$r['currency']} {$r['total']}\n";
            echo "  Tax: {$r['tax_percent']}% (ID: {$r['tax_id']})\n";
            echo "  Payment: {$r['payment_method']}\n";
            echo "  Date: {$r['date']}\n";
            echo "  Lines: " . count($r['lines']) . "\n";
            
            foreach ($r['lines'] as $idx => $line) {
                echo "    Line " . ($idx + 1) . ": {$line['receiptLineName']} - {$r['currency']} {$line['receiptLineTotal']}\n";
            }
            echo "\n";
        }
    }
    
    echo "=== Recreate Missing Receipts? ===\n";
    echo "This will insert placeholder records for the missing receipts.\n";
    echo "Continue? (yes/no): ";
    
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if ($line === 'yes') {
        $created = 0;
        
        foreach ($receipts as $r) {
            if (!in_array($r['global_no'], $missingGlobalNos)) {
                continue;
            }
            
            // Determine fiscal day based on global number
            // Global 1-6: Day 1, Global 7-11: Day 2, etc.
            $fiscalDayNo = 1;
            if ($r['global_no'] >= 7 && $r['global_no'] <= 11) {
                $fiscalDayNo = 2;
            } elseif ($r['global_no'] >= 12) {
                $fiscalDayNo = 3;
            }
            
            DB::table('receipts')->insert([
                'device_id' => 32857,
                'invoice_no' => $r['invoice_no'],
                'receipt_type' => 'FiscalInvoice',
                'receipt_currency' => $r['currency'],
                'receipt_counter' => $r['counter'],
                'receipt_global_no' => $r['global_no'],
                'fiscal_day_no' => $fiscalDayNo,
                'receipt_total' => $r['total'],
                'tax_amount' => 0,
                'tax_code' => null,
                'tax_percent' => $r['tax_percent'],
                'payment_method' => $r['payment_method'],
                'receipt_lines' => json_encode($r['lines']),
                'receipt_taxes' => json_encode($r['lines']), // Simplified
                'receipt_payments' => json_encode([['moneyTypeCode' => $r['payment_method'], 'paymentAmount' => $r['total']]]),
                'receipt_date' => $r['date'],
                'validation_code' => 'Red',
                'is_valid' => false,
                'has_red_errors' => true,
                'has_gray_errors' => false,
                'fdms_receipt_id' => 'RECOVERED-' . $r['global_no'],
                'receipt_notes' => 'Recovered from debug files - had validation errors',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $created++;
            echo "  ✓ Created Global #{$r['global_no']} - {$r['invoice_no']}\n";
        }
        
        echo "\n✅ Created {$created} missing receipts from debug files.\n";
    } else {
        echo "\nNo receipts created.\n";
    }
}
