# Debit Note Payload - Before vs After

## Key Changes in New Payload

### 1. ✅ receiptNotes - SANITIZED (RCPT032 Fix)

**Before:**
```json
"receiptNotes": "Debit note issued to correct underbilling on Invoice INV-146"
```
- Length: 65 characters
- **Problem:** Too long for FDMS

**After:**
```json
"receiptNotes": "Debit note issued to correct underbilling on I"
```
- Length: 50 characters (max safe limit)
- ASCII-only characters
- Automatically truncated by `sanitizeReceiptNotes()`

### 2. ✅ product_id - ADDED (Stability Fix)

**Before:**
```json
{
  "receiptLineType": "Sale",
  "receiptLineNo": 1,
  "receiptLineName": "Leather shoes",
  "receiptLinePrice": 40.0,
  "receiptLineQuantity": 1.0,
  "receiptLineTotal": 40.0,
  "taxPercent": 0.0,
  "taxID": 513
}
```

**After:**
```json
{
  "receiptLineType": "Sale",
  "receiptLineNo": 1,
  "receiptLineName": "Leather shoes",
  "receiptLinePrice": 40.0,
  "receiptLineQuantity": 1.0,
  "receiptLineTotal": 40.0,
  "taxPercent": 0.0,
  "taxID": 513,
  "product_id": "12345"
}
```
- **New field:** `product_id` for stable matching
- Prevents issues when product names are edited

### 3. ✅ Delta Calculation - PRESERVED

**Original Receipt:**
```json
{
  "receiptLineName": "Leather shoes",
  "receiptLineQuantity": 1,
  "receiptLineTotal": 40
}
```

**Corrected Invoice:**
- Quantity: 2 (user wants to increase from 1 to 2)

**Debit Note (Delta):**
```json
{
  "receiptLineQuantity": 1.0,
  "receiptLineTotal": 40.0
}
```
- **Delta = 2 - 1 = 1** ✅ Correct
- Only the adjustment is sent, not the full corrected value

### 4. ✅ creditDebitNote Reference - CORRECT

```json
"creditDebitNote": {
  "creditDebitNoteReceiptGlobalNo": 146,
  "creditDebitNoteDate": "2026-03-06T03:29:21"
}
```
- References original receipt #146
- Includes original receipt date
- **This was already correct**

## Full New Payload Structure

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
        "receiptLineHSCode": "",
        "receiptLineName": "Leather shoes",
        "receiptLinePrice": 40.0,
        "receiptLineQuantity": 1.0,
        "receiptLineTotal": 40.0,
        "taxPercent": 0.0,
        "taxID": 513,
        "product_id": "12345"
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
      "creditDebitNoteReceiptGlobalNo": 146,
      "creditDebitNoteDate": "2026-03-06T03:29:21"
    },
    
    "receiptDeviceSignature": {
      "hash": "...",
      "signature": "..."
    }
  }
}
```

## What Should Happen Now

### Expected: RCPT032 Fixed ✅
- `receiptNotes` is now sanitized to 50 chars max
- ASCII-only characters
- Should pass FDMS validation

### Unknown: RCPT015 Status ❓
- Tax calculation is mathematically correct
- May be FDMS quirk with 0% tax
- Need to test to see if it persists

## Test Instructions

1. Submit a new debit note with a long reason text
2. Check the logs for the sanitized `receiptNotes`
3. Verify FDMS response:
   - **If only RCPT015 remains:** Tax calculation issue to investigate
   - **If both errors gone:** Success! ✅
   - **If RCPT032 persists:** Check logs for actual notes sent

## Files to Check After Submission

1. **Laravel logs:**
   ```
   storage/logs/laravel.log
   ```
   Look for: `DEBIT_NOTE_DELTA_CALCULATION` and `FINAL_JSON_SENT`

2. **Debug payload:**
   ```
   storage/app/zimra/debug/receipt_*_DN-*_final.json
   ```
   Check the actual `receiptNotes` value sent to FDMS
