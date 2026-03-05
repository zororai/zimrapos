# ZIMRA Fiscal Device Architecture

## Core Principle

**All fiscal documents are receipts. Invoices, debit notes, and credit notes are just receipt types.**

```
Accounting mindset:     Fiscal device mindset:
├── Invoice             ├── Receipt (type=FiscalInvoice)
├── Debit Note     →    ├── Receipt (type=DebitNote)
└── Credit Note         └── Receipt (type=CreditNote)
```

---

## Fiscal Receipt Lifecycle (Crash-Safe)

```
Client / POS
      │
      ▼
1️⃣ Create Transaction
      │
      ▼
2️⃣ Create PENDING Receipt (crash-safe checkpoint)
      │
      ▼
3️⃣ Reserve Counters (with row locking)
      │
      ▼
4️⃣ Build Canonical String (SignatureService)
      │
      ▼
5️⃣ Device Signature (ECDSA)
      │
      ▼
6️⃣ SubmitReceipt → FDMS
      │
      ▼
7️⃣ FDMS Validation + Signature
      │
      ▼
8️⃣ Update Receipt (status = submitted, store FDMS response)
      │
      ▼
9️⃣ Commit Counters (update device_state with hash)
      │
      ▼
🔟 Mark Receipt FINALIZED
      │
      ▼
1️⃣1️⃣ Generate QR Code (AFTER FDMS response)
```

**Why This Order Matters:**
- If crash after step 2: Recovery finds pending receipt, marks as failed
- If crash after step 6: FDMS has receipt, recovery can finalize
- If crash after step 8: Counter commit recovers chain
- QR generated AFTER FDMS response (contains valid receiptID)

---

## Service Architecture

```
Controllers
    │
    ▼
ReceiptService
    │
    ├── CounterService      (row locking, counter management)
    ├── SignatureService    (canonical string, ECDSA)
    ├── DeviceSyncService   (recovery, chain gap detection)
    └── ZimraDeviceService  (FDMS communication)
           │
           ▼
         FDMS
```

---

## 1. ReceiptService

**Location**: `app/Services/Fiscal/ReceiptService.php`

**Purpose**: Creates all fiscal documents through a unified interface.

### Single Entry Point

```php
use App\Enums\ReceiptType;

// ONE method for ALL receipt types
createReceipt(ReceiptType $type, array $data, ?Receipt $originalReceipt = null): array
```

### ReceiptType Enum

**Location**: `app/Enums/ReceiptType.php`

```php
enum ReceiptType: string
{
    case FISCAL_INVOICE = 'FiscalInvoice';
    case CREDIT_NOTE = 'CreditNote';
    case DEBIT_NOTE = 'DebitNote';

    public function invoicePrefix(): string;      // INV, CN, DN
    public function requiresOriginalReceipt(): bool;
    public function counterType(): string;        // SaleByTax, CreditNoteByTax, etc.
}
```

### Lifecycle Steps (Crash-Safe)

1. **Get fiscal day** from FDMS
2. **Reserve counters** (with row locking)
3. **Create PENDING receipt** (crash checkpoint)
4. **Build canonical string** for signing
5. **Sign** with device private key
6. **Submit to FDMS**
7. **Update receipt** (status = submitted, store FDMS response)
8. **Commit counters** (including hash for chain recovery)
9. **Mark receipt FINALIZED**
10. **Generate QR code** (after FDMS response)

### Usage

```php
use App\Enums\ReceiptType;
use App\Services\Fiscal\ReceiptService;

$receiptService = app(ReceiptService::class);

// Create fiscal invoice
$result = $receiptService->createReceipt(
    ReceiptType::FISCAL_INVOICE,
    [
        'receiptCurrency' => 'USD',
        'invoiceNo' => 'INV-118',
        'receiptLines' => [...],
        'receiptTaxes' => [...],
        'receiptPayments' => [...],
        'receiptTotal' => 500.00,
    ]
);

// Create debit note (requires original receipt)
$originalReceipt = Receipt::find(144);
$result = $receiptService->createReceipt(
    ReceiptType::DEBIT_NOTE,
    [
        'receiptCurrency' => 'USD',
        'receiptNotes' => 'Additional delivery charges',
        'receiptLines' => [...],
        'receiptTaxes' => [...],
        'receiptPayments' => [...],
        'receiptTotal' => 20.00,
    ],
    $originalReceipt
);

// Create credit note
$result = $receiptService->createReceipt(
    ReceiptType::CREDIT_NOTE,
    [
        'receiptCurrency' => 'USD',
        'receiptNotes' => 'Customer return',
        'receiptLines' => [...],
        'receiptTaxes' => [...],
        'receiptPayments' => [...],
        'receiptTotal' => 50.00,
    ],
    $originalReceipt
);
```

