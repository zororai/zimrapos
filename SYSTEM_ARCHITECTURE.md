# ZIMRA FDMS System Architecture

## Table of Contents
1. [System Overview](#system-overview)
2. [Receipt Generation Flow](#receipt-generation-flow)
3. [Signature Generation](#signature-generation)
4. [Fiscal Counter Calculation](#fiscal-counter-calculation)
5. [Fiscal Day Management](#fiscal-day-management)
6. [Database Structure](#database-structure)
7. [Security Design](#security-design)

---

## System Overview

The ZIMRA Fiscal Device Management System (FDMS) integration provides a complete solution for:
- Device registration and certificate management
- Receipt generation and submission with digital signatures
- Fiscal day opening/closing with counter validation
- Secure communication using mTLS (mutual TLS)

### Key Components
- **Laravel Backend**: API endpoints and business logic
- **ZimraDeviceService**: Core service handling ZIMRA API communication
- **Database**: PostgreSQL/MySQL for receipt and fiscal day storage
- **Certificate Store**: Secure storage of device certificates and private keys

---

## Receipt Generation Flow

### 1. Receipt Creation Process

```
┌─────────────────┐
│   POS System    │
│  (Sale Event)   │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  Receipt Data Preparation               │
│  - Line items with prices               │
│  - Tax calculations                     │
│  - Payment methods                      │
│  - Customer info (optional)             │
└────────┬────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  Tax Calculation Engine                 │
│  - Group items by tax rate              │
│  - Calculate tax-inclusive/exclusive    │
│  - Generate receipt_taxes array         │
└────────┬────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  Build Receipt Payload                  │
│  - receiptType (e.g., "FiscalInvoice")  │
│  - receiptCurrency (e.g., "USD")        │
│  - receiptCounter (auto-increment)      │
│  - receiptGlobalNo (global counter)     │
│  - receiptLines (line items)            │
│  - receiptTaxes (tax breakdown)         │
│  - receiptPayments (payment methods)    │
│  - receiptTotal (final amount)          │
└────────┬────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  Generate Canonical String              │
│  (for digital signature)                │
└────────┬────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  Sign Receipt with Device Private Key  │
│  - Generate hash (SHA-256)              │
│  - Create signature (ECDSA)             │
└────────┬────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  Submit to ZIMRA API                    │
│  POST /Device/v1/{deviceID}/Receipt     │
│  (with mTLS authentication)             │
└────────┬────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  Store in Local Database                │
│  - Receipt data                         │
│  - ZIMRA response                       │
│  - Verification code                    │
│  - QR code data                         │
└─────────────────────────────────────────┘
```

### 2. Receipt Data Structure

```json
{
  "receiptType": "FiscalInvoice",
  "receiptCurrency": "USD",
  "receiptCounter": 15,
  "receiptGlobalNo": 150,
  "receiptDate": "2026-02-23",
  "receiptLinesTaxInclusive": true,
  "receiptLines": [
    {
      "receiptLineNo": 1,
      "receiptLineHSCode": "12345678",
      "receiptLineType": "Sale",
      "receiptLineName": "Product A",
      "receiptLinePrice": 100.00,
      "receiptLineQuantity": 2.0,
      "receiptLineTotal": 200.00
    }
  ],
  "receiptTaxes": [
    {
      "taxCode": "A",
      "taxID": 1,
      "taxPercent": 15.0,
      "taxAmount": 30.00,
      "salesAmountWithTax": 230.00
    }
  ],
  "receiptPayments": [
    {
      "moneyTypeCode": "Cash",
      "paymentAmount": 230.00
    }
  ],
  "buyerData": {
    "buyerRegisterName": "ABC Company Ltd",
    "buyerTradeName": "ABC Store",
    "vatNumber": "12345678",
    "buyerTIN": "1234567890",
    "buyerContacts": {
      "phoneNo": "+263712345678",
      "email": "customer@example.com"
    },
    "buyerAddress": {
      "province": "Harare",
      "city": "Harare",
      "street": "Main Street",
      "houseNo": "123",
      "district": "CBD"
    }
  },
  "receiptTotal": 230.00,
  "receiptDeviceSignature": {
    "hash": "base64_encoded_hash",
    "signature": "base64_encoded_signature"
  }
}
```

**Note**: `buyerData` is optional and typically used for FiscalInvoice receipts where customer details are required.

---

## Signature Generation

### Digital Signature Process (FDMS Spec 13.3)

The signature ensures receipt integrity and authenticity.

#### Step 1: Build Canonical String

The canonical string is a concatenation of specific fields in a defined order:

```
deviceID || receiptType || receiptCurrency || receiptDate || 
receiptCounter || receiptGlobalNo || receiptLines || receiptTaxes || 
receiptPayments || receiptTotal
```

**Example:**
```
32558FiscalInvoiceUSD2026-02-2315150[line_data][tax_data][payment_data]23000
```

#### Step 2: Generate Hash

```php
$hash = hash('sha256', $canonicalString, true);
$hashBase64 = base64_encode($hash);
```

- **Algorithm**: SHA-256
- **Output**: Base64-encoded hash for the `hash` field

#### Step 3: Sign Canonical String

```php
$signature = '';
openssl_sign($canonicalString, $signature, $privateKey, OPENSSL_ALGO_SHA256);
$signatureBase64 = base64_encode($signature);
```

- **Algorithm**: ECDSA with SHA-256
- **Input**: Canonical string (NOT the hash)
- **Key**: Device private key (ECDSA P-256)
- **Output**: Base64-encoded signature

#### Step 4: Local Verification

```php
$publicKey = openssl_pkey_get_public($certificate);
$verifyResult = openssl_verify($canonicalString, $signature, $publicKey, OPENSSL_ALGO_SHA256);
```

- Verifies signature before sending to ZIMRA
- Ensures certificate and private key match

### Critical Notes

⚠️ **IMPORTANT**: 
- Sign the **canonical string**, NOT the hash
- `openssl_sign()` with `OPENSSL_ALGO_SHA256` hashes internally
- Signing the hash causes double-hashing and `BadCertificateSignature` error

---

## Fiscal Counter Calculation

Fiscal counters aggregate sales data for fiscal day closure.

### Counter Types

| Counter Type | Description | Grouping |
|--------------|-------------|----------|
| `SaleByTax` | Total sales by tax rate | taxID, taxPercent |
| `SaleTaxByTax` | Total tax collected | taxID, taxPercent |
| `CreditNoteByTax` | Credit note sales | taxID, taxPercent |
| `DebitNoteByTax` | Debit note sales | taxID, taxPercent |

### Calculation Process

```php
// Group receipts by tax rate
foreach ($receipts as $receipt) {
    foreach ($receipt->receipt_taxes as $tax) {
        $taxID = $tax['taxID'];
        $taxPercent = $tax['taxPercent'];
        $salesAmountWithTax = $tax['salesAmountWithTax'];
        
        // Aggregate by tax group
        $key = "SaleByTax_{$taxID}_{$taxPercent}";
        $counters[$key]['fiscalCounterValue'] += $salesAmountWithTax;
    }
}
```

### Counter Structure

```json
{
  "fiscalCounterType": "SaleByTax",
  "fiscalCounterCurrency": "USD",
  "fiscalCounterTaxPercent": 15.0,
  "fiscalCounterTaxID": 1,
  "fiscalCounterValue": 2300.00
}
```

### Validation Rules

1. **Total Match**: Sum of `SaleByTax` counters must equal total receipt value
2. **No Zero Values**: Filter out counters with zero value
3. **Proper Sorting**: Sort by type → currency → taxID

---

## Fiscal Day Management

### Fiscal Day Lifecycle

```
┌──────────────┐
│ FiscalDayNew │ (Initial state)
└──────┬───────┘
       │ openDay()
       ▼
┌─────────────────┐
│ FiscalDayOpened │ (Accepting receipts)
└──────┬──────────┘
       │ submitReceipt() (multiple times)
       │
       │ closeDay()
       ▼
┌──────────────────────┐
│ FiscalDayCloseInitiated │ (Processing)
└──────┬───────────────┘
       │
       ├─ Success ──────────────────┐
       │                            ▼
       │                   ┌─────────────────┐
       │                   │ FiscalDayClosed │
       │                   └─────────────────┘
       │
       └─ Failure ──────────────────┐
                                    ▼
                          ┌──────────────────────┐
                          │ FiscalDayCloseFailed │
                          └──────────────────────┘
```

### Opening a Fiscal Day

```php
POST /Device/v1/{deviceID}/OpenDay

// No payload required
// Response includes:
{
  "fiscalDayNo": 10,
  "operationID": "0HNJILAP7K82N:00000001"
}
```

**Database Record:**
```php
FiscalDay::create([
    'device_id' => $deviceId,
    'fiscal_day_no' => $fiscalDayNo,
    'status' => 'opened',
    'opened_at' => now(),
    'open_operation_id' => $operationId,
]);
```

### Closing a Fiscal Day

#### Step 1: Build Payload from Receipts

```php
$payload = [
    'fiscalDayNo' => 10,
    'fiscalDayDate' => '2026-02-23',
    'fiscalDayCounters' => [...], // Calculated from receipts
    'receiptCounter' => 15, // Last receipt counter
];
```

#### Step 2: Build Canonical String

```
deviceID || fiscalDayNo || fiscalDayDate || fiscalDayCounters
```

**Example:**
```
32558102026-02-23SALEBYTAXUSD15.00230000
```

#### Step 3: Sign and Submit

```php
POST /Device/v1/{deviceID}/CloseDay

{
  "fiscalDayNo": 10,
  "fiscalDayDate": "2026-02-23",
  "fiscalDayCounters": [...],
  "receiptCounter": 15,
  "fiscalDayDeviceSignature": {
    "hash": "base64_hash",
    "signature": "base64_signature"
  }
}
```

#### Step 4: Poll for Completion

```php
// Poll getStatus() every 3 seconds (max 5 attempts)
while ($attempts < 5) {
    $status = getStatus();
    
    if ($status === 'FiscalDayClosed') {
        // Success
        break;
    }
    
    if ($status === 'FiscalDayCloseFailed') {
        // Handle error
        $errorCode = $status['fiscalDayClosingErrorCode'];
        break;
    }
    
    sleep(3);
}
```

### Error Handling

| Error Code | Cause | Solution |
|------------|-------|----------|
| `BadCertificateSignature` | Invalid signature | Fix signing logic (sign canonical string, not hash) |
| `MissingReceipts` | Gap in receipt sequence | Submit missing receipts |
| `ReceiptsWithValidationErrors` | Red/Gray validation errors | Fix receipt data |
| `CountersMismatch` | Counter totals don't match | Recalculate counters |

---

## Database Structure

### Tables

#### 1. `zimra_configs`

Stores device configuration and certificates.

```sql
CREATE TABLE zimra_configs (
    id BIGINT PRIMARY KEY,
    device_id INT,
    device_model VARCHAR(255),
    device_version VARCHAR(255),
    certificate TEXT, -- X.509 certificate (PEM)
    private_key TEXT, -- ECDSA private key (PEM)
    base_url VARCHAR(255),
    is_active BOOLEAN,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 2. `fiscal_days`

Tracks fiscal day lifecycle.

```sql
CREATE TABLE fiscal_days (
    id BIGINT PRIMARY KEY,
    device_id INT,
    fiscal_day_no INT,
    status VARCHAR(50), -- 'opened', 'closed', 'failed'
    opened_at TIMESTAMP,
    closed_at TIMESTAMP,
    open_operation_id VARCHAR(255),
    close_operation_id VARCHAR(255),
    close_response JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE(device_id, fiscal_day_no)
);
```

#### 3. `receipts`

Stores all receipt data and ZIMRA responses.

```sql
CREATE TABLE receipts (
    id BIGINT PRIMARY KEY,
    device_id INT,
    fiscal_day_no INT,
    receipt_counter INT,
    receipt_global_no INT,
    receipt_type VARCHAR(50),
    receipt_currency VARCHAR(3),
    receipt_date DATE,
    receipt_lines JSON, -- Array of line items
    receipt_taxes JSON, -- Array of tax breakdowns
    receipt_payments JSON, -- Array of payment methods
    buyer_data JSON, -- Customer information (optional)
    receipt_total DECIMAL(15,2),
    receipt_device_signature JSON, -- {hash, signature}
    
    -- ZIMRA Response
    verification_code VARCHAR(255),
    verification_url TEXT,
    qr_code_url TEXT,
    mac VARCHAR(255),
    fiscal_day_counter INT,
    
    -- Validation
    is_valid BOOLEAN DEFAULT true,
    has_red_errors BOOLEAN DEFAULT false,
    has_gray_errors BOOLEAN DEFAULT false,
    validation_errors JSON,
    
    -- Metadata
    submitted_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE(device_id, receipt_counter),
    UNIQUE(device_id, receipt_global_no),
    INDEX(fiscal_day_no),
    INDEX(receipt_date)
);
```

### Data Flow

```
┌─────────────┐
│ POS System  │
└──────┬──────┘
       │
       ▼
┌─────────────────┐      ┌──────────────┐
│ Receipt Created │─────▶│ receipts     │
└─────────────────┘      │ (pending)    │
                         └──────┬───────┘
                                │
                                ▼
                         ┌──────────────┐
                         │ Submit to    │
                         │ ZIMRA API    │
                         └──────┬───────┘
                                │
                                ▼
                         ┌──────────────┐
                         │ receipts     │
                         │ (verified)   │
                         └──────────────┘
```

---

## Security Design

### Certificate Management

#### 1. Device Registration Flow

```
┌──────────────────┐
│ Generate CSR     │ (Certificate Signing Request)
│ - ECDSA P-256    │
│ - CN=DeviceID    │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Submit to ZIMRA  │
│ /registerDevice  │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Receive Cert ID  │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ /issueCertificate│
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Store in DB      │
│ - Certificate    │
│ - Private Key    │
└──────────────────┘
```

#### 2. Key Storage

**Database Storage:**
```php
ZimraConfig::create([
    'certificate' => $certificatePEM, // X.509 PEM format
    'private_key' => $privateKeyPEM,  // PKCS#8 PEM format
]);
```

**File Storage (for mTLS):**
```php
// Written temporarily for HTTP client
Storage::put('zimra/device_certificate.pem', $certificate);
Storage::put('zimra/device_private.key', $privateKey);
```

**Security Measures:**
- Private keys stored encrypted in database
- File permissions: `0600` (read/write owner only)
- Keys written to disk only during API calls
- Cleaned up after use (optional)

#### 3. mTLS (Mutual TLS) Authentication

All ZIMRA API calls use mTLS:

```php
Http::withOptions([
    'cert' => storage_path('app/zimra/device_certificate.pem'),
    'ssl_key' => storage_path('app/zimra/device_private.key'),
])->post($url, $payload);
```

**Certificate Verification:**
- ZIMRA verifies device certificate
- Device verifies ZIMRA server certificate
- Both parties authenticate each other

### Signature Security

#### Private Key Protection

```php
// Load private key
$privateKey = openssl_pkey_get_private($pemString);

// Sign data
openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

// Free key from memory
openssl_free_key($privateKey);
```

#### Signature Verification Chain

```
┌─────────────────┐
│ Device Signs    │
│ (Private Key)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Local Verify    │
│ (Public Key)    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Submit to ZIMRA │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ ZIMRA Verifies  │
│ (Certificate)   │
└─────────────────┘
```

### Security Best Practices

1. **Never expose private keys** in logs or API responses
2. **Rotate certificates** before expiry
3. **Use environment variables** for sensitive config
4. **Encrypt database** fields containing keys
5. **Audit all signature operations**
6. **Implement rate limiting** on API endpoints
7. **Monitor for failed signatures** (potential tampering)

### Threat Mitigation

| Threat | Mitigation |
|--------|------------|
| Key theft | Database encryption, access control |
| Man-in-the-middle | mTLS, certificate pinning |
| Receipt tampering | Digital signatures, hash verification |
| Replay attacks | Receipt counters, timestamps |
| Certificate expiry | Monitoring, auto-renewal alerts |

---

## System Monitoring

### Key Metrics to Track

1. **Receipt Submission Rate**
   - Successful vs. failed submissions
   - Average response time

2. **Signature Verification**
   - Local verification success rate
   - ZIMRA verification failures

3. **Fiscal Day Status**
   - Open days count
   - Failed closures
   - Average closure time

4. **Certificate Health**
   - Days until expiry
   - Certificate validation errors

### Logging Strategy

```php
// Receipt submission
Log::info('RECEIPT_SUBMITTED', [
    'receipt_counter' => $counter,
    'total' => $total,
    'verification_code' => $code,
]);

// Signature generation
Log::debug('SIGNATURE_GENERATED', [
    'canonical_string' => $canonical,
    'hash' => $hash,
    'signature_length' => strlen($signature),
]);

// Errors
Log::error('ZIMRA_API_ERROR', [
    'endpoint' => $endpoint,
    'status' => $status,
    'error_code' => $errorCode,
]);
```

---

## Appendix: FDMS Specification References

- **Section 13.3**: Signature generation rules
- **Section 13.3.1**: CloseDay canonical string format
- **Section 5.4.9**: FiscalDayProcessingError enum
- **Section 4.7**: submitReceipt endpoint
- **Section 4.10**: closeDay endpoint

---

**Document Version**: 1.0  
**Last Updated**: 2026-02-23  
**Author**: System Architecture Team
