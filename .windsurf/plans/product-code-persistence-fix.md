# Fix Product Code Not Persisting in Receipt PDFs

The product code field was added to the UI form but is not being saved to the database or displayed in PDFs because the backend service strips it out during receipt processing.

## Problem Analysis

1. **Frontend**: Product code input field (`productCode`) was added to the Submit Receipt form ✅
2. **PDF Templates**: Updated to display `$line['productCode']` ✅  
3. **Backend Service**: The `ZimraDeviceService::buildAndValidateReceiptBCMath()` method **rebuilds** each receipt line from scratch (lines 2621-2641), only preserving:
   - `receiptLineType`
   - `receiptLineName` 
   - `receiptLineHSCode`
   
   **The `productCode` field is NOT preserved** when the line is rebuilt, so it gets lost.

4. **Database**: The `receipt_lines` column stores the processed lines as JSON, which no longer contains `productCode`.

## Root Cause

In `app/Services/ZimraDeviceService.php` at lines 2530-2641:

```php
// Line 2533: Only 3 fields are preserved
$originalLineName = $line['receiptLineName'] ?? 'Item';
$originalHSCode = $line['receiptLineHSCode'] ?? '00000000';

// Line 2621-2641: Line is completely rebuilt
$line = [
    'receiptLineType' => $originalLineType,
    'receiptLineNo' => (int) ($index + 1),
];
// ... productCode is never added back
```

The `productCode` field from the form submission is discarded during FDMS validation/canonicalization.

## Solution

### Option 1: Preserve productCode in Service (Recommended)
Store `productCode` before rebuilding the line, then add it back to the rebuilt line array. This keeps it in the database JSON for PDF display.

**Files to modify:**
- `app/Services/ZimraDeviceService.php` (line ~2534 and ~2631)

### Option 2: Store in Separate Database Column
Add a `product_code` column to receipts table and store codes separately. More complex, requires migration.

## Implementation Steps

1. **Preserve productCode in `buildAndValidateReceiptBCMath()`**:
   - Line ~2534: Add `$originalProductCode = $line['productCode'] ?? null;`
   - Line ~2631: Add `if ($originalProductCode) { $line['productCode'] = $originalProductCode; }`

2. **Verify in other build methods** (if they exist):
   - Check `buildAndValidateReceipt()` method
   - Check any credit/debit note specific builders

3. **Test**:
   - Submit a receipt with product codes
   - Verify codes appear in database `receipt_lines` JSON
   - Generate PDF and confirm codes display correctly

## Questions

1. Should `productCode` be sent to FDMS API, or is it only for internal/PDF use?
2. Are there other receipt building methods that also need this fix?
3. Should we validate product code format (e.g., max length, allowed characters)?
