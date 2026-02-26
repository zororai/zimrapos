<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FiscalDay;
use App\Models\Receipt;
use App\Models\ZimraConfig;
use Illuminate\Support\Facades\Storage;

echo "=== Generating CloseDay Payload for Postman (ZIMRA v7.2) ===\n\n";

$deviceId = 32558;
$fiscalDayNo = 12;

echo "ZIMRA Fiscal Device Gateway API v7.2\n";
echo "Generating v7.2 compliant CloseDay payload\n\n";

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

// Calculate fiscalCounters (v7.2 spec)
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
        
        // v7.2: Group by Type_Currency_TaxID_TaxPercent
        $taxKey = "SaleByTax_{$currency}_{$taxID}_" . number_format($taxPercent, 2, '_', '');
        if (!isset($counters[$taxKey])) {
            $counters[$taxKey] = [
                'fiscalCounterType' => 'SaleByTax',
                'fiscalCounterCurrency' => $currency,
                'fiscalCounterTaxPercent' => $taxPercent,
                'fiscalCounterTaxID' => $taxID,
                'fiscalCounterValue' => 0.0,
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

// v7.2 SPEC: Field name is 'fiscalCounters' NOT 'fiscalDayCounters'
$fiscalCounters = array_values($filteredCounters);

echo "Fiscal Counters (v7.2):\n";
foreach ($fiscalCounters as $counter) {
    echo "  - {$counter['fiscalCounterType']}: Tax {$counter['fiscalCounterTaxID']} ({$counter['fiscalCounterTaxPercent']}%) = {$counter['fiscalCounterCurrency']} {$counter['fiscalCounterValue']}\n";
}
echo "\n";

// Build v7.2 compliant payload
// CRITICAL: fiscalDayClosed is the CLOSING timestamp, not opening
$fiscalDayClosed = date('Y-m-d\TH:i:s');

$payload = [
    'deviceID' => $deviceId,
    'fiscalDayNo' => $fiscalDay->fiscal_day_no,
    'fiscalCounters' => $fiscalCounters,
    'receiptCounter' => $receiptCounter,
    'fiscalDayClosed' => $fiscalDayClosed,
];

echo "=== PAYLOAD (before signature) ===\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Build canonical string for signature (v7.2 spec section 13.3)
function buildCloseDayCanonicalString($payload, $deviceId)
{
    // v7.2 Format: deviceID || fiscalDayNo || fiscalDayClosed || receiptCounter || fiscalCounters
    $parts = [];
    
    // 1. deviceID
    $parts[] = (string) $deviceId;
    
    // 2. fiscalDayNo
    $parts[] = (string) $payload['fiscalDayNo'];
    
    // 3. fiscalDayClosed (ISO datetime)
    $parts[] = $payload['fiscalDayClosed'];
    
    // 4. receiptCounter
    $parts[] = (string) $payload['receiptCounter'];
    
    // 5. fiscalCounters - concatenated string
    $counters = $payload['fiscalCounters'];
    
    // Sort counters per FDMS spec
    usort($counters, function ($a, $b) {
        $typeOrder = ['SaleByTax' => 0, 'SaleTaxByTax' => 1, 'CreditNoteByTax' => 2];
        $typeCompare = ($typeOrder[$a['fiscalCounterType']] ?? 99) <=> ($typeOrder[$b['fiscalCounterType']] ?? 99);
        if ($typeCompare !== 0) return $typeCompare;
        
        $currencyCompare = strcmp($a['fiscalCounterCurrency'], $b['fiscalCounterCurrency']);
        if ($currencyCompare !== 0) return $currencyCompare;
        
        return ($a['fiscalCounterTaxID'] ?? 0) <=> ($b['fiscalCounterTaxID'] ?? 0);
    });
    
    $counterStrings = [];
    foreach ($counters as $counter) {
        $counterParts = [];
        $counterParts[] = strtoupper($counter['fiscalCounterType']);
        $counterParts[] = strtoupper($counter['fiscalCounterCurrency']);
        $counterParts[] = number_format($counter['fiscalCounterTaxPercent'], 2, '.', '');
        $valueInCents = (int) round($counter['fiscalCounterValue'] * 100);
        $counterParts[] = (string) $valueInCents;
        $counterStrings[] = implode('', $counterParts);
    }
    
    $parts[] = implode('', $counterStrings);
    
    // Concatenate without separators
    return implode('', $parts);
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

// v7.2 SPEC: Hash the canonical string for the hash field
$hash = hash('sha256', $canonicalString, true);
$hashBase64 = base64_encode($hash);

echo "=== HASH (Base64 SHA256) ===\n";
echo $hashBase64 . "\n\n";

echo "=== SIGNATURE (Base64) ===\n";
echo $base64Signature . "\n\n";

// v7.2 SPEC: Signature must be an object with hash and signature
$payload['fiscalDayDeviceSignature'] = [
    'hash' => $hashBase64,
    'signature' => $base64Signature,
];

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
