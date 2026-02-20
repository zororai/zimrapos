# Fiscal Day Close - Payload & Signature Debug Guide

This document shows how to debug the fiscal day close process by examining the payload and signature.

## Enable Debug Logging

Add the following to your `.env` file to enable detailed logging:

```env
LOG_LEVEL=debug
```

## Sample Close Day Payload

### Before Signing

```json
{
  "fiscalDayNo": 1,
  "fiscalDayCounters": [
    {
      "fiscalCounterType": "SaleByTax",
      "fiscalCounterCurrency": "USD",
      "fiscalCounterTaxPercent": 15,
      "fiscalCounterTaxID": 1,
      "fiscalCounterMoneyType": "Cash",
      "fiscalCounterValue": 125.50
    },
    {
      "fiscalCounterType": "SaleByTax",
      "fiscalCounterCurrency": "USD",
      "fiscalCounterTaxPercent": 0,
      "fiscalCounterTaxID": 2,
      "fiscalCounterMoneyType": "Cash",
      "fiscalCounterValue": 50.00
    }
  ],
  "receiptCounter": 7
}
```

### After Signing (Sent to ZIMRA)

```json
{
  "fiscalDayNo": 1,
  "fiscalDayCounters": [
    {
      "fiscalCounterType": "SaleByTax",
      "fiscalCounterCurrency": "USD",
      "fiscalCounterTaxPercent": 15,
      "fiscalCounterTaxID": 1,
      "fiscalCounterMoneyType": "Cash",
      "fiscalCounterValue": 125.50
    },
    {
      "fiscalCounterType": "SaleByTax",
      "fiscalCounterCurrency": "USD",
      "fiscalCounterTaxPercent": 0,
      "fiscalCounterTaxID": 2,
      "fiscalCounterMoneyType": "Cash",
      "fiscalCounterValue": 50.00
    }
  ],
  "receiptCounter": 7,
  "fiscalDayDeviceSignature": {
    "hash": "kH7vQx3mP9sLwN2jR5tY8uB1cD4fG6hJ0kM3nO5pQ7r=",
    "signature": "MEUCIQDx7B2kL9mN3pR5sT8vW1xY4zA7cD0fG3hJ6kM9nP2qRwIgS5tU8vX1yB4cE7fH0jK3mN6pQ9rS2uV5wX8yA1bC4dE="
  }
}
```

## Signature Generation Process

### Step 1: JSON Encode (Before Signature)

```php
$payload = [
    'fiscalDayNo' => 1,
    'fiscalDayCounters' => [...],
    'receiptCounter' => 7,
];

$json = json_encode($payload, JSON_UNESCAPED_SLASHES);
```

**Output:**
```
{"fiscalDayNo":1,"fiscalDayCounters":[{"fiscalCounterType":"SaleByTax","fiscalCounterCurrency":"USD","fiscalCounterTaxPercent":15,"fiscalCounterTaxID":1,"fiscalCounterMoneyType":"Cash","fiscalCounterValue":125.5},{"fiscalCounterType":"SaleByTax","fiscalCounterCurrency":"USD","fiscalCounterTaxPercent":0,"fiscalCounterTaxID":2,"fiscalCounterMoneyType":"Cash","fiscalCounterValue":50}],"receiptCounter":7}
```

### Step 2: Generate SHA256 Hash

```php
$hashBinary = hash('sha256', $json, true);  // Binary output
$hashBase64 = base64_encode($hashBinary);   // Base64 for API
```

**Output:**
```
Hash (hex): 907eef431de63fdb0bc0dd8a3479b598f2e07571c0f8e19fc924b337ce82f0ab
Hash (base64): kH7vQx3mP9sLwN2jR5tY8uB1cD4fG6hJ0kM3nO5pQ7r=
```

### Step 3: Sign with ECDSA

```php
$privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));

// IMPORTANT: Sign the original JSON string, NOT the hash
// OPENSSL_ALGO_SHA256 tells openssl to hash internally before signing
openssl_sign($json, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);

$signatureBase64 = base64_encode($signatureBinary);
```

**Output:**
```
Signature (base64): MEUCIQDx7B2kL9mN3pR5sT8vW1xY4zA7cD0fG3hJ6kM9nP2qRwIgS5tU8vX1yB4cE7fH0jK3mN6pQ9rS2uV5wX8yA1bC4dE=
```

## Log Output Example

When close day runs, check `storage/logs/laravel.log`:

