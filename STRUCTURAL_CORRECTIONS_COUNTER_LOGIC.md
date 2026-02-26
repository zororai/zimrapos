# Counter Logic Correction - Remove Receipt::max() Usage

## 🚨 CRITICAL ISSUE

Current code in `ZimraDeviceService.php` calculates counters from `receipts` table using `max()`:

```php
// WRONG - Lines 1548-1560
$maxReceiptCounter = Receipt::where('device_id', $deviceId)
    ->where('fiscal_day_no', $fiscalDayNo)
    ->lockForUpdate()
    ->max('receipt_counter');

$nextReceiptCounter = ($maxReceiptCounter ?? 0) + 1;

$maxGlobalNo = Receipt::where('device_id', $deviceId)
    ->lockForUpdate()
    ->max('receipt_global_no');

$nextGlobalNo = ($maxGlobalNo ?? 0) + 1;
```

**Problem:** Counters derived from receipts table, not from `device_states` (single source of truth).

---

## ✅ REQUIRED FIX

Replace with `device_states` lookup:

```php
// CORRECT - Use device_states as single source of truth
DB::transaction(function () use ($deviceId, $fiscalDayNo, &$receiptData) {
    // Lock device_state row
    $deviceState = DeviceState::where('device_id', $deviceId)
        ->lockForUpdate()
        ->first();
    
    if (!$deviceState) {
        throw new \Exception("CRITICAL: No device_state record found for device {$deviceId}");
    }
    
    // Check reconciliation status
    if ($deviceState->requires_reconciliation) {
        throw new \Exception(
            "CRITICAL: Device {$deviceId} requires reconciliation. " .
            "Error: {$deviceState->reconciliation_error}"
        );
    }
    
    // Calculate next counters from device_state (NOT receipts table)
    $nextReceiptCounter = $deviceState->getNextReceiptCounter($fiscalDayNo);
    $nextGlobalNo = $deviceState->getNextGlobalNo();
    
    Log::info('ZIMRA SubmitReceipt - Counters from device_state (LOCKED)', [
        'device_state_last_fiscal_day' => $deviceState->last_fiscal_day_no,
        'device_state_last_counter' => $deviceState->last_receipt_counter,
        'device_state_last_global' => $deviceState->last_receipt_global_no,
        'fdms_fiscal_day_no' => $fiscalDayNo,
        'next_receipt_counter' => $nextReceiptCounter,
        'next_global_no' => $nextGlobalNo,
    ]);
    
    // Override counters with calculated values
    $receiptData['receiptCounter'] = $nextReceiptCounter;
    $receiptData['receiptGlobalNo'] = $nextGlobalNo;
});
```

---

## 📍 Location in Code

**File:** `app/Services/ZimraDeviceService.php`

**Lines to Replace:** 1546-1573

**Method:** `submitReceipt()`

---

## 🔒 Why This Matters

1. **Single Source of Truth:** `device_states` is authoritative, not `receipts`
2. **Prevents Race Conditions:** Row lock on `device_state` prevents concurrent access
3. **Reconciliation Safety:** Checks reconciliation flag before proceeding
4. **FDMS Alignment:** `device_states` reflects FDMS state, not DB state

---

## ⚠️ DO NOT

- ❌ Use `Receipt::max('receipt_counter')`
- ❌ Use `Receipt::max('receipt_global_no')`
- ❌ Use `Receipt::count()`
- ❌ Derive counters from receipts table

---

## ✅ ALWAYS

- ✅ Use `DeviceState::lockForUpdate()`
- ✅ Use `$deviceState->getNextReceiptCounter($fiscalDayNo)`
- ✅ Use `$deviceState->getNextGlobalNo()`
- ✅ Check `$deviceState->requires_reconciliation`

---

**Status:** Ready to apply
**Priority:** CRITICAL - Required for atomic fiscal state
