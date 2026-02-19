# ZIMRA Fiscalization Integration

This document describes how the ZIMRA fiscal device integration works in this Laravel application.

## Overview

The ZIMRA integration provides endpoints for fiscal device registration, fiscal day management, receipt submission, and file submission to the Zimbabwe Revenue Authority (ZIMRA) Fiscal Device Management System (FDMS).

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Laravel App                              │
├─────────────────────────────────────────────────────────────────┤
│  Routes (web.php)                                                │
│       ↓                                                          │
│  ZimraController                                                 │
│       ↓                                                          │
│  ZimraDeviceService (mTLS + Signing)                            │
│       ↓                                                          │
│  ZIMRA FDMS API (https://fdmsapitest.zimra.co.zw)               │
└─────────────────────────────────────────────────────────────────┘
```

## Database Models

### ZimraConfig
Stores ZIMRA configuration including:
- `base_url` - ZIMRA API base URL
- `device_model` - Device model name
- `device_version` - Device version
- `device_id` - Registered device ID
- `serial_number` - Device serial number
- `certificate` - Device certificate (PEM)
- `private_key` - Device private key (PEM)
- `reporting_frequency` - Ping frequency in minutes
- `is_active` - Whether this config is active

### FiscalDay
Tracks fiscal day status:
- `fiscal_day_no` - Fiscal day number
- `device_id` - Device ID
- `status` - `open` or `closed`
- `opened_at` - When day was opened
- `closed_at` - When day was closed
- `receipt_counter` - Number of receipts submitted
- `fiscal_counters` - JSON array of tax counters

## API Endpoints

### Configuration Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/zimra/config` | Store new ZIMRA configuration |
| GET | `/zimra/config` | Get active configuration |
| PUT | `/zimra/config/{id}` | Update configuration |

### Device Operations

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/zimra/register` | Register device with ZIMRA |
| GET | `/zimra/device-config` | Get device configuration from ZIMRA |
| GET | `/zimra/status` | Get device status from ZIMRA |

### Fiscal Day Operations

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/zimra/open-day` | Open a new fiscal day |
| POST | `/zimra/close-day` | Close current fiscal day |
| GET | `/zimra/fiscal-day` | Check if fiscal day is open |

### Receipt & File Operations

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/zimra/submit-receipt` | Submit a fiscal receipt |
| POST | `/zimra/submit-file` | Submit a fiscal file |

---

## Workflow

### 1. Initial Setup

```
1. POST /zimra/config
   - Store base_url, device_model, device_version

2. POST /zimra/register
   - Provide device_id, serial_number, activation_key
   - System generates ECC P-256 key pair
   - Generates CSR and sends to ZIMRA
   - Receives and stores certificate
```

### 2. Daily Operations

```
┌─────────────────┐
│  Start of Day   │
└────────┬────────┘
         ↓
┌─────────────────┐
│ POST /open-day  │ ← Opens fiscal day, saves to database
└────────┬────────┘
         ↓
┌─────────────────┐
│ Submit Receipts │ ← Each receipt updates counters
│ POST /submit-   │
│    receipt      │
└────────┬────────┘
         ↓
┌─────────────────┐
│ POST /close-day │ ← Closes day, sends counters to ZIMRA
└────────┬────────┘
         ↓
┌─────────────────┐
│ POST /submit-   │ ← Optional: Submit daily file
│    file         │
└─────────────────┘
```

### 3. Background Jobs

#### ZimraPingJob
- Runs every 5 minutes (configurable via `reporting_frequency`)
- Sends heartbeat to ZIMRA FDMS
- Self-reschedules based on configuration

#### ZimraAutoCloseDayJob
- Runs daily at 11 PM
- Automatically closes any open fiscal day
- Prevents forgotten open days

---

## Receipt Submission

### Request Format

```json
{
  "receiptType": "FiscalInvoice",
  "receiptCurrency": "USD",
  "receiptCounter": 1,
  "receiptGlobalNo": 1,
  "invoiceNo": "INV-001",
  "receiptDate": "2026-02-19T12:00:00",
  "receiptLinesTaxInclusive": true,
  "receiptLines": [
    {
      "receiptLineType": "Sale",
      "receiptLineNo": 1,
      "receiptLineHSCode": "85456852",
      "receiptLineName": "Product Name",
      "receiptLinePrice": 25.00,
      "receiptLineQuantity": 1,
      "receiptLineTotal": 25.00,
      "taxCode": "A",
      "taxPercent": 15,
      "taxID": 1
    }
  ],
  "receiptTaxes": [
    {
      "taxCode": "A",
      "taxPercent": 15,
      "taxID": 1,
      "taxAmount": 3.75,
      "salesAmountWithTax": 28.75
    }
  ],
  "receiptPayments": [
    {
      "moneyTypeCode": "Cash",
      "paymentAmount": 28.75
    }
  ],
  "receiptTotal": 28.75,
  "receiptPrintForm": "Receipt48"
}
```

### Signing Process

1. Remove any existing `receiptDeviceSignature`
2. Add `fiscalDayNo` from current open day
3. JSON encode receipt with `JSON_UNESCAPED_SLASHES`
4. Generate SHA256 hash (binary)
5. Sign hash with ECDSA using device private key
6. Base64 encode hash and signature
7. Add `receiptDeviceSignature` object to receipt

```php
$receiptData['receiptDeviceSignature'] = [
    'hash' => base64_encode($hashBinary),
    'signature' => base64_encode($signatureBinary),
];
```

### Response

```json
{
  "success": true,
  "data": {
    "receiptID": 600,
    "serverDate": "2026-02-19T12:00:02",
    "receiptServerSignature": {
      "certificateThumbprint": "...",
      "hash": "...",
      "signature": "..."
    },
    "operationID": "..."
  },
  "fiscal_day_no": 1
}
```

---

## File Submission

### Request Format

```json
{
  "header": {
    "fiscalDayNo": 1,
    "fiscalDayOpened": "2026-02-19T08:00:00",
    "fileSequence": 1
  },
  "content": {
    "receipts": []
  },
  "footer": {
    "fiscalDayCounters": [],
    "receiptCounter": 0,
    "fiscalDayClosed": "2026-02-19T22:00:00"
  }
}
```

### Important Notes
- Content-Type is `text/plain` (not JSON)
- `deviceId` is automatically injected into header
- Fiscal day must be CLOSED before submitting file

---

## mTLS Authentication

All device operations use mutual TLS (mTLS):

```php
Http::withOptions([
    'cert' => storage_path('app/zimra/device_certificate.pem'),
    'ssl_key' => storage_path('app/zimra/device_private.key'),
])
```

Certificates are stored in:
- Database: `zimra_configs.certificate` and `zimra_configs.private_key`
- Files: `storage/app/zimra/device_certificate.pem` and `device_private.key`

---

## Fiscal Counters

When receipts are submitted, fiscal counters are automatically updated:

```php
[
    'fiscalCounterType' => 'saleByTax',
    'fiscalCounterCurrency' => 'USD',
    'fiscalCounterTaxPercent' => 15,
    'fiscalCounterTaxID' => 1,
    'fiscalCounterMoneyType' => 'Cash',
    'fiscalCounterValue' => 28.75,
]
```

These counters are sent to ZIMRA when closing the fiscal day.

---

## Error Handling

All endpoints return consistent error responses:

```json
{
  "error": true,
  "message": "Error description",
  "status": 400,
  "body": { /* ZIMRA error details */ }
}
```

Common errors:
- No active ZIMRA configuration
- No device ID configured
- No open fiscal day (for receipt submission)
- Certificate/key file not found
- ZIMRA API errors

---

## Files Structure

```
app/
├── Http/Controllers/
│   └── ZimraController.php
├── Services/
│   └── ZimraDeviceService.php
├── Models/
│   ├── ZimraConfig.php
│   └── FiscalDay.php
├── Jobs/
│   ├── ZimraPingJob.php
│   └── ZimraAutoCloseDayJob.php
└── Providers/
    └── ZimraPingServiceProvider.php

database/migrations/
├── 2026_02_19_080000_create_zimra_configs_table.php
├── 2026_02_19_120000_add_reporting_frequency_to_zimra_configs_table.php
└── 2026_02_19_123000_create_fiscal_days_table.php

storage/app/zimra/
├── device_certificate.pem
└── device_private.key

routes/
└── web.php (ZIMRA routes)
```

---

## Testing

### 1. Create Configuration
```bash
curl -X POST http://127.0.0.1:8000/zimra/config \
  -H "Content-Type: application/json" \
  -d '{
    "base_url": "https://fdmsapitest.zimra.co.zw",
    "device_model": "Server",
    "device_version": "v1"
  }'
```

### 2. Register Device
```bash
curl -X POST http://127.0.0.1:8000/zimra/register \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": 32558,
    "serial_number": "ABC123",
    "activation_key": "your-activation-key"
  }'
```

### 3. Open Fiscal Day
```bash
curl -X POST http://127.0.0.1:8000/zimra/open-day
```

### 4. Submit Receipt
```bash
curl -X POST http://127.0.0.1:8000/zimra/submit-receipt \
  -H "Content-Type: application/json" \
  -d '{ /* receipt data */ }'
```

### 5. Close Fiscal Day
```bash
curl -X POST http://127.0.0.1:8000/zimra/close-day
```

---

## Production Checklist

- [ ] Use production ZIMRA URL: `https://fdmsapi.zimra.co.zw`
- [ ] Secure certificate storage
- [ ] Enable queue worker for background jobs
- [ ] Set up scheduler (`php artisan schedule:work`)
- [ ] Configure proper logging
- [ ] Implement receipt number uniqueness
- [ ] Add retry logic for failed submissions
- [ ] Store receipt responses for audit trail