---

## 2. CounterService

**Location**: `app/Services/Fiscal/CounterService.php`

**Purpose**: Manages fiscal counters per FDMS specification with **row locking** for concurrency safety.

### Counters Managed

| Counter | Scope | Rule |
|---------|-------|------|
| `receiptCounter` | Per fiscal day | Resets to 1 each day |
| `receiptGlobalNo` | Lifetime | Increments forever |
| `fiscalDayNo` | Lifetime | Increments each day |
| `last_receipt_hash` | Recovery | For chain continuity |
| `last_receipt_id` | Recovery | For crash recovery |

### Methods

```php
setDeviceId(int $deviceId): self

// Reserve counters WITH ROW LOCKING (prevents concurrent duplicates)
reserveCounters(int $fiscalDayNo): array

// Commit counters WITH hash for chain recovery
commitCounters(
    int $fiscalDayNo,
    int $receiptCounter,
    int $receiptGlobalNo,
    string $receiptHash,    // For chain continuity
    int $receiptId          // For crash recovery
): void

// Get last receipt hash (fast path from device_state, fallback to receipts)
getLastReceiptHash(): string

syncFromFdms(int $fdmsFiscalDayNo, int $fdmsLastGlobalNo): void
openFiscalDay(int $fiscalDayNo): FiscalDay
```

### Row Locking (Critical)

```php
// Inside reserveCounters()
$this->deviceState = DeviceState::where('device_id', $this->deviceId)
    ->lockForUpdate()  // CRITICAL: prevents concurrent counter reservation
    ->first();
```

**Without row locking**: Two concurrent POS requests can reserve the same counter, causing FDMS rejection.

### Database

Counters stored in `device_state` table:

```sql
device_id
last_receipt_counter
last_receipt_global_no
last_fiscal_day_no
last_receipt_hash        -- For chain recovery
last_receipt_id          -- For crash recovery
last_sync_at             -- Last FDMS sync timestamp
```

---

## 3. SignatureService

**Location**: `app/Services/Fiscal/SignatureService.php`

**Purpose**: Builds canonical strings and creates ECDSA signatures.

### Methods

```php
setDeviceId(int $deviceId): self
buildCanonicalString(array $receiptData, string $previousHash): string
sign(string $canonicalString): array
signReceipt(array $receiptData, int $receiptCounter): array

// CRITICAL: Query by receipt_global_no (not receipt_counter)
getPreviousReceiptHash(int $nextGlobalNo): string

verifySignature(string $canonicalString, string $signatureBase64): bool
```

### Previous Receipt Hash (Critical)

**Wrong**: Query by `receipt_counter` (resets each day)
**Correct**: Query by `receipt_global_no` (lifetime chain)

```php
// CORRECT: Chain is based on global receipt sequence
$previousReceipt = Receipt::where('device_id', $this->deviceId)
    ->where('receipt_global_no', $nextGlobalNo - 1)
    ->where('status', 'finalized')
    ->first();
```

### Fast Path Recovery

1. Try `device_state.last_receipt_hash` first (fast)
2. Fallback to receipts table query (if device_state missing)

### Canonical String Format

Per FDMS API v7.2 Section 13.2.1:

```
deviceID || receiptType || receiptCurrency || receiptGlobalNo || 
receiptDate || receiptTotal(cents) || receiptTaxes || previousReceiptHash
```

Example:
```
32558FISCALINVOICEUSD1172026-03-05T03:39:07500000.000500002nOY...
```

### Signature Process

1. Build canonical string
2. SHA256 hash
3. ECDSA sign with device private key
4. Base64 encode

---

## 4. ZimraDeviceService

**Location**: `app/Services/ZimraDeviceService.php`

**Purpose**: Handles all FDMS API communication.

### Key Methods

