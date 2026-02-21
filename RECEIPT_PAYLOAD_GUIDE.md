# FDMS Receipt Payload Guide - Complete Structure

## Overview
This guide shows the complete receipt payload structure from header to footer for ZIMRA FDMS v7.2 compliance.

---

## Root Payload Structure

```json
{
  "deviceID": 123456,
  "receipt": { ... }
}
```

**Important:** 
- `deviceID` is at **root level** (integer)
- `receipt` object contains all receipt data + signature

---

## Receipt Object - Field Order Matters!

### 1. Header Fields

```json
{
  "receiptType": "FiscalInvoice",
  "receiptCurrency": "USD",
  "receiptCounter": 1,
  "receiptGlobalNo": "00000001",
  "invoiceNo": "INV-2024-0001"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `receiptType` | string | ✅ | `FiscalInvoice`, `CreditNote`, `DebitNote`, `Refund` |
| `receiptCurrency` | string | ✅ | ISO 4217 code (e.g., `USD`, `ZWL`) |
| `receiptCounter` | integer | ✅ | Sequential counter for this device |
| `receiptGlobalNo` | string | ✅ | Global receipt number (8 digits, zero-padded) |
| `invoiceNo` | string | ✅ | Your internal invoice number |

---

### 2. Buyer Information (Optional but Recommended)

```json
{
  "buyerData": {
    "buyerRegisterName": "John Doe",
    "buyerTradeName": "Doe Enterprises",
    "buyerTIN": "1234567890",
    "buyerContacts": {
      "phoneNo": "+263771234567",
      "email": "john@example.com"
    },
    "buyerAddress": {
      "street": "123 Main Street",
      "city": "Harare",
      "country": "Zimbabwe"
    }
  }
}
```

**All `buyerData` fields are optional** - omit entire object if not needed.

---

### 3. Receipt Metadata

```json
{
  "receiptNotes": "Thank you for your business",
  "receiptDate": "2024-02-21T23:11:00",
  "creditDebitNote": null,
  "receiptLinesTaxInclusive": true
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `receiptNotes` | string | ❌ | Optional notes/comments |
| `receiptDate` | string | ✅ | ISO 8601 format: `YYYY-MM-DDTHH:mm:ss` |
| `creditDebitNote` | object | ❌ | Only for credit/debit notes |
| `receiptLinesTaxInclusive` | boolean | ✅ | `true` = prices include tax, `false` = tax added |

---

### 4. Receipt Lines (Items)

```json
{
  "receiptLines": [
    {
      "receiptLineNo": 1,
      "receiptLineType": "Sale",
      "receiptLineName": "Product A",
      "receiptLineQuantity": 2.0,
      "receiptLinePrice": 15.0,
      "receiptLineTotal": 30.0,
      "taxCode": "A",
      "taxPercent": 15.0,
      "taxID": 1,
      "receiptLineHSCode": "00000000"
    }
  ]
}
```

**Field Order in Each Line:**
1. `receiptLineNo` (integer) - Line number starting from 1
2. `receiptLineType` (string) - `Sale`, `Return`, `Discount`
3. `receiptLineName` (string) - Item description
4. `receiptLineQuantity` (float) - Quantity with up to 6 decimals
5. `receiptLinePrice` (float) - Unit price with up to 6 decimals
6. `receiptLineTotal` (float) - Line total (2 decimals)
7. `taxCode` (string) - Tax code from FDMS (e.g., `A`, `B`, `C`)
8. `taxPercent` (float) - Tax percentage (2 decimals)
9. `taxID` (integer) - Tax ID from FDMS `applicableTaxes`
10. `receiptLineHSCode` (string) - Harmonized System code (8 digits)

**Critical:**
- Use `taxID` from FDMS `GetConfig` → `applicableTaxes`
- Never use hardcoded or user-provided `taxID`
- All numeric values must be JSON **numbers**, not strings

---

### 5. Receipt Taxes (Summary by Tax Code)

```json
{
  "receiptTaxes": [
    {
      "taxCode": "A",
      "taxPercent": 15.0,
      "taxID": 1,
      "taxAmount": 10.43,
      "salesAmountWithTax": 80.0
    }
  ]
}
```

**Field Order:**
1. `taxCode` (string)
2. `taxPercent` (float, 2 decimals)
3. `taxID` (integer)
4. `taxAmount` (float, 2 decimals) - Total tax for this code
5. `salesAmountWithTax` (float, 2 decimals) - Total sales including tax

**Validation:**
- Sum of all `salesAmountWithTax` must equal `receiptTotal`
- `taxAmount` calculation depends on `receiptLinesTaxInclusive`

---

### 6. Receipt Payments

```json
{
  "receiptPayments": [
    {
      "paymentType": "Cash",
      "paymentAmount": 80.0
    },
    {
      "paymentType": "Card",
      "paymentAmount": 20.0
    }
  ]
}
```

**Payment Types:**
- `Cash`
- `Card`
- `EcoCash`
- `BankTransfer`
- `Other`

**Validation:**
- Sum of all `paymentAmount` must equal `receiptTotal`

---

### 7. Receipt Total

```json
{
  "receiptTotal": 80.0
}
```

**Must equal:**
- Sum of `receiptPayments[].paymentAmount`
- Sum of `receiptTaxes[].salesAmountWithTax`

---

### 8. Footer Fields

```json
{
  "receiptPrintForm": "A4",
  "fiscalDayNo": 1
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `receiptPrintForm` | string | ❌ | `A4`, `80mm`, etc. |
| `fiscalDayNo` | integer | ✅ | Fiscal day number from FDMS `GetStatus` |

**Critical:**
- Always fetch `fiscalDayNo` from FDMS `GetStatus` API
- Never use cached or hardcoded fiscal day number

---

### 9. Device Signature (Added After Signing)

```json
{
  "receiptDeviceSignature": {
    "hash": "base64_encoded_sha256_hash",
    "signature": "base64_encoded_ecdsa_signature"
  }
}
```

**Signing Process:**
1. Build receipt object **without** `receiptDeviceSignature`
2. Encode to JSON with `JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION`
3. Hash JSON string with SHA256 → base64 encode
4. Sign JSON string with ECDSA private key → base64 encode
5. Add `receiptDeviceSignature` to receipt object
6. Build final payload with `deviceID` at root

---

## Complete Example

See `example_receipt_payload.json` for a working example.

---

## Common RCPT Errors

| Error | Cause | Fix |
|-------|-------|-----|
| RCPT012 | Invalid tax code | Use only codes from FDMS `applicableTaxes` |
| RCPT014 | Invalid tax ID | Use `taxID` from FDMS, check `receiptDate` within `taxValidFrom`/`taxValidTill` |
| RCPT020 | Invalid receipt total | Use BCMath for calculations, validate sums |
| RCPT021 | Invalid fiscal day | Fetch `fiscalDayNo` from FDMS `GetStatus` before submission |
| RCPT025 | Invalid signature | Ensure numeric fields are JSON numbers (not strings), use `JSON_PRESERVE_ZERO_FRACTION` |

---

## Tax Calculation Examples

### Tax Inclusive (receiptLinesTaxInclusive = true)

```
Price (incl. tax) = 115.00
Tax % = 15%

Tax Amount = 115.00 × 15 / (100 + 15) = 15.00
Sales Amount = 115.00
```

### Tax Exclusive (receiptLinesTaxInclusive = false)

```
Price (excl. tax) = 100.00
Tax % = 15%

Tax Amount = 100.00 × 15 / 100 = 15.00
Sales Amount = 100.00 + 15.00 = 115.00
```

---

## Field Order Reference

**Receipt Root Level (in order):**
1. receiptType
2. receiptCurrency
3. receiptCounter
4. receiptGlobalNo
5. invoiceNo
6. buyerData (optional)
7. receiptNotes (optional)
8. receiptDate
9. creditDebitNote (optional)
10. receiptLinesTaxInclusive
11. receiptLines
12. receiptTaxes
13. receiptPayments
14. receiptTotal
15. receiptPrintForm (optional)
16. fiscalDayNo
17. receiptDeviceSignature (added after signing)

**This order is critical for signature verification!**
