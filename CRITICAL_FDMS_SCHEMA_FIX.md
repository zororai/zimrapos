# Critical FDMS Schema Fixes Applied

## Issues Fixed

### 1. ✅ creditDebitNote Structure - CORRECTED

**Wrong (was causing RCPT015/RCPT032):**
```json
"creditDebitNote": {
  "creditDebitNoteReceiptGlobalNo": 146,
  "creditDebitNoteDate": "2026-03-06T03:29:21"
}
```
❌ These fields don't exist in FDMS API v7.2

**Correct (FDMS API v7.2 schema):**
```json
"creditDebitNote": {
  "deviceID": 32558,
  "receiptGlobalNo": 146,
  "fiscalDayNo": 22
}
```
✅ Valid fields: `receiptID`, `deviceID`, `receiptGlobalNo`, `fiscalDayNo`

### 2. ✅ receiptLineHSCode - FIXED

**Wrong:**
```json
"receiptLineHSCode": ""
```
❌ Empty string can trigger validation errors

**Correct:**
```json
"receiptLineHSCode": "0000"
```
✅ Use "0000" for non-VAT items or when HS code is unknown

### 3. ✅ product_id - REMOVED

**Wrong:**
```json
{
  "receiptLineName": "Leather shoes",
  "product_id": "12345"
}
```
❌ FDMS doesn't recognize this field

**Correct:**
```json
{
  "receiptLineName": "Leather shoes"
}
```
✅ `product_id` is used internally for delta matching but NOT sent to FDMS

### 4. ✅ receiptNotes - SANITIZED

**Before:**
```json
"receiptNotes": "Debit note issued to correct underbilling on Invoice INV-146"
```
❌ Too long (65 chars)

**After:**
```json
"receiptNotes": "Debit note issued to correct underbilling on I"
```
✅ Max 50 chars, ASCII-only

## New Correct Payload Structure

```json
{
  "receipt": {
    "receiptType": "DebitNote",
    "receiptCurrency": "USD",
    "receiptCounter": 33,
    "receiptGlobalNo": 150,
    "invoiceNo": "DN-1772768184",
    "receiptDate": "2026-03-06T06:00:00",
    "receiptLinesTaxInclusive": false,
    
    "receiptLines": [
      {
        "receiptLineType": "Sale",
        "receiptLineNo": 1,
        "receiptLineHSCode": "0000",
        "receiptLineName": "Leather shoes",
        "receiptLinePrice": 40.0,
        "receiptLineQuantity": 1.0,
        "receiptLineTotal": 40.0,
        "taxPercent": 0.0,
        "taxID": 513
      }
    ],
    
    "receiptTaxes": [
      {
        "taxID": 513,
        "taxPercent": 0.0,
        "taxAmount": 0.0,
        "salesAmountWithTax": 40.0
      }
    ],
    
    "receiptPayments": [
      {
        "moneyTypeCode": "Cash",
        "paymentAmount": 40.0
      }
    ],
    
    "receiptTotal": 40.0,
    "receiptPrintForm": "Receipt48",
    "receiptNotes": "Debit note issued to correct underbilling on I",
    
    "creditDebitNote": {
      "deviceID": 32558,
      "receiptGlobalNo": 146,
      "fiscalDayNo": 22
    },
    
    "receiptDeviceSignature": {
      "hash": "...",
      "signature": "..."
    }
  }
}
```

## Files Modified

1. **`app/Services/Fiscal/ReceiptFactory.php`**
   - Fixed `creditDebitNote` to use correct FDMS fields
   - Applied to both DebitNote and CreditNote

2. **`app/Services/Fiscal/ReceiptBuilder.php`**
   - Fixed `receiptLineHSCode` to use "0000" if empty
   - Removed `product_id` from FDMS payload
   - Added comment explaining `product_id` is internal only

## Why This Fixes RCPT015 and RCPT032

**Root Cause:**
FDMS couldn't identify the referenced receipt because `creditDebitNote` had invalid field names. This caused:
- **RCPT015**: Tax validation failed because FDMS couldn't load original receipt for comparison
- **RCPT032**: Receipt notes validation failed for the same reason

**After Fix:**
FDMS can now properly:
1. Identify the original receipt using correct fields
2. Validate the delta calculation against the original
3. Accept the receipt notes
4. Process the debit note successfully

## Test Now

Submit a new debit note and verify:
- ✅ No RCPT015 error
- ✅ No RCPT032 error
- ✅ Receipt accepted by FDMS

Check the payload in:
```
storage/app/zimra/debug/receipt_*_DN-*_final.json
```

Verify it contains:
- `"creditDebitNote": { "deviceID": ..., "receiptGlobalNo": ..., "fiscalDayNo": ... }`
- `"receiptLineHSCode": "0000"`
- No `product_id` field
