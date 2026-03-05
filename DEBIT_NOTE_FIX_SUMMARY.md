# Debit Note Fix Summary

## Root Causes Identified

### 1. Business Logic Error
**Problem:** Sending new total quantity instead of adjustment amount to FDMS.

**Example:**
- Original: 7 items × $60 = $420
- User enters: 8 items
- **Wrong:** Sent 8 × $60 = $480 to FDMS
- **Correct:** Send (8-7) = 1 × $60 = $60 to FDMS

**Fix:** Backend now calculates adjustment automatically: `newQuantity - originalQuantity`

### 2. RCPT032 - Invalid Receipt Notes
**Problem:** receiptNotes didn't contain invoice reference.

**Wrong:**
```
"receiptNotes": "Price adjustment for previously invoiced goods"
```

**Correct:**
```
"receiptNotes": "Debit note for invoice INV-120: Price adjustment for previously invoiced goods"
```

**Fix:** System now automatically prepends "Debit note for invoice {invoice_no}" to user's reason.

### 3. Unnecessary Fields
**Problem:** Including `receiptPayments` and `receiptPrintForm` in debit notes.

**Fix:** 
- Removed `receiptPayments` - debit notes increase amount owed, not settle payment
- Removed `receiptPrintForm` - not required for debit notes

## FDMS Configuration

### GetConfig Response
```json
{
  "vatNumber": "NOT_REGISTERED",
  "deviceOperatingMode": "Online",
  "applicableTaxes": [
    {
      "taxID": 513,
      "taxPercent": 0.0,
      "taxName": "Non-VAT 0%",
      "validFrom": "2023-01-01T00:00:00"
    }
  ]
}
```

**Key Points:**
- Device is NOT_REGISTERED for VAT
- Only one tax group available: taxID 513 (Non-VAT 0%)
- All transactions must use 0% tax

## Correct Debit Note Payload Structure

```json
{
  "receipt": {
    "receiptType": "DebitNote",
    "receiptCurrency": "USD",
    "receiptCounter": 16,
    "receiptGlobalNo": 133,
    "invoiceNo": "DN-1772690476",
    "receiptDate": "2026-03-05T06:01:16",
    "receiptLinesTaxInclusive": false,
    
    "receiptLines": [
      {
        "receiptLineType": "Sale",
        "receiptLineNo": 1,
        "receiptLineName": "Sports shoes",
        "receiptLinePrice": 60,
        "receiptLineQuantity": 1,
        "receiptLineTotal": 60,
        "taxPercent": 0,
        "taxID": 513
      }
    ],
    
    "receiptTaxes": [
      {
        "taxPercent": 0,
        "taxID": 513,
        "taxAmount": 0,
        "salesAmountWithTax": 60
      }
    ],
    
    "receiptTotal": 60,
    "receiptNotes": "Debit note for invoice INV-120: Additional quantity correction",
    
    "creditDebitNote": {
      "creditDebitNoteReceiptGlobalNo": 120,
      "creditDebitNoteDate": "2026-03-05T04:55:41"
    }
  }
}
```

**Note:** NO `receiptPayments`, NO `receiptPrintForm`

## Tax Calculation Validation

For 0% tax with `receiptLinesTaxInclusive = false`:

```
receiptLineTotal = price × quantity (base amount)
taxAmount = receiptLineTotal × (taxPercent / 100) = 60 × 0 = 0
salesAmountWithTax = receiptLineTotal + taxAmount = 60 + 0 = 60
receiptTotal = sum(salesAmountWithTax) = 60
```

**FDMS validates:**
1. `salesAmountWithTax = receiptLineTotal + taxAmount` ✓
2. `sum(salesAmountWithTax) = receiptTotal` ✓

## Code Changes

### Backend: `app/Services/DatabaseService.php`

1. **Adjustment Calculation** (lines 898-935)
   - Loads original receipt lines
   - Calculates: `adjustment = newQuantity - originalQuantity`
   - Validates adjustment > 0
   - Only sends adjustment to FDMS

2. **Invoice Reference in Notes** (lines 1010-1014)
   ```php
   $receiptNotes = "Debit note for invoice {$originalReceipt->invoice_no}";
   if (!empty($noteData['reason'])) {
       $receiptNotes .= ": {$noteData['reason']}";
   }
   ```

3. **Removed Fields** (lines 1029-1030)
   - No `receiptPayments`
   - No `receiptPrintForm`

### Frontend: `frontend/src/pages/DebitNotes.jsx`

1. **UI Shows Adjustment** (lines 210-270)
   - Columns: Original Qty | New Qty | Adjustment | Adjustment Amount
   - User enters new total quantity
   - System calculates and displays adjustment automatically

2. **Validation** (lines 123-127)
   - Only allows `newQuantity > originalQuantity`
   - Clear error messages

## Testing Checklist

- [ ] Create debit note with quantity increase (e.g., 7 → 8)
- [ ] Verify debug logs show adjustment calculation
- [ ] Verify receiptNotes contains invoice reference
- [ ] Verify payload has NO receiptPayments
- [ ] Verify payload has NO receiptPrintForm
- [ ] Check FDMS response for GREEN validation
- [ ] Verify FDMS sees correct adjustment amount

## Expected Result

**Original Invoice:**
- Sports shoes: 7 × $60 = $420

**Debit Note Sent to FDMS:**
- Sports shoes: 1 × $60 = $60 (adjustment only)

**FDMS Calculation:**
- New effective total: $420 + $60 = $480 ✓

**Validation:**
- RCPT015: ✓ Tax calculation correct
- RCPT032: ✓ Invoice reference in notes
- Status: GREEN
