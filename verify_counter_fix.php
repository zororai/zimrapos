<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Receipt;
use App\Models\FiscalDay;

echo "=== Counter Aggregation Verification ===\n\n";

// Get fiscal day 14
$fiscalDay = FiscalDay::where('fiscal_day_no', 14)->first();

if (!$fiscalDay) {
    echo "ERROR: Fiscal day 14 not found\n";
    exit(1);
}

$deviceId = $fiscalDay->device_id;

// Get all valid receipts
$receipts = Receipt::where('device_id', $deviceId)
    ->where('fiscal_day_no', 14)
    ->where('is_valid', true)
    ->get();

echo "Fiscal Day: {$fiscalDay->fiscal_day_no}\n";
echo "Device ID: {$deviceId}\n";
echo "Total Receipts: {$receipts->count()}\n\n";

// Aggregate counters exactly as FDMS would
$counters = [];

foreach ($receipts as $receipt) {
    $receiptType = $receipt->receipt_type ?? 'FiscalInvoice';
    $currency = $receipt->receipt_currency ?? 'USD';
    $receiptTaxes = $receipt->receipt_taxes ?? [];
    
    echo "Receipt #{$receipt->receipt_counter} ({$receiptType}): {$receipt->receipt_total} {$currency}\n";
    
    // Determine counter type based on receipt type
    $salesCounterType = 'SaleByTax';
    if ($receiptType === 'CreditNote') {
        $salesCounterType = 'CreditNoteByTax';
    } elseif ($receiptType === 'DebitNote') {
        $salesCounterType = 'DebitNoteByTax';
    }
    
    foreach ($receiptTaxes as $tax) {
        $taxPercent = (float) ($tax['taxPercent'] ?? 0);
        $taxID = (int) ($tax['taxID'] ?? 1);
        $salesAmountWithTax = (float) ($tax['salesAmountWithTax'] ?? 0);
        
        if ($salesAmountWithTax == 0) {
            continue;
        }
        
        $key = "{$salesCounterType}_{$currency}_{$taxID}_" . number_format($taxPercent, 2, '_', '');
        
        if (!isset($counters[$key])) {
            $counters[$key] = [
                'fiscalCounterType' => $salesCounterType,
                'fiscalCounterCurrency' => $currency,
                'fiscalCounterTaxPercent' => $taxPercent,
                'fiscalCounterTaxID' => $taxID,
                'fiscalCounterValue' => 0,
            ];
        }
        
        $counters[$key]['fiscalCounterValue'] += $salesAmountWithTax;
        
        echo "  - Tax {$taxID} ({$taxPercent}%): {$salesAmountWithTax} → {$salesCounterType}\n";
    }
}

echo "\n=== Expected CloseDay Counters ===\n\n";

// Filter out zero values (but keep negatives!)
$filteredCounters = array_filter($counters, function ($c) {
    return $c['fiscalCounterValue'] != 0;
});

$fiscalCounters = array_values($filteredCounters);

foreach ($fiscalCounters as $counter) {
    $value = round($counter['fiscalCounterValue'], 2);
    echo "{$counter['fiscalCounterType']} {$counter['fiscalCounterCurrency']} ";
    echo "Tax{$counter['fiscalCounterTaxID']} ({$counter['fiscalCounterTaxPercent']}%): ";
    echo "{$value}\n";
}

echo "\n=== Verification ===\n\n";

// Group by type
$byType = [];
foreach ($fiscalCounters as $counter) {
    $type = $counter['fiscalCounterType'];
    if (!isset($byType[$type])) {
        $byType[$type] = 0;
    }
    $byType[$type] += $counter['fiscalCounterValue'];
}

foreach ($byType as $type => $total) {
    echo "{$type} Total: " . round($total, 2) . "\n";
}

$totalReceipts = $receipts->sum(function ($r) {
    return (float) $r->receipt_total;
});

$totalCounters = array_sum(array_column($fiscalCounters, 'fiscalCounterValue'));

echo "\nTotal from Receipts: " . round($totalReceipts, 2) . "\n";
echo "Total from Counters: " . round($totalCounters, 2) . "\n";
echo "Difference: " . round($totalReceipts - $totalCounters, 2) . "\n";

if (abs($totalReceipts - $totalCounters) < 0.01) {
    echo "\n✅ PASS: Counters match receipts\n";
} else {
    echo "\n❌ FAIL: Counter mismatch\n";
    exit(1);
}

echo "\n=== Counter JSON (Ready for FDMS) ===\n\n";
echo json_encode($fiscalCounters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n";
