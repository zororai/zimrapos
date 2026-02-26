<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\FiscalDay;
use App\Models\Receipt;
use App\Models\ZimraConfig;

echo "=== Testing Complete Counter Set (4 Counter Types) ===\n\n";

$deviceId = 32558;
$fiscalDayNo = 12;

$fiscalDay = FiscalDay::where('fiscal_day_no', $fiscalDayNo)
    ->where('device_id', $deviceId)
    ->first();

if (!$fiscalDay) {
    echo "ERROR: Fiscal day {$fiscalDayNo} not found\n";
    exit(1);
}

$receipts = Receipt::where('device_id', $deviceId)
    ->where('fiscal_day_no', $fiscalDayNo)
    ->where('is_valid', true)
    ->get();

echo "Device ID: {$deviceId}\n";
echo "Fiscal Day: {$fiscalDayNo}\n";
echo "Receipt Count: {$receipts->count()}\n\n";

$receiptCounter = Receipt::where('device_id', $deviceId)
    ->where('fiscal_day_no', $fiscalDayNo)
    ->where('is_valid', true)
    ->max('receipt_counter') ?? 0;

// Generate all 4 counter types
$counters = [];
$totalReceiptValueCents = 0;

foreach ($receipts as $receipt) {
    $receiptTaxes = $receipt->receipt_taxes ?? [];
    $receiptPayments = $receipt->receipt_payments ?? [];
    $receiptTotal = (float) $receipt->receipt_total;
    $currency = $receipt->receipt_currency ?? 'USD';

    $receiptTotalCents = (int) round($receiptTotal * 100);
    $totalReceiptValueCents += $receiptTotalCents;

    // A) SaleByTax - NO moneyType
    foreach ($receiptTaxes as $tax) {
        $taxPercent = (float) ($tax['taxPercent'] ?? 0);
        $taxID = (int) ($tax['taxID'] ?? 1);
        $salesAmountWithTax = (float) ($tax['salesAmountWithTax'] ?? 0);
        $salesAmountCents = (int) round($salesAmountWithTax * 100);

        if ($salesAmountCents <= 0) continue;

        $taxKey = "SaleByTax_{$currency}_{$taxID}_" . number_format($taxPercent, 2, '_', '');
        
        if (!isset($counters[$taxKey])) {
            $counters[$taxKey] = [
                'fiscalCounterType' => 'SaleByTax',
                'fiscalCounterCurrency' => $currency,
                'fiscalCounterTaxPercent' => $taxPercent,
                'fiscalCounterTaxID' => $taxID,
                'fiscalCounterValueCents' => 0,
            ];
        }
        $counters[$taxKey]['fiscalCounterValueCents'] += $salesAmountCents;
    }

    // B) SaleByMoneyType
    foreach ($receiptPayments as $payment) {
        $moneyType = $payment['moneyTypeCode'] ?? 'Cash';
        $paymentAmount = (float) ($payment['paymentAmount'] ?? 0);
        $paymentAmountCents = (int) round($paymentAmount * 100);

        if ($paymentAmountCents <= 0) continue;

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

    // C) SalesTotal
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

    // D) BalanceByMoneyType
    foreach ($receiptPayments as $payment) {
        $moneyType = $payment['moneyTypeCode'] ?? 'Cash';
        $paymentAmount = (float) ($payment['paymentAmount'] ?? 0);
        $paymentAmountCents = (int) round($paymentAmount * 100);

        if ($paymentAmountCents <= 0) continue;

        $balanceKey = "BalanceByMoneyType_{$currency}_{$moneyType}";
        
        if (!isset($counters[$balanceKey])) {
            $counters[$balanceKey] = [
                'fiscalCounterType' => 'BalanceByMoneyType',
                'fiscalCounterCurrency' => $currency,
                'fiscalCounterMoneyType' => $moneyType,
                'fiscalCounterValueCents' => 0,
            ];
        }
        $counters[$balanceKey]['fiscalCounterValueCents'] += $paymentAmountCents;
    }
}

// Convert cents back to decimal
foreach ($counters as &$counter) {
    $counter['fiscalCounterValue'] = round($counter['fiscalCounterValueCents'] / 100, 2);
    unset($counter['fiscalCounterValueCents']);
}
unset($counter);

$fiscalCounters = array_values($counters);

// Sort deterministically
usort($fiscalCounters, function ($a, $b) {
    $typeOrder = [
        'SaleByTax' => 1,
        'SaleByMoneyType' => 2,
        'SalesTotal' => 3,
        'BalanceByMoneyType' => 4,
    ];
    $typeCompare = ($typeOrder[$a['fiscalCounterType']] ?? 99) <=> ($typeOrder[$b['fiscalCounterType']] ?? 99);
    if ($typeCompare !== 0) return $typeCompare;
    
    $currencyCompare = strcmp($a['fiscalCounterCurrency'], $b['fiscalCounterCurrency']);
    if ($currencyCompare !== 0) return $currencyCompare;
    
    $aTaxID = $a['fiscalCounterTaxID'] ?? null;
    $bTaxID = $b['fiscalCounterTaxID'] ?? null;
    
    if ($aTaxID !== null && $bTaxID !== null) {
        return $aTaxID <=> $bTaxID;
    }
    
    return 0;
});

echo "=== Generated Complete Counter Set (4 Types) ===\n\n";
echo json_encode($fiscalCounters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
echo "\n\n";

// Build payload
$fiscalDayClosed = now()->format('Y-m-d\TH:i:s');

$payload = [
    'deviceID' => $deviceId,
    'fiscalDayNo' => $fiscalDayNo,
    'fiscalDayCounters' => $fiscalCounters,
    'receiptCounter' => $receiptCounter,
    'fiscalDayClosed' => $fiscalDayClosed,
];

// Build canonical string
$canonicalParts = [];
$canonicalParts[] = (string) $deviceId;
$canonicalParts[] = (string) $fiscalDayNo;
$canonicalParts[] = $fiscalDayClosed;
$canonicalParts[] = (string) $receiptCounter;

$counterStrings = [];
foreach ($fiscalCounters as $counter) {
    $parts = [];
    $parts[] = strtoupper($counter['fiscalCounterType']);
    $parts[] = strtoupper($counter['fiscalCounterCurrency']);
    
    if (isset($counter['fiscalCounterTaxPercent'])) {
        $parts[] = number_format($counter['fiscalCounterTaxPercent'], 2, '.', '');
    } elseif (isset($counter['fiscalCounterMoneyType'])) {
        $parts[] = strtoupper($counter['fiscalCounterMoneyType']);
    }
    
    $valueInCents = (int) round($counter['fiscalCounterValue'] * 100);
    $parts[] = (string) $valueInCents;
    
    $counterStrings[] = implode('', $parts);
}
$countersString = implode('', $counterStrings);
$canonicalParts[] = $countersString;

$canonicalString = implode('', $canonicalParts);

// Sign
$zimraConfig = ZimraConfig::getActive();
$privateKey = openssl_pkey_get_private($zimraConfig->private_key);

$hash = hash('sha256', $canonicalString, true);
$hashBase64 = base64_encode($hash);

$signature = '';
openssl_sign($canonicalString, $signature, $privateKey, OPENSSL_ALGO_SHA256);
$signatureBase64 = base64_encode($signature);

$payload['fiscalDayDeviceSignature'] = [
    'hash' => $hashBase64,
    'signature' => $signatureBase64,
];

echo "=== Final Payload (Complete 4 Counter Types) ===\n\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
echo "\n\n";

echo "=== Canonical String ===\n";
echo "Full: {$canonicalString}\n";
echo "Length: " . strlen($canonicalString) . "\n\n";

echo "=== Breakdown ===\n";
echo "deviceID: {$canonicalParts[0]}\n";
echo "fiscalDayNo: {$canonicalParts[1]}\n";
echo "fiscalDayClosed: {$canonicalParts[2]}\n";
echo "receiptCounter: {$canonicalParts[3]}\n";
echo "fiscalDayCounters: {$canonicalParts[4]}\n\n";

echo "=== Counter Count ===\n";
echo "Total counters: " . count($fiscalCounters) . "\n";
echo "Expected: 4 (SaleByTax + SaleByMoneyType + SalesTotal + BalanceByMoneyType)\n\n";

$totalSalesByTax = 0;
$totalSalesByMoneyType = 0;
$totalSalesTotal = 0;
$totalBalanceByMoneyType = 0;

foreach ($fiscalCounters as $counter) {
    if ($counter['fiscalCounterType'] === 'SaleByTax') {
        $totalSalesByTax += $counter['fiscalCounterValue'];
    } elseif ($counter['fiscalCounterType'] === 'SaleByMoneyType') {
        $totalSalesByMoneyType += $counter['fiscalCounterValue'];
    } elseif ($counter['fiscalCounterType'] === 'SalesTotal') {
        $totalSalesTotal += $counter['fiscalCounterValue'];
    } elseif ($counter['fiscalCounterType'] === 'BalanceByMoneyType') {
        $totalBalanceByMoneyType += $counter['fiscalCounterValue'];
    }
}

$totalReceiptValue = round($totalReceiptValueCents / 100, 2);

echo "=== Validation ===\n";
echo "Total Receipt Value: \${$totalReceiptValue}\n";
echo "Total SaleByTax: \${$totalSalesByTax}\n";
echo "Total SaleByMoneyType: \${$totalSalesByMoneyType}\n";
echo "Total SalesTotal: \${$totalSalesTotal}\n";
echo "Total BalanceByMoneyType: \${$totalBalanceByMoneyType}\n\n";

echo "SaleByTax matches: " . (abs($totalSalesByTax - $totalReceiptValue) <= 0.01 ? "✓ PASS" : "✗ FAIL") . "\n";
echo "SaleByMoneyType matches: " . (abs($totalSalesByMoneyType - $totalReceiptValue) <= 0.01 ? "✓ PASS" : "✗ FAIL") . "\n";
echo "SalesTotal matches: " . (abs($totalSalesTotal - $totalReceiptValue) <= 0.01 ? "✓ PASS" : "✗ FAIL") . "\n";
echo "BalanceByMoneyType matches: " . (abs($totalBalanceByMoneyType - $totalReceiptValue) <= 0.01 ? "✓ PASS" : "✗ FAIL") . "\n";
echo "\n✓ Ready for CloseDay test with complete counter model\n";
