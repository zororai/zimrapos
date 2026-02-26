<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FiscalDay;
use App\Models\Receipt;
use App\Models\ZimraConfig;
use Illuminate\Support\Facades\Storage;

echo "=== Generating CloseDay Payload for Postman ===\n\n";

$deviceId = 32558;
$fiscalDayNo = 11;

// Get fiscal day
$fiscalDay = FiscalDay::where('device_id', $deviceId)
    ->where('fiscal_day_no', $fiscalDayNo)
    ->first();

if (!$fiscalDay) {
    echo "❌ Fiscal day {$fiscalDayNo} not found for device {$deviceId}\n";
    exit(1);
}

echo "Fiscal Day: {$fiscalDay->fiscal_day_no}\n";
echo "Status: {$fiscalDay->status}\n";
echo "Opened: {$fiscalDay->opened_at}\n\n";

// Get receipts
$receipts = Receipt::where('device_id', $deviceId)
    ->where('fiscal_day_no', $fiscalDayNo)
    ->where('is_valid', 1)
    ->orderBy('created_at', 'asc')
    ->get();

echo "Valid Receipts: {$receipts->count()}\n\n";

if ($receipts->isEmpty()) {
    echo "⚠️  No valid receipts found for this fiscal day\n";
}

// Calculate receiptCounter
$lastReceipt = $receipts->sortByDesc('receipt_counter')->first();
$receiptCounter = $lastReceipt ? $lastReceipt->receipt_counter : 0;

echo "Receipt Counter: {$receiptCounter}\n\n";

// Calculate fiscalDayCounters
$counters = [];
$totalReceiptValue = 0;

foreach ($receipts as $receipt) {
    $receiptTaxes = $receipt->receipt_taxes ?? [];
    $receiptTotal = (float) $receipt->receipt_total;
    $currency = $receipt->receipt_currency ?? 'USD';
    
    $totalReceiptValue += $receiptTotal;
    
    foreach ($receiptTaxes as $tax) {
        $taxPercent = (float) ($tax['taxPercent'] ?? 0);
        $taxID = (int) ($tax['taxID'] ?? 1);
        $salesAmountWithTax = (float) ($tax['salesAmountWithTax'] ?? 0);
        
        $taxKey = "SaleByTax_{$taxID}_{$taxPercent}";
        if (!isset($counters[$taxKey])) {
            $counters[$taxKey] = [
                'fiscalCounterType' => 'SaleByTax',
                'fiscalCounterCurrency' => $currency,
                'fiscalCounterTaxPercent' => $taxPercent,
                'fiscalCounterTaxID' => $taxID,
                'fiscalCounterValue' => 0,
            ];
        }
        $counters[$taxKey]['fiscalCounterValue'] += $salesAmountWithTax;
    }
}

// Round counter values
foreach ($counters as &$counter) {
    $counter['fiscalCounterValue'] = round($counter['fiscalCounterValue'], 2);
}
unset($counter);

// Filter out zero-value counters
$filteredCounters = array_filter($counters, function ($c) {
    return $c['fiscalCounterValue'] > 0;
});

$fiscalDayCounters = array_values($filteredCounters);

echo "Fiscal Day Counters:\n";
foreach ($fiscalDayCounters as $counter) {
    echo "  - {$counter['fiscalCounterType']}: Tax {$counter['fiscalCounterTaxID']} ({$counter['fiscalCounterTaxPercent']}%) = {$counter['fiscalCounterCurrency']} {$counter['fiscalCounterValue']}\n";
}
echo "\n";

// Build payload
$payload = [
    'fiscalDayNo' => $fiscalDay->fiscal_day_no,
    'fiscalDayDate' => $fiscalDay->opened_at->format('Y-m-d\TH:i:s'),
    'receiptCounter' => $receiptCounter,
    'fiscalDayCounters' => $fiscalDayCounters,
];

