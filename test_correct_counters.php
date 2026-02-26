<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\FiscalDay;
use App\Models\Receipt;
use App\Models\ZimraConfig;

echo "=== Testing CORRECT Counter Types (Per ZIMRA Spec Section 5.4.4) ===\n\n";

$deviceId = 32558;
$fiscalDayNo = 12;

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

// Generate ONLY valid counter types
$counters = [];
$totalReceiptValueCents = 0;

foreach ($receipts as $receipt) {
    $receiptTaxes = $receipt->receipt_taxes ?? [];
    $receiptPayments = $receipt->receipt_payments ?? [];
    $receiptTotal = (float) $receipt->receipt_total;
    $currency = $receipt->receipt_currency ?? 'USD';

    $receiptTotalCents = (int) round($receiptTotal * 100);
    $totalReceiptValueCents += $receiptTotalCents;

    // A) SaleByTax - salesAmountWithTax
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

    // B) SaleTaxByTax - taxAmount
    foreach ($receiptTaxes as $tax) {
        $taxPercent = (float) ($tax['taxPercent'] ?? 0);
        $taxID = (int) ($tax['taxID'] ?? 1);
        $taxAmount = (float) ($tax['taxAmount'] ?? 0);
        $taxAmountCents = (int) round($taxAmount * 100);

        if ($taxAmountCents <= 0) continue; // Skip zero tax (exempt)

        $saleTaxKey = "SaleTaxByTax_{$currency}_{$taxID}_" . number_format($taxPercent, 2, '_', '');
        
        if (!isset($counters[$saleTaxKey])) {
            $counters[$saleTaxKey] = [
                'fiscalCounterType' => 'SaleTaxByTax',
                'fiscalCounterCurrency' => $currency,
                'fiscalCounterTaxPercent' => $taxPercent,
                'fiscalCounterTaxID' => $taxID,
                'fiscalCounterValueCents' => 0,
            ];
        }
        $counters[$saleTaxKey]['fiscalCounterValueCents'] += $taxAmountCents;
    }

    // C) BalanceByMoneyType - paymentAmount
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
        'SaleTaxByTax' => 2,
        'BalanceByMoneyType' => 3,
    ];
    $typeCompare = ($typeOrder[$a['fiscalCounterType']] ?? 99) <=> ($typeOrder[$b['fiscalCounterType']] ?? 99);
    if ($typeCompare !== 0) return $typeCompare;
    
    $currencyCompare = strcmp($a['fiscalCounterCurrency'], $b['fiscalCounterCurrency']);
    if ($currencyCompare !== 0) return $currencyCompare;
    
    return 0;
});

echo "=== Generated Counters (VALID Enum Values Only) ===\n\n";
echo json_encode($fiscalCounters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
echo "\n\n";

// Build payload with closeDayRequest wrapper
$fiscalDayClosed = now()->format('Y-m-d\TH:i:s');

$closeDayRequest = [
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

$closeDayRequest['fiscalDayDeviceSignature'] = [
    'hash' => $hashBase64,
    'signature' => $signatureBase64,
];

// Wrap in closeDayRequest for API
$apiPayload = [
    'closeDayRequest' => $closeDayRequest
];

echo "=== Final API Payload (with closeDayRequest wrapper) ===\n\n";
echo json_encode($apiPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
echo "\n\n";

echo "=== Canonical String ===\n";
echo "Full: {$canonicalString}\n";
echo "Length: " . strlen($canonicalString) . "\n\n";

echo "=== Counter Count ===\n";
echo "Total counters: " . count($fiscalCounters) . "\n";
echo "Expected: 2 (SaleByTax + BalanceByMoneyType) - SaleTaxByTax only if tax > 0\n\n";

echo "✓ Ready for Postman test with VALID enum values\n";
