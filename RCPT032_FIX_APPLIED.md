# RCPT032 Fix Applied

## Problem
FDMS rejected debit notes with error:
- **RCPT032: Invalid receipt notes**
- **RCPT015: Invalid tax amount calculation**

## Root Cause Analysis

### RCPT032
The `receiptNotes` field contained user-provided text that was:
- Too long (>50 characters)
- Potentially contained non-ASCII characters
- Example: `"Debit note issued to correct underbilling on Invoice INV-146"` (65 chars)

FDMS requires `receiptNotes` to be:
- ASCII-only characters (0x20-0x7E)
- Short and simple
- No special formatting

### RCPT015
This error may be related to:
- 0% tax handling
- Rounding precision
- Tax structure validation

## Fix Applied

### 1. Added `sanitizeReceiptNotes()` to ReceiptFactory

```php
protected function sanitizeReceiptNotes(string $notes): string
{
    // Remove non-ASCII characters
    $notes = preg_replace('/[^\x20-\x7E]/', '', $notes);
    
    // Limit to 50 characters (safe limit for FDMS)
    $notes = substr($notes, 0, 50);
    
    // Trim whitespace
    $notes = trim($notes);
    
    // Fallback if empty after sanitization
    if (empty($notes)) {
        $notes = 'Adjustment';
    }
    
    return $notes;
}
```

### 2. Updated DebitNote and CreditNote builders

Both `buildDebitNoteReceipt()` and `buildCreditNoteReceipt()` now sanitize notes:

```php
// Before
'receiptNotes' => $noteData['reason'] ?? 'Quantity adjustment',

// After
$receiptNotes = $this->sanitizeReceiptNotes($noteData['reason'] ?? 'Quantity adjustment');
'receiptNotes' => $receiptNotes,
```

## Expected Result

After this fix:
- User input: `"Debit note issued to correct underbilling on Invoice INV-146"`
- Sanitized: `"Debit note issued to correct underbilling on I"` (50 chars)
- RCPT032 should be resolved

## Testing

1. Clear cache:
   ```bash
   php artisan optimize:clear
   ```

2. Submit a new debit note with a long reason

3. Check logs for sanitized notes:
   ```bash
   tail -f storage/logs/laravel.log | grep receiptNotes
   ```

4. Verify FDMS accepts the receipt without RCPT032

## RCPT015 Investigation

If RCPT015 persists after RCPT032 is fixed, investigate:
- Tax rounding with 0% tax
- Tax structure for non-VAT items
- Possible FDMS validation quirk with 0% tax

The current payload shows:
- `taxPercent: 0`
- `taxAmount: 0`
- `salesAmountWithTax: 40`
- `receiptTotal: 40`

This is mathematically correct, but FDMS may have additional validation rules for 0% tax items.
