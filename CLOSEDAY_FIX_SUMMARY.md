# CloseDay CountersMismatch Fix Summary

## Problem Identified

FDMS was rejecting CloseDay requests with `CountersMismatch` error because:

1. **Negative counters were filtered out** - Credit notes have negative values, but the filter was removing them
2. **Validation logic incomplete** - Only checking `SaleByTax`, ignoring `CreditNoteByTax` and `DebitNoteByTax`
3. **Database sync issue** - Local DB marked day as `closed` even when FDMS reported `FiscalDayCloseFailed`
4. **UI button disappeared** - CloseDay button only showed for `FiscalDayOpened` status, hiding it when retry was needed

## Fixes Applied

### 1. Counter Aggregation Filter (CRITICAL)
**File:** `app/Services/ZimraDeviceService.php:1251`

**Before:**
```php
return $c['fiscalCounterValue'] > 0;  // ❌ Removed credit notes
```

**After:**
```php
return $c['fiscalCounterValue'] != 0;  // ✅ Keeps negatives, removes zeros
```

### 2. Validation Logic - Service
**File:** `app/Services/ZimraDeviceService.php:1268-1274`

**Before:**
```php
if ($counter['fiscalCounterType'] === 'SaleByTax') {
    $totalSalesByTax += $counter['fiscalCounterValue'];
}
```

**After:**
```php
if (in_array($counter['fiscalCounterType'], ['SaleByTax', 'CreditNoteByTax', 'DebitNoteByTax'])) {
    $totalSalesByTax += $counter['fiscalCounterValue'];
}
```

### 3. Validation Logic - Validator
**File:** `app/Services/CloseDayValidator.php:148-154`

**Before:**
```php
if ($counter['fiscalCounterType'] === 'SaleByTax') {
    $totalSalesByTax += $counter['fiscalCounterValue'];
}
```

**After:**
```php
if (in_array($counter['fiscalCounterType'], ['SaleByTax', 'CreditNoteByTax', 'DebitNoteByTax'])) {
    $totalSalesByTax += $counter['fiscalCounterValue'];
}
```

### 4. Database Sync on Failure
**File:** `app/Services/ZimraDeviceService.php:944-982`

**Changes:**
- Added check to prevent updating local status to `'closed'` when FDMS reports `FiscalDayCloseFailed`
- Added timeout handling to prevent marking as closed when polling times out
- Local status remains `'open'` to allow retry when FDMS rejects CloseDay

### 5. UI Button Visibility
**File:** `resources/views/zimra.blade.php:524`

**Before:**
```html
<template x-if="deviceStatus?.fiscalDayStatus === 'FiscalDayOpened'">
```

**After:**
```html
<template x-if="deviceStatus?.fiscalDayStatus === 'FiscalDayOpened' || deviceStatus?.fiscalDayStatus === 'FiscalDayCloseFailed'">
```

**Button text changes dynamically:**
- `FiscalDayOpened` → "Close Fiscal Day"
- `FiscalDayCloseFailed` → "Retry Close Day"

## Expected Counter Structure

For fiscal day 14 with 4 invoices and 4 credit notes:

```json
[
  {
    "fiscalCounterType": "SaleByTax",
    "fiscalCounterCurrency": "USD",
    "fiscalCounterTaxPercent": 0,
    "fiscalCounterTaxID": 513,
    "fiscalCounterValue": 1710.00
  },
  {
    "fiscalCounterType": "SaleByTax",
    "fiscalCounterCurrency": "ZWG",
    "fiscalCounterTaxPercent": 0,
    "fiscalCounterTaxID": 513,
    "fiscalCounterValue": 53000.00
  },
  {
    "fiscalCounterType": "CreditNoteByTax",
    "fiscalCounterCurrency": "USD",
    "fiscalCounterTaxPercent": 0,
    "fiscalCounterTaxID": 513,
    "fiscalCounterValue": -509.97
  },
  {
    "fiscalCounterType": "CreditNoteByTax",
    "fiscalCounterCurrency": "ZWG",
    "fiscalCounterTaxPercent": 0,
    "fiscalCounterTaxID": 513,
    "fiscalCounterValue": -40999.91
  }
]
```

## Verification

Run `php verify_counter_fix.php` to confirm:
- ✅ Negative counters preserved
- ✅ Separate counter types for invoices and credit notes
- ✅ Totals match receipt values

## Current Status

- **Device 32558:** Fiscal Day 14 status reset to `'open'` for retry
- **Receipts:** All 8 receipts submitted to FDMS (IDs: 10694695-10694703)
- **Counters:** Fixed to preserve negative values
- **UI:** CloseDay button now visible for retry

## Next Steps

1. Refresh your UI (hard refresh: Ctrl+Shift+R)
2. Click "Retry Close Day" button
3. FDMS should accept the CloseDay request with correct counters
4. Local DB will only update to `'closed'` when FDMS confirms `FiscalDayClosed`

## Important Notes

- **Device ID Confusion:** You have 2 devices (32857 inactive, 32558 active)
- Always verify you're querying the correct device ID
- The fix ensures local DB stays in sync with FDMS status
- CloseDay button remains visible until both systems are synced