echo "=== PAYLOAD (before signature) ===\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Build canonical string for signature
function buildCloseDayCanonicalString($payload, $deviceId)
{
    $parts = [
        $deviceId,
        $payload['fiscalDayNo'],
        $payload['fiscalDayDate'],
        $payload['receiptCounter'],
    ];
    
    // Sort counters by type, taxID, taxPercent for consistency
    $counters = $payload['fiscalDayCounters'];
    usort($counters, function ($a, $b) {
        if ($a['fiscalCounterType'] !== $b['fiscalCounterType']) {
            return strcmp($a['fiscalCounterType'], $b['fiscalCounterType']);
        }
        if ($a['fiscalCounterTaxID'] !== $b['fiscalCounterTaxID']) {
            return $a['fiscalCounterTaxID'] <=> $b['fiscalCounterTaxID'];
        }
        return $a['fiscalCounterTaxPercent'] <=> $b['fiscalCounterTaxPercent'];
    });
    
    foreach ($counters as $counter) {
        $parts[] = $counter['fiscalCounterType'];
        $parts[] = $counter['fiscalCounterCurrency'];
        $parts[] = number_format($counter['fiscalCounterTaxPercent'], 2, '.', '');
        $parts[] = $counter['fiscalCounterTaxID'];
        $parts[] = number_format($counter['fiscalCounterValue'], 2, '.', '');
    }
    
    return implode('|', $parts);
}

$canonicalString = buildCloseDayCanonicalString($payload, $deviceId);

echo "=== CANONICAL STRING ===\n";
echo $canonicalString . "\n\n";

// Sign the canonical string
$zimraConfig = ZimraConfig::getActive();

if (!$zimraConfig || !$zimraConfig->private_key) {
    echo "❌ No ZIMRA config or private key found\n";
    exit(1);
}

// Write private key to temp file
Storage::put('zimra/device_private.key', $zimraConfig->private_key);
$keyPath = storage_path('app/zimra/device_private.key');

$privateKey = openssl_pkey_get_private(file_get_contents($keyPath));

if (!$privateKey) {
    echo "❌ Failed to load private key\n";
    echo "OpenSSL Error: " . openssl_error_string() . "\n";
    exit(1);
}

$signature = '';
$success = openssl_sign($canonicalString, $signature, $privateKey, OPENSSL_ALGO_SHA256);

if (!$success) {
    echo "❌ Failed to sign canonical string\n";
    echo "OpenSSL Error: " . openssl_error_string() . "\n";
    exit(1);
}

$base64Signature = base64_encode($signature);

echo "=== SIGNATURE (Base64) ===\n";
echo $base64Signature . "\n\n";

// Add signature to payload
$payload['fiscalDayDeviceSignature'] = $base64Signature;

echo "=== FINAL PAYLOAD (with signature) ===\n";
$finalJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
echo $finalJson . "\n\n";

// Get ZIMRA config for Postman
echo "=== POSTMAN CONFIGURATION ===\n";
echo "URL: {$zimraConfig->base_url}/Device/v1/{$deviceId}/CloseDay\n";
echo "Method: POST\n";
echo "Headers:\n";
echo "  DeviceModelName: {$zimraConfig->device_model}\n";
echo "  DeviceModelVersion: {$zimraConfig->device_version}\n";
echo "  Content-Type: application/json\n";
echo "  Accept: application/json\n\n";

echo "mTLS Certificates:\n";
echo "  Certificate: storage/app/zimra/device_certificate.pem\n";
echo "  Private Key: storage/app/zimra/device_private.key\n\n";

// Write certificate to file
if ($zimraConfig->certificate) {
    Storage::put('zimra/device_certificate.pem', $zimraConfig->certificate);
    echo "✓ Certificate written to: " . storage_path('app/zimra/device_certificate.pem') . "\n";
}
if ($zimraConfig->private_key) {
    echo "✓ Private key written to: " . storage_path('app/zimra/device_private.key') . "\n";
}

echo "\n=== COPY THIS JSON FOR POSTMAN BODY ===\n";
echo $finalJson . "\n";

echo "\n✅ Payload generated successfully!\n";
