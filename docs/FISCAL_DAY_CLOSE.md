# Fiscal Day Close Process

This document describes how the ZIMRA fiscal day close process works in this system.

## Overview

A fiscal day must be closed before starting a new one. The close process sends all accumulated fiscal counters (sales totals, taxes collected, etc.) to ZIMRA and marks the day as complete.

## Close Day Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                     Close Fiscal Day                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. User clicks "Close Fiscal Day"                              │
│           ↓                                                      │
│  2. Load current open fiscal day from database                  │
│           ↓                                                      │
│  3. Build payload with fiscal counters                          │
│           ↓                                                      │
│  4. Sign payload with device private key                        │
│           ↓                                                      │
│  5. Send to ZIMRA API via mTLS                                  │
│           ↓                                                      │
│  6a. Success → Update database, mark day closed                 │
│  6b. Failure → Show error, offer Force Close option             │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## API Endpoint

**ZIMRA API:** `POST /Device/v1/{deviceId}/CloseDay`

**Request Headers:**
- `DeviceModelName` - Device model (e.g., "Server")
- `DeviceModelVersion` - Device version (e.g., "v1")
- `Content-Type: application/json`
- mTLS certificate authentication

## Payload Structure

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
      "fiscalCounterValue": 100.00
    }
  ],
  "receiptCounter": 5,
  "fiscalDayDeviceSignature": {
    "hash": "base64-encoded-sha256-hash",
    "signature": "base64-encoded-ecdsa-signature"
  }
}
```

## Signature Generation

The `fiscalDayDeviceSignature` is generated as follows:

1. **Build payload** - Create JSON object with `fiscalDayNo`, `fiscalDayCounters`, and `receiptCounter`
2. **JSON encode** - Serialize with `JSON_UNESCAPED_SLASHES`
3. **Hash** - Generate SHA256 hash of the JSON string (binary)
4. **Sign** - Sign the JSON string using ECDSA with SHA256 algorithm
5. **Encode** - Base64 encode both hash and signature

```php
// Pseudo-code
$json = json_encode($payload, JSON_UNESCAPED_SLASHES);
$hash = base64_encode(hash('sha256', $json, true));
openssl_sign($json, $signature, $privateKey, OPENSSL_ALGO_SHA256);
$signature = base64_encode($signature);
```

**Important:** The signature is computed over the original JSON string, not the hash. The `OPENSSL_ALGO_SHA256` flag tells `openssl_sign` to hash internally before signing.

## Success Response

```json
{
  "fiscalDayStatus": "FiscalDayClosed",
  "lastReceiptGlobalNo": 7,
  "lastFiscalDayNo": 1,
  "operationID": "0HNJFKSARI420:00000001"
}
```

## Error Response

```json
{
  "fiscalDayStatus": "FiscalDayCloseFailed",
  "lastReceiptGlobalNo": 7,
  "lastFiscalDayNo": 1,
  "fiscalDayClosingErrorCode": "BadCertificateSignature",
  "operationID": "0HNJFKSARI420:00000001"
}
```

### Common Error Codes

| Error Code | Description | Solution |
|------------|-------------|----------|
| `BadCertificateSignature` | Invalid digital signature | Check signing logic, ensure correct key is used |
| `InvalidFiscalDayNo` | Wrong fiscal day number | Sync with ZIMRA status |
| `FiscalDayNotOpen` | Trying to close already closed day | Check device status first |
| `InvalidCertificate` | Certificate issue | Re-register device or upload valid certificate |

## Database Updates

On successful close:

```php
$fiscalDay->update([
    'status' => 'closed',
    'closed_at' => now(),
    'close_operation_id' => $responseData['operationID'],
    'close_response' => $responseData,
]);
```

## Force Close (Local Only)

When ZIMRA API is unavailable or returns errors, users can force close locally:

```
POST /zimra/force-close-day
```

This marks the fiscal day as closed in the local database **without** notifying ZIMRA. Use this only when:
- ZIMRA API is down
- There's a persistent signature/certificate error
- You need to proceed with operations

**Warning:** Force closed days are not synchronized with ZIMRA. You may need to reconcile manually later.

## Code Locations

| Component | File |
|-----------|------|
| Service Method | `app/Services/ZimraDeviceService.php` → `closeDay()` |
| Force Close | `app/Services/ZimraDeviceService.php` → `forceCloseDay()` |
| Controller | `app/Http/Controllers/ZimraController.php` → `closeDay()` |
| Route | `routes/web.php` → `POST /zimra/close-day` |
| UI | `resources/views/zimra.blade.php` |

## Auto Close

The system has an automatic close job that runs daily:

```php
// app/Jobs/ZimraAutoCloseDayJob.php
// Runs at 11 PM via scheduler
```

This prevents forgotten open fiscal days from blocking operations the next day.

## Troubleshooting

### "BadCertificateSignature" Error

1. **Check signature generation** - Ensure signing the JSON string, not the hash
2. **Verify certificate** - Ensure certificate matches the private key
3. **Re-register device** - If certificate is corrupted, re-register with ZIMRA

### "No open fiscal day found"

1. Check if day was already closed
2. Use `GET /zimra/fiscal-day` to check current status
3. Open a new fiscal day if needed

### Force Close Not Syncing

Force closed days are local only. To sync:
1. Open a new fiscal day
2. ZIMRA will assign the next fiscal day number
3. Previous force-closed day remains in local records only