```php
// Configuration
getConfig(int $deviceId): array
getStatus(int $deviceId): array

// Fiscal day operations
openDay(int $fiscalDayNo, int $deviceId): array
closeDay(int $fiscalDayNo, array $counters, int $deviceId): array

// Receipt submission (for ReceiptService)
getFiscalDayStatus(int $deviceId): array
submitReceiptToFdms(array $receiptData, int $deviceId): array

// Legacy (still functional)
submitReceipt(array $receiptData, int $deviceId): array
```

---

## Receipt Data Structure

All receipt types use the same payload structure:

```json
{
  "receiptType": "FiscalInvoice | CreditNote | DebitNote",
  "receiptCurrency": "USD",
  "receiptCounter": 12,
  "receiptGlobalNo": 118,
  "invoiceNo": "INV-118",
  "receiptDate": "2026-03-05T05:00:00",
  
  "receiptLines": [
    {
      "receiptLineType": "Sale",
      "receiptLineNo": 1,
      "receiptLineName": "Product name",
      "receiptLinePrice": 50.00,
      "receiptLineQuantity": 10,
      "receiptLineTotal": 500.00,
      "taxPercent": 0,
      "taxID": 513
    }
  ],
  
  "receiptTaxes": [
    {
      "taxID": 513,
      "taxPercent": 0,
      "taxAmount": 0,
      "salesAmountWithTax": 500.00
    }
  ],
  
  "receiptPayments": [
    {
      "moneyTypeCode": "Cash",
      "paymentAmount": 500.00
    }
  ],
  
  "receiptTotal": 500.00,
  
  "receiptDeviceSignature": {
    "hash": "vzK0KX9B2G7PasWX2rt2JlTje9vIeDWVeJ59s2qt478=",
    "signature": "MEYCIQCMDC97RyD2U9sv0cnNco4zcvXO0Qy7fGP/PJIHH3iT/..."
  },
  
  "creditDebitNote": {
    "creditDebitNoteReceiptGlobalNo": 117,
    "creditDebitNoteDate": "2026-03-05T03:39:07"
  }
}
```

**IMPORTANT**: The `creditDebitNote` structure uses specific FDMS field names:
- `creditDebitNoteReceiptGlobalNo` - the original receipt's `receipt_global_no`
- `creditDebitNoteDate` - the original receipt's `receipt_date`

Do NOT send `deviceID`, `fiscalDayNo`, or `receiptCounter` - FDMS does not need them.

---

## 5. DeviceSyncService

**Location**: `app/Services/Fiscal/DeviceSyncService.php`

**Purpose**: Recovery and synchronization with FDMS after failures.

### Methods

```php
// Sync device state with FDMS (call on startup)
syncWithFdms(int $deviceId): array

// Find gaps in receipt_global_no sequence
recoverChainGaps(int $deviceId): array

// Recover pending/submitted receipts after crash
recoverPendingReceipts(int $deviceId): array

// Full recovery (sync + pending + gaps)
fullRecovery(int $deviceId): array

// Resend a failed receipt
resendFailedReceipt(int $receiptId): array
```

### Recovery Scenarios

| Scenario | Recovery Action |
|----------|-----------------|
| Crash after pending created | Mark as failed after timeout |
| Crash after FDMS accepted | Finalize from FDMS response |
| Counter drift | Sync from FDMS status |
| Chain gap detected | Flag for manual review |

### Usage

```php
use App\Services\Fiscal\DeviceSyncService;

$syncService = app(DeviceSyncService::class);

// On application startup
$result = $syncService->fullRecovery($deviceId);

// Check for chain gaps
$gaps = $syncService->recoverChainGaps($deviceId);
if (!empty($gaps['gaps'])) {
    Log::warning('Chain gaps detected', $gaps);
}
```

---

## Database Schema

### receipts (Single Fiscal Ledger)

```sql
id                      -- Primary key
status                  -- pending | submitted | finalized | failed
device_id               -- ZIMRA device ID
invoice_no              -- INV-118, DN-119, CN-120
receipt_type            -- FiscalInvoice | CreditNote | DebitNote
original_receipt_id     -- FK for credit/debit notes
external_reference      -- Idempotency key
receipt_currency        -- USD, ZWG
receipt_counter         -- Per fiscal day
receipt_global_no       -- Lifetime counter
fiscal_day_no           -- Fiscal day number
receipt_total           -- Total amount
receipt_notes           -- Required for credit/debit notes
receipt_lines           -- JSON array
receipt_taxes           -- JSON array
receipt_payments        -- JSON array
buyer_data              -- JSON object
receipt_hash            -- SHA256 hash (base64)
receipt_signature       -- Device signature (JSON)
receipt_qr_code         -- Verification URL
verification_code       -- XXXX-XXXX-XXXX-XXXX
fdms_receipt_id         -- FDMS-assigned ID
fdms_operation_id       -- FDMS operation ID (for audits)
fdms_server_date        -- FDMS server timestamp
fdms_certificate_thumbprint -- FDMS certificate (for audits)
zimra_response          -- Full FDMS response (JSON)
validation_code         -- Green | Yellow | Red | Grey
validation_errors       -- JSON array
is_valid                -- Boolean
is_voided               -- Boolean
voided_by_receipt_id    -- FK
created_at
updated_at
```

