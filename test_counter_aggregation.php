<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\FiscalDay;
use App\Models\Receipt;

echo "=== Testing Counter Aggregation for Fiscal Day 12 ===\n\n";

$fiscalDay = FiscalDay::where('fiscal_day_no', 12)
    ->where('device_id', 32558)
    ->first();

if (!$fiscalDay) {
    echo "ERROR: Fiscal day 12 not found\n";
    exit(1);
}

$receipts = Receipt::where('device_id', 32558)
    ->where('fiscal_day_no', 12)
    ->where('is_valid', true)
    ->get();

echo "Fiscal Day: {$fiscalDay->fiscal_day_no}\n";
echo "Receipt Count: {$receipts->count()}\n\n";

// Simulate counter aggregation logic
$counters = [];
$totalReceiptValueCents = 0;

foreach ($receipts as $receipt) {
    echo "--- Processing Receipt #{$receipt->receipt_counter} ---\n";
    echo "Receipt Total: \${$receipt->receipt_total}\n";
    echo "Currency: {$receipt->receipt_currency}\n";
    
    $receiptTaxes = $receipt->receipt_taxes ?? [];
    $receiptPayments = $receipt->receipt_payments ?? [];
    $receiptTotal = (float) $receipt->receipt_total;
    $currency = $receipt->receipt_currency ?? 'USD';

    $receiptTotalCents = (int) round($receiptTotal * 100);
    $totalReceiptValueCents += $receiptTotalCents;

    echo "\nReceipt Taxes:\n";
    print_r($receiptTaxes);
    
    echo "\nReceipt Payments:\n";
    print_r($receiptPayments);

    // A) SaleByTax Counters
    foreach ($receiptTaxes as $tax) {
        $taxPercent = (float) ($tax['taxPercent'] ?? 0);
        $taxID = (int) ($tax['taxID'] ?? 1);
        $salesAmountWithTax = (float) ($tax['salesAmountWithTax'] ?? 0);
        $salesAmountCents = (int) round($salesAmountWithTax * 100);

        if ($salesAmountCents <= 0) {
            continue;
        }

        foreach ($receiptPayments as $payment) {
            $moneyType = $payment['moneyTypeCode'] ?? 'Cash';
            $paymentAmount = (float) ($payment['paymentAmount'] ?? 0);
            $paymentAmountCents = (int) round($paymentAmount * 100);

            if ($paymentAmountCents <= 0) {
                continue;
            }

            $proportion = $receiptTotalCents > 0 ? $paymentAmountCents / $receiptTotalCents : 0;
            $allocatedAmountCents = (int) round($salesAmountCents * $proportion);

            if ($allocatedAmountCents <= 0) {
                continue;
            }

            $taxKey = "SaleByTax_{$currency}_{$taxID}_" . number_format($taxPercent, 2, '_', '') . "_{$moneyType}";
            
            if (!isset($counters[$taxKey])) {
                $counters[$taxKey] = [
                    'fiscalCounterType' => 'SaleByTax',
                    'fiscalCounterCurrency' => $currency,
                    'fiscalCounterTaxPercent' => $taxPercent,
                    'fiscalCounterTaxID' => $taxID,
                    'fiscalCounterMoneyType' => $moneyType,
                    'fiscalCounterValueCents' => 0,
                ];
            }
            $counters[$taxKey]['fiscalCounterValueCents'] += $allocatedAmountCents;
        }
    }

    // B) SaleByMoneyType Counters
    foreach ($receiptPayments as $payment) {
        $moneyType = $payment['moneyTypeCode'] ?? 'Cash';
        $paymentAmount = (float) ($payment['paymentAmount'] ?? 0);
        $paymentAmountCents = (int) round($paymentAmount * 100);

        if ($paymentAmountCents <= 0) {
            continue;
        }

        $moneyKey = "SaleByMoneyType_{$currency}_{$moneyType}";
        
        if (!isset($counters[$moneyKey])) {
            $counters[$moneyKey] = [
                'fiscalCounterType' => 'SaleByMoneyType',
                'fiscalCounterCurrency' => $currency,
                'fiscalCounterMoneyType' => $moneyType,
                'fiscalCounterValueCents' => 0,
            ];
        }
        $counters[$moneyKey]['fiscalCounterValueCents'] += $paymentAmountCents;
    }

    // C) SalesTotal Counters
    if ($receiptTotalCents > 0) {
        $totalKey = "SalesTotal_{$currency}";
        
        if (!isset($counters[$totalKey])) {
            $counters[$totalKey] = [
                'fiscalCounterType' => 'SalesTotal',
                'fiscalCounterCurrency' => $currency,
                'fiscalCounterValueCents' => 0,
            ];
        }
        $counters[$totalKey]['fiscalCounterValueCents'] += $receiptTotalCents;
    }
    
    echo "\n";
}

// Convert cents back to decimal
foreach ($counters as &$counter) {
    $counter['fiscalCounterValue'] = round($counter['fiscalCounterValueCents'] / 100, 2);
    unset($counter['fiscalCounterValueCents']);
}
unset($counter);

$fiscalCounters = array_values($counters);

echo "\n=== Generated Fiscal Day Counters ===\n\n";
echo json_encode($fiscalCounters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n\n";

echo "=== Counter Summary ===\n";
$totalSalesByTax = 0;
$totalSalesByMoneyType = 0;
$totalSalesTotal = 0;

foreach ($fiscalCounters as $counter) {
    if ($counter['fiscalCounterType'] === 'SaleByTax') {
        $totalSalesByTax += $counter['fiscalCounterValue'];
    } elseif ($counter['fiscalCounterType'] === 'SaleByMoneyType') {
        $totalSalesByMoneyType += $counter['fiscalCounterValue'];
    } elseif ($counter['fiscalCounterType'] === 'SalesTotal') {
        $totalSalesTotal += $counter['fiscalCounterValue'];
    }
}

$totalReceiptValue = round($totalReceiptValueCents / 100, 2);

echo "Total Receipt Value: \${$totalReceiptValue}\n";
echo "Total SaleByTax: \${$totalSalesByTax}\n";
echo "Total SaleByMoneyType: \${$totalSalesByMoneyType}\n";
echo "Total SalesTotal: \${$totalSalesTotal}\n\n";

echo "=== Validation ===\n";
echo "SalesTotal matches receipts: " . (abs($totalSalesTotal - $totalReceiptValue) <= 0.01 ? "✓ PASS" : "✗ FAIL") . "\n";
echo "SaleByMoneyType matches receipts: " . (abs($totalSalesByMoneyType - $totalReceiptValue) <= 0.01 ? "✓ PASS" : "✗ FAIL") . "\n";
echo "\n";
