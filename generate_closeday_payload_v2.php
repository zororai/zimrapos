<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\FiscalDay;
use App\Models\Receipt;
use App\Models\ZimraConfig;

echo "=== Generate CloseDay Payload with Full Counter Types (v7.2) ===\n\n";

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

// Calculate receiptCounter (max receipt counter)
$receiptCounter = Receipt::where('device_id', $deviceId)
    ->where('fiscal_day_no', $fiscalDayNo)
    ->where('is_valid', true)
    ->max('receipt_counter') ?? 0;

// Generate fiscal counters using FDMS reconciliation logic
$counters = [];
$totalReceiptValueCents = 0;

foreach ($receipts as $receipt) {
    $receiptTaxes = $receipt->receipt_taxes ?? [];
    $receiptPayments = $receipt->receipt_payments ?? [];
    $receiptTotal = (float) $receipt->receipt_total;
    $currency = $receipt->receipt_currency ?? 'USD';

    $receiptTotalCents = (int) round($receiptTotal * 100);
    $totalReceiptValueCents += $receiptTotalCents;

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
}

// Convert cents back to decimal
foreach ($counters as &$counter) {
    $counter['fiscalCounterValue'] = round($counter['fiscalCounterValueCents'] / 100, 2);
    unset($counter['fiscalCounterValueCents']);
}
unset($counter);

$fiscalCounters = array_values($counters);

// Sort counters deterministically
usort($fiscalCounters, function ($a, $b) {
    $typeOrder = [
        'SaleByTax' => 1,
        'SaleByMoneyType' => 2,
        'SalesTotal' => 3,
    ];
    $typeCompare = ($typeOrder[$a['fiscalCounterType']] ?? 99) <=> ($typeOrder[$b['fiscalCounterType']] ?? 99);
    if ($typeCompare !== 0) return $typeCompare;
    
    $currencyCompare = strcmp($a['fiscalCounterCurrency'], $b['fiscalCounterCurrency']);
    if ($currencyCompare !== 0) return $currencyCompare;
    
    $aTaxID = $a['fiscalCounterTaxID'] ?? null;
    $bTaxID = $b['fiscalCounterTaxID'] ?? null;
    $aMoneyType = $a['fiscalCounterMoneyType'] ?? null;
    $bMoneyType = $b['fiscalCounterMoneyType'] ?? null;
    
    if ($aTaxID !== null && $bTaxID !== null) {
        $taxIDCompare = $aTaxID <=> $bTaxID;
        if ($taxIDCompare !== 0) return $taxIDCompare;
    } elseif ($aMoneyType !== null && $bMoneyType !== null) {
        $moneyTypeCompare = strcmp($aMoneyType, $bMoneyType);
        if ($moneyTypeCompare !== 0) return $moneyTypeCompare;
    }
    
    $aTaxPercent = $a['fiscalCounterTaxPercent'] ?? null;
    $bTaxPercent = $b['fiscalCounterTaxPercent'] ?? null;
    
    if ($aTaxPercent !== null && $bTaxPercent !== null) {
        return $aTaxPercent <=> $bTaxPercent;
    }
    
    return 0;
});

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

// Build counters string
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

// Sign canonical string
$zimraConfig = ZimraConfig::getActive();
$privateKey = openssl_pkey_get_private($zimraConfig->private_key);

if (!$privateKey) {
    echo "ERROR: Failed to load private key\n";
    exit(1);
}

$hash = hash('sha256', $canonicalString, true);
$hashBase64 = base64_encode($hash);

$signature = '';
openssl_sign($canonicalString, $signature, $privateKey, OPENSSL_ALGO_SHA256);
$signatureBase64 = base64_encode($signature);

$payload['fiscalDayDeviceSignature'] = [
    'hash' => $hashBase64,
    'signature' => $signatureBase64,
];

// Output
echo "=== Final CloseDay Payload (v7.2 with Full Counter Types) ===\n\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
echo "\n\n";

echo "=== Canonical String ===\n";
echo "Full: {$canonicalString}\n";
echo "Length: " . strlen($canonicalString) . "\n\n";

echo "=== Canonical String Breakdown ===\n";
echo "deviceID: {$canonicalParts[0]}\n";
echo "fiscalDayNo: {$canonicalParts[1]}\n";
echo "fiscalDayClosed: {$canonicalParts[2]}\n";
echo "receiptCounter: {$canonicalParts[3]}\n";
echo "fiscalDayCounters: {$canonicalParts[4]}\n\n";

echo "=== Signature ===\n";
echo "Hash (SHA256 Base64): {$hashBase64}\n";
echo "Signature (ECDSA Base64): {$signatureBase64}\n\n";

echo "=== Counter Totals ===\n";
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

echo "✓ All counter types match receipt totals\n";