### device_state (Recovery-Safe)

```sql
device_id
last_receipt_counter
last_receipt_global_no
last_fiscal_day_no
last_receipt_hash       -- For chain continuity recovery
last_receipt_id         -- For crash recovery
last_sync_at            -- Last FDMS sync timestamp
updated_at
```

---

## FDMS Validation Rules

### Counter Rules (RCPT003, RCPT004)

```
receiptCounter:
  - Must start at 1 for new fiscal day
  - Must increment by 1 for each receipt
  - Must reset to 1 when fiscal day changes

receiptGlobalNo:
  - Must increment by 1 forever
  - Never resets
  - Gaps are flagged as Grey errors
```

### Credit/Debit Note Rules (RCPT015, RCPT032)

```
creditDebitNote:
  - Mandatory for CreditNote and DebitNote types
  - Required fields (ONLY these two):
    - creditDebitNoteReceiptGlobalNo: original receipt's receipt_global_no
    - creditDebitNoteDate: original receipt's receipt_date (ISO format)
  - Do NOT send: deviceID, fiscalDayNo, receiptCounter
```

### Signature Rules (RCPT011, RCPT020)

```
receiptDeviceSignature:
  - hash: SHA256 of canonical string (base64)
  - signature: ECDSA signature (base64)
  - Must be verifiable with device certificate
```

---

## Error Color Codes

| Color | Meaning | Fiscal Day Close |
|-------|---------|------------------|
| Green | Valid | Allowed |
| Yellow | Minor error | Allowed |
| Red | Major error | Blocked |
| Grey | Chain gap | Blocked |

---

## File Structure

```
app/
├── Enums/
│   └── ReceiptType.php         -- FiscalInvoice | CreditNote | DebitNote
├── Services/
│   ├── Fiscal/
│   │   ├── CounterService.php      -- Counter management (with row locking)
│   │   ├── SignatureService.php    -- Canonical string + ECDSA signing
│   │   ├── ReceiptService.php      -- Unified receipt creation
│   │   └── DeviceSyncService.php   -- Recovery + FDMS sync
│   ├── ZimraDeviceService.php      -- FDMS communication
│   └── ReceiptQrCodeService.php    -- QR code generation
└── Models/
    ├── Receipt.php                 -- Fiscal ledger (all receipt types)
    └── DeviceState.php             -- Counter state + recovery fields
```

---

## Migration Path

### Old Architecture (Wrong)

```
Controllers
    ├── InvoiceController → PanierInvoice → ZIMRA → receipts
    ├── DebitNoteController → PanierDebitNote → ZIMRA → receipts
    └── CreditNoteController → PanierCreditNote → ZIMRA → receipts
```

### New Architecture (Correct)

```
Controllers
    └── ReceiptService → CounterService
                       → SignatureService
                       → ZimraDeviceService → FDMS
                       → receipts (single ledger)
```

---

## Key Principles

1. **Single fiscal ledger** - All receipts in one table
2. **Receipt types, not document types** - Invoice/debit/credit are just types
3. **Sign before submit** - Canonical string, not JSON
4. **Counter integrity** - Reserve → Submit → Commit
5. **No deletion** - Fiscal documents cannot be deleted
6. **Chain continuity** - Each receipt references previous hash
7. **FDMS is source of truth** - Sync counters from FDMS when needed

---

## Common Mistakes Avoided

| Mistake | Correct Approach |
|---------|-----------------|
| Separate tables for each document type | Single receipts table |
| Sign the JSON payload | Sign the canonical string |
| Delete fiscal documents | Void with credit note |
| Batch debit note creation | Single document per request |
| Store outside fiscal ledger | All records in receipts |
| Generate counters after FDMS | Reserve before, commit after |
