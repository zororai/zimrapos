# Debit Note Structure & Implementation Guide

## ⚠️ CRITICAL: Fiscal Document Architecture

**Debit notes are NOT separate business documents. They are fiscal receipts stored in the fiscal ledger.**

This implementation follows the correct fiscal accounting principles:
- Debit notes are stored in the `receipts` table with `receipt_type = 'DebitNote'`
- They reference the original receipt via `original_receipt_id`
- They cannot be deleted, only voided
- They increment fiscal counters like any other receipt
- They appear in the fiscal ledger alongside invoices and credit notes

---

## Table of Contents

1. [Correct Architecture](#correct-architecture)
2. [Database Schema](#database-schema)
3. [API Endpoints](#api-endpoints)
4. [ZIMRA Fiscalization Flow](#zimra-fiscalization-flow)
5. [Request/Response Examples](#requestresponse-examples)
6. [Business Rules](#business-rules)
7. [Common Mistakes](#common-mistakes)

---

## Correct Architecture

### Single Fiscal Ledger

All fiscal documents live in ONE table:

```
receipts
--------
id                      (Primary Key)
receipt_type            (FiscalInvoice | CreditNote | DebitNote)
original_receipt_id     (Foreign Key → receipts.id)
external_reference      (For idempotency)
invoice_no
receipt_total
device_id
receipt_counter
receipt_global_no
fiscal_day_no
receipt_notes
fdms_receipt_id
is_voided
voided_by_receipt_id
```

### Receipt Model Relationships

**Location**: `app/Models/Receipt.php`

```php
class Receipt extends Model
{
    // Reference to original receipt (for credit/debit notes)
    public function originalReceipt()
    {
        return $this->belongsTo(Receipt::class, 'original_receipt_id');
    }

    // Credit notes issued against this receipt
    public function creditNotes()
    {
        return $this->hasMany(Receipt::class, 'original_receipt_id')
            ->where('receipt_type', 'CreditNote');
    }

    // Debit notes issued against this receipt
    public function debitNotes()
    {
        return $this->hasMany(Receipt::class, 'original_receipt_id')
            ->where('receipt_type', 'DebitNote');
    }

    // Receipt that voided this one
    public function voidedByReceipt()
    {
        return $this->belongsTo(Receipt::class, 'voided_by_receipt_id');
    }
}
```

### Why This Matters

❌ **Wrong**: Separate tables for invoices, credit notes, debit notes  
✅ **Correct**: Single fiscal ledger with receipt_type discrimination

**Benefits**:
- Maintains fiscal chain integrity
- Prevents counter inconsistencies
- Enables proper audit trails
- Follows ZIMRA fiscal device requirements
- Prevents deletion of fiscal records

---

## Database Schema

### receipts Table (Fiscal Ledger)

**All fiscal documents are stored here:**

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Auto-increment primary key |
| `device_id` | integer | ZIMRA device ID |
| `invoice_no` | string | Invoice number (e.g., INV-117, DN-118, CN-119) |
| `receipt_type` | enum | `FiscalInvoice`, `CreditNote`, `DebitNote` |
| `original_receipt_id` | bigint (nullable) | FK to receipts.id (for credit/debit notes) |
| `external_reference` | string (nullable) | External reference for idempotency |
| `receipt_currency` | string | USD, ZWG, etc. |
| `receipt_counter` | integer | Sequential counter per fiscal day |
| `receipt_global_no` | integer | Global receipt number |
| `fiscal_day_no` | integer | Fiscal day number |
| `receipt_total` | decimal(10,2) | Total amount |
| `receipt_notes` | text | Reason/notes (mandatory for debit notes) |
| `receipt_lines` | json | Array of line items |
| `receipt_taxes` | json | Array of tax totals |
| `receipt_payments` | json | Array of payments |
| `buyer_data` | json | Customer information |
| `receipt_hash` | string | Canonical hash |
| `receipt_signature` | json | Device signature |
| `fdms_receipt_id` | bigint | FDMS-assigned receipt ID |
| `is_valid` | boolean | Validation status |
| `is_voided` | boolean | Whether receipt is voided |
| `voided_by_receipt_id` | bigint (nullable) | FK to receipts.id |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Last update timestamp |

### Example Records

```sql
-- Original Invoice
id: 144
receipt_type: FiscalInvoice
invoice_no: INV-117
original_receipt_id: NULL
receipt_total: 500.00

-- Debit Note against invoice
id: 145
receipt_type: DebitNote
invoice_no: DN-118
original_receipt_id: 144
receipt_total: 20.00
receipt_notes: Additional delivery charges

-- Credit Note reversing debit note
id: 146
receipt_type: CreditNote
invoice_no: CN-119
original_receipt_id: 145
receipt_total: -20.00
receipt_notes: Reversal of DN-118
```

---

## API Endpoints

### 1. Create Debit Note

**Endpoint**: `POST /api/v1/debit-notes`

**Important**: Single debit note creation only. Debit notes are legal fiscal documents, not batch operations.

**Headers**:
```
AppId: {your_app_id}
ApiKey: {your_api_key}
Content-Type: application/json
```

**Request Body**:
```json
{
  "invoice_id": "INV-117",
  "external_reference": "ORDER-2231-DELIVERY",
  "invoice_no": "DN-118",
  "products": [
    {
      "id": "product_panier_id",
      "quantity": 1
    }
  ],
  "reason": "Additional delivery charges",
  "customer_id": "customer_panier_id"
}
```

**Fields**:
- `invoice_id` (required): Original receipt's invoice_no or ID
- `external_reference` (optional): Idempotency key to prevent duplicates
- `invoice_no` (optional): Custom invoice number (default: DN-{timestamp})
- `products` (required): Array of products with id and quantity
- `reason` (required): Reason for debit note (ZIMRA mandatory field)
- `customer_id` (optional): Customer panier_id

**Response** (201 Created):
```json
{
  "success": true,
  "data": {
    "success": true,
    "receipt": {
      "id": 145,
      "receipt_type": "DebitNote",
      "invoice_no": "DN-118",
      "original_receipt_id": 144,
      "receipt_total": 20.00,
      "receipt_global_no": 118,
      "fdms_receipt_id": 10699984,
      "is_valid": true,
      "created_at": "2026-03-05T03:39:07Z"
    },
    "fdms_receipt_id": 10699984
  }
}
```

### 2. Void Debit Note

**Endpoint**: `POST /api/v1/debit-notes/void`

**Note**: Fiscal documents cannot be deleted. Use credit notes to reverse.

**Request Body**:
```json
{
  "receipt_id": 145,
  "reason": "Reversal of incorrect debit note"
}
```

**Response**: Currently returns 501 Not Implemented. Use credit note creation instead.

### 3. ~~Delete Debit Notes~~ (REMOVED)

**This endpoint has been removed.** Fiscal documents cannot be deleted per ZIMRA regulations.

---

## ZIMRA Fiscalization Flow

### Correct Flow (Fiscal Document)

```
1. API Request Received
   └─> Validate request data
   
2. Validate Original Receipt
   └─> Find original receipt by invoice_no or ID
   └─> Check receipt is fiscalized (has fdms_receipt_id)
   └─> Check receipt is not voided
   └─> Verify device_id matches current device
   └─> Verify currency matches
   
3. Check Idempotency
   └─> If external_reference provided
   └─> Check for existing debit note with same reference
   └─> Return existing if found
   
4. Build Receipt Payload
   └─> Load products and calculate totals
   └─> Build receiptLines with tax calculations
   └─> Build receiptTaxes grouped by tax rate
   └─> Add creditDebitNote reference:
       {
         "creditDebitNoteReceiptGlobalNo": 117,
         "creditDebitNoteDate": "2026-03-05T03:39:07"
       }
   
5. Submit to FDMS
   └─> ZimraDeviceService.submitReceipt()
   └─> FDMS validates debit note
   └─> FDMS assigns receipt ID
   └─> FDMS returns signature
   
6. Save to Fiscal Ledger
   └─> Create record in receipts table
   └─> receipt_type = 'DebitNote'
   └─> original_receipt_id = 144
   └─> external_reference = 'ORDER-2231-DELIVERY'
   └─> All fiscal fields populated
   
7. Increment Counters
   └─> DebitNoteByTax counter
   └─> DebitNoteTaxByTax counter
   └─> BalanceByMoneyType counter
   └─> receipt_counter and receipt_global_no
```

### Wrong Flow (DO NOT DO THIS)

```
❌ Save to panier_debit_notes first
❌ Then submit to ZIMRA
❌ Then save to receipts
❌ Then update panier_debit_notes

This creates:
- Duplicate records
- Inconsistent counters
- Broken audit trails
- Fiscal chain gaps
```

### ZIMRA Receipt Payload Structure

```json
{
  "receiptType": "DebitNote",
  "receiptCurrency": "USD",
  "invoiceNo": "DN-XXXXXXXX",
  "receiptNotes": "Reason for debit note",
  "creditDebitNote": {
    "creditDebitNoteReceiptGlobalNo": 117,
    "creditDebitNoteDate": "2026-03-05T03:39:07"
  },
  "receiptDate": "2026-03-05T05:00:00",
  "receiptLines": [
    {
      "receiptLineType": "Sale",
      "receiptLineNo": 1,
      "receiptLineHSCode": "6403990000",
      "receiptLineName": "Sports shoes",
      "receiptLinePrice": 50.00,
      "receiptLineQuantity": 2,
      "receiptLineTotal": 100.00,
      "taxPercent": 0.0,
      "taxID": 513,
      "taxCode": "A"
    }
  ],
  "receiptTaxes": [
    {
      "taxPercent": 0.0,
      "taxID": 513,
      "taxAmount": 0.0,
      "salesAmountWithTax": 100.0
    }
  ],
  "receiptPayments": [
    {
      "moneyTypeCode": "Cash",
      "paymentAmount": 100.00
    }
  ],
  "receiptTotal": 100.00
}
```

---

## Business Rules

### 1. Original Receipt Requirements

- **Must exist**: Original invoice/receipt must be found in the system
- **Must be fiscalized**: Original receipt must have ZIMRA fiscal data
- **Required fields**:
  - `receipt_global_no`
  - `device_id`
  - `fiscal_day_no`
  - `receipt_date`

### 2. Product Requirements

- All products must exist in the system
- Products must have:
  - Valid `hs_code` (HS Code for customs)
  - Valid `applicable_tax_id` (ZIMRA tax configuration)
  - `selling_price` > 0

### 3. Tax Calculation

- Tax is calculated based on product's assigned tax rate
- For tax-inclusive pricing:
  ```
  tax_amount = line_total * (tax_percent / (100 + tax_percent))
  ```
- For tax-exclusive pricing:
  ```
  tax_amount = line_total * (tax_percent / 100)
  ```

### 4. Currency Support

- **ZIMRA fiscalization**: Only `USD` and `ZWG` supported
- Currency must match original receipt currency

### 5. Validation Rules

- **RCPT015**: `creditDebitNote` is mandatory for DebitNote type
- **RCPT032**: Must reference valid original receipt
- **RCPT034**: `receiptNotes` (reason) is mandatory
- **RCPT014**: Tax must be valid for receipt date

### 6. Counter Management

Debit notes increment fiscal counters:
- `DebitNoteByTax`: Sales amount with tax
- `DebitNoteTaxByTax`: Tax amount only
- `BalanceByMoneyType`: Payment balance

---

## Receipt History Display

### Automatic Inclusion

Debit notes automatically appear in Receipt History when:
1. ✅ Saved to `receipts` table with `receipt_type = 'DebitNote'`
2. ✅ `device_id` matches active ZIMRA device
3. ✅ No filtering by receipt type in `getReceipts()` method

### Display Format

```
Invoice No: DN-XXXXXXXX
Type: Debit Note
Amount: $100.00
Status: Valid/Invalid
Date: 2026-03-05
```

---

## Error Handling

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| `invoice_id is required` | Missing invoice_id in request | Provide valid invoice_id |
| `Invoice/Receipt not found` | Invalid invoice_id | Verify invoice exists |
| `Original receipt not found` | Invoice not fiscalized | Fiscalize original invoice first |
| `RCPT015` | Missing creditDebitNote | System auto-populates (check logs) |
| `RCPT032` | Invalid original receipt reference | Verify original receipt data |
| `RCPT034` | Missing receiptNotes | Provide reason field |

### ZIMRA Errors Array

If fiscalization fails, errors are returned:

```json
{
  "created": [...],
  "zimra_errors": [
    {
      "debit_note_id": "01JKXYZ...",
      "error": "Original receipt not found..."
    }
  ]
}
```

---

## Code References

### Key Files

1. **Model**: `app/Models/PanierDebitNote.php`
2. **Controller**: `app/Http/Controllers/Api/V1/DebitNoteController.php`
3. **Service**: `app/Services/DatabaseService.php`
   - `createDebitNotes()` - Main creation logic
   - `submitDebitNoteToZimra()` - ZIMRA submission
   - `searchDebitNotes()` - Search functionality
   - `deleteDebitNotes()` - Deletion logic
4. **ZIMRA Service**: `app/Services/ZimraDeviceService.php`
   - `submitReceipt()` - Handles all receipt types including DebitNote

### Migration

```bash
php artisan make:migration create_panier_debit_notes_table
```

---

## Testing

### Manual Test via API

```bash
# Create debit note with ZIMRA fiscalization
curl -X POST http://localhost/api/v1/debit-note/create \
  -H "AppId: your_app_id" \
  -H "ApiKey: your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "data": [{
      "invoice_id": "original_invoice_id",
      "products": [{"id": "product_id", "quantity": 1}],
      "reason": "Additional delivery charges"
    }],
    "zimra_fiscalize": true
  }'
```

### Verify in Database

```sql
-- Check debit note created
SELECT * FROM panier_debit_notes ORDER BY created_at DESC LIMIT 1;

-- Check ZIMRA receipt created
SELECT * FROM receipts WHERE receipt_type = 'DebitNote' ORDER BY created_at DESC LIMIT 1;
```

### Check Receipt History

Navigate to Receipt History in the application - debit notes should appear automatically.

---

## Common Mistakes

### ❌ Mistake 1: Separate Tables

**Wrong**:
```
panier_invoices
panier_credit_notes
panier_debit_notes
receipts (for ZIMRA only)
```

**Correct**:
```
receipts (single fiscal ledger)
  ├─ receipt_type = FiscalInvoice
  ├─ receipt_type = CreditNote
  └─ receipt_type = DebitNote
```

### ❌ Mistake 2: Allowing Deletion

**Wrong**: `DELETE /api/v1/debit-note/delete`

**Correct**: Fiscal documents cannot be deleted. Use void or credit note reversal.

### ❌ Mistake 3: Batch Creation

**Wrong**: `data: [...]` (array of debit notes)

**Correct**: Single debit note per request. These are legal documents.

### ❌ Mistake 4: Missing Original Receipt Validation

**Wrong**: Create debit note without checking if original exists

**Correct**: Validate original receipt before submission

### ❌ Mistake 5: Storing Outside Fiscal Ledger

**Wrong**: Save to `panier_debit_notes`, then copy to `receipts`

**Correct**: Save directly to `receipts` table

---

## Summary

**Correct Debit Note Workflow**:
1. Validate original receipt exists and is fiscalized
2. Check for duplicate external_reference
3. Build receipt payload with creditDebitNote reference
4. Submit directly to FDMS
5. Save result to receipts table as fiscal document
6. Appears in Receipt History automatically

**Key Architectural Principles**:
- ✅ Debit notes are fiscal receipts, not business documents
- ✅ Stored in receipts table with receipt_type = 'DebitNote'
- ✅ Reference original via original_receipt_id
- ✅ Cannot be deleted, only voided
- ✅ Increment fiscal counters like any receipt
- ✅ Must be fiscalized (no offline debit notes)
- ✅ Single debit note per API call
- ✅ Idempotency via external_reference