```
[2026-02-20 07:30:00] local.INFO: ZIMRA CloseDay Request {"device_id":32558,"payload":{"fiscalDayNo":1,"fiscalDayCounters":[...],"receiptCounter":7}}
[2026-02-20 07:30:00] local.DEBUG: ZIMRA Signature Generated {"json_length":245,"hash":"kH7vQx3mP9sLwN2jR5tY8uB1cD4fG6hJ0kM3nO5pQ7r=","signature_length":96}
[2026-02-20 07:30:01] local.INFO: ZIMRA Fiscal Day Closed {"fiscal_day_no":1,"operation_id":"0HNJFKSARI420:00000001"}
```

### On Error

```
[2026-02-20 07:30:01] local.ERROR: ZIMRA CloseDay Failed {"status":400,"body":{"fiscalDayStatus":"FiscalDayCloseFailed","fiscalDayClosingErrorCode":"BadCertificateSignature"}}
```

## Common Signature Issues

### Issue: BadCertificateSignature

**Cause 1: Double Hashing (FIXED)**
```php
// WRONG - This double-hashes the data
openssl_sign($hashBinary, $sig, $key, OPENSSL_ALGO_SHA256);

// CORRECT - Pass original JSON, openssl_sign hashes internally
openssl_sign($json, $sig, $key, OPENSSL_ALGO_SHA256);
```

**Cause 2: Wrong Key**
- Ensure `device_private.key` matches the registered certificate
- Re-register device if keys are mismatched

**Cause 3: JSON Encoding Differences**
- Use `JSON_UNESCAPED_SLASHES` flag
- Ensure no extra whitespace or formatting
- Numbers should not have unnecessary decimals

### Issue: Signature Verification Failed

To manually verify a signature:

```php
// Load the certificate
$cert = openssl_x509_read(file_get_contents('device_certificate.pem'));
$publicKey = openssl_pkey_get_public($cert);

// Verify
$isValid = openssl_verify($json, $signatureBinary, $publicKey, OPENSSL_ALGO_SHA256);
// Returns: 1 = valid, 0 = invalid, -1 = error
```

## Debug Code Snippet

Add this to `ZimraDeviceService.php` → `signData()` for debugging:

```php
private function signData(array $data): array
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    
    // DEBUG: Log the JSON being signed
    Log::debug('ZIMRA Sign Data - JSON', ['json' => $json, 'length' => strlen($json)]);

    $hashBinary = hash('sha256', $json, true);
    $hashBase64 = base64_encode($hashBinary);
    
    // DEBUG: Log the hash
    Log::debug('ZIMRA Sign Data - Hash', [
        'hash_hex' => bin2hex($hashBinary),
        'hash_base64' => $hashBase64
    ]);

    $privateKeyPath = storage_path('app/zimra/device_private.key');
    $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));

    if (!$privateKey) {
        throw new \Exception('Failed to load private key for signing.');
    }

    openssl_sign($json, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);
    $signatureBase64 = base64_encode($signatureBinary);
    
    // DEBUG: Log the signature
    Log::debug('ZIMRA Sign Data - Signature', [
        'signature_base64' => $signatureBase64,
        'signature_length' => strlen($signatureBinary)
    ]);

    return [
        'hash' => $hashBase64,
        'signature' => $signatureBase64,
    ];
}
```

## Viewing Logs

```bash
# Tail the log file
tail -f storage/logs/laravel.log

# Filter for ZIMRA entries
grep "ZIMRA" storage/logs/laravel.log

# Windows PowerShell
Get-Content storage/logs/laravel.log -Tail 50 -Wait | Select-String "ZIMRA"
```

## Testing Signature Locally

Create a test script `test_signature.php`:

```php
<?php
require 'vendor/autoload.php';

$payload = [
    'fiscalDayNo' => 1,
    'fiscalDayCounters' => [],
    'receiptCounter' => 0,
];

$json = json_encode($payload, JSON_UNESCAPED_SLASHES);
echo "JSON: $json\n";
echo "JSON Length: " . strlen($json) . "\n\n";

$hash = hash('sha256', $json, true);
echo "Hash (hex): " . bin2hex($hash) . "\n";
echo "Hash (base64): " . base64_encode($hash) . "\n\n";

$privateKey = openssl_pkey_get_private(
    file_get_contents('storage/app/zimra/device_private.key')
);

if (!$privateKey) {
    die("Failed to load private key\n");
}

openssl_sign($json, $signature, $privateKey, OPENSSL_ALGO_SHA256);
echo "Signature (base64): " . base64_encode($signature) . "\n";
echo "Signature Length: " . strlen($signature) . " bytes\n";
```

Run with:
```bash
php test_signature.php
```
