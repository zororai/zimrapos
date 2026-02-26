<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\FiscalDay;
use App\Models\Receipt;
use App\Models\ZimraConfig;

echo "=== Testing FIXED Canonical String (fiscalDayDate, not fiscalDayClosed) ===\n\n";

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

// Generate ONLY valid counter types
$counters = [];

foreach ($receipts as $receipt) {
    $receiptTaxes = $receipt->receipt_taxes ?? [];
    $receiptPayments = $receipt->receipt_payments ?? [];
    $currency = $receipt->receipt_currency ?? 'USD';

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

    // B) SaleTaxByTax - taxAmount (skip if 0)
    foreach ($receiptTaxes as $tax) {
        $taxPercent = (float) ($tax['taxPercent'] ?? 0);
        $taxID = (int) ($tax['taxID'] ?? 1);
        $taxAmount = (float) ($tax['taxAmount'] ?? 0);
        $taxAmountCents = (int) round($taxAmount * 100);

        if ($taxAmountCents <= 0) continue;

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

echo "=== Generated Counters ===\n\n";
echo json_encode($fiscalCounters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
echo "\n\n";

// CRITICAL: Use fiscalDayDate (YYYY-MM-DD when opened) for canonical string
// But use fiscalDayClosed (datetime when closed) in payload
$fiscalDayDate = $fiscalDay->opened_at->format('Y-m-d'); // For signature
$fiscalDayClosed = now()->format('Y-m-d\TH:i:s'); // For payload

$payload = [
    'deviceID' => $deviceId,
    'fiscalDayNo' => $fiscalDayNo,
    'fiscalDayCounters' => $fiscalCounters,
    'receiptCounter' => $receiptCounter,
    'fiscalDayClosed' => $fiscalDayClosed,
];

// Build canonical string using fiscalDayDate (NOT fiscalDayClosed)
// Per ZIMRA spec Section 13.3.1: deviceID || fiscalDayNo || fiscalDayDate || fiscalDayCounters
// NOTE: receiptCounter is NOT part of the canonical string!
$canonicalParts = [];
$canonicalParts[] = (string) $deviceId;
$canonicalParts[] = (string) $fiscalDayNo;
$canonicalParts[] = $fiscalDayDate; // YYYY-MM-DD when day was OPENED

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

echo "=== CORRECTED Payload (fiscalDayDate in signature, fiscalDayClosed in payload) ===\n\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
echo "\n\n";

echo "=== Canonical String (FIXED) ===\n";
echo "Full: {$canonicalString}\n";
echo "Length: " . strlen($canonicalString) . "\n\n";

echo "=== Field Breakdown ===\n";
echo "1. deviceID: {$canonicalParts[0]}\n";
echo "2. fiscalDayNo: {$canonicalParts[1]}\n";
echo "3. fiscalDayDate (YYYY-MM-DD when OPENED): {$canonicalParts[2]}\n";
echo "4. fiscalDayCounters: {$canonicalParts[3]}\n\n";
echo "NOTE: receiptCounter ({$receiptCounter}) is in payload but NOT in canonical string!\n\n";

echo "=== CRITICAL DIFFERENCE ===\n";
echo "Payload contains fiscalDayClosed: {$fiscalDayClosed}\n";
echo "Signature uses fiscalDayDate: {$fiscalDayDate}\n\n";

echo "✓ Ready for Postman with CORRECTED signature\n";
