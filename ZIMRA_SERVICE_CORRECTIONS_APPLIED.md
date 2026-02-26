# ZimraDeviceService - Structural Corrections Applied

## 🎯 Objective

Fixed critical architectural flaws in `ZimraDeviceService.php` to support proper multi-company/multi-device data isolation and eliminate RCPT020 errors caused by incorrect device routing.

---

## 🔥 Root Cause of RCPT020 Issue

**Problem:** Device 32558 (lotusdream-1) had `is_active = NO`, while device 32857 (IPAYMOBILE-1) had `is_active = YES`.

**Impact:**
- All `submitReceipt()` calls used `ZimraConfig::getActive()` which **always returned device 32857**
- Device 32558 receipts never reached FDMS (no validation)
- Device 32857 receipts went through strict FDMS validation → RCPT020 triggered
- Cross-company contamination risk

---

## ✅ Changes Applied to `app/Services/ZimraDeviceService.php`

### **1. Added DeviceState Import**

```php
use App\Models\DeviceState;
```

### **2. Fixed submitReceipt() Method**

**Before:**
```php
public function submitReceipt(array $receiptData)
{
    $zimraConfig = ZimraConfig::getActive(); // ❌ WRONG
    $deviceId = $zimraConfig->device_id;
}
```

**After:**
```php
public function submitReceipt(array $receiptData, int $deviceId = null)
{
    // CRITICAL: device_id MUST be provided (no fallback to getActive)
    if (!$deviceId) {
        throw new \Exception(
            'CRITICAL: device_id is required. ' .
            'Device ID must be provided by ResolveCompanyDevice middleware.'
        );
    }

    // Load config for THIS specific device (not getActive)
    $zimraConfig = ZimraConfig::where('device_id', $deviceId)->first();
    
    if (!$zimraConfig) {
        throw new \Exception("No ZIMRA configuration found for device {$deviceId}.");
    }
}
```

**Result:** Each device uses its own configuration, no cross-contamination.

---

### **3. Fixed getStatus() Method**

**Before:**
```php
public function getStatus(int $deviceId = null)
{
    $zimraConfig = ZimraConfig::getActive(); // ❌ WRONG
    $deviceId = $deviceId ?? $zimraConfig->device_id;
}
```

**After:**
```php
public function getStatus(int $deviceId = null)
{
    // CRITICAL: device_id MUST be provided
    if (!$deviceId) {
        throw new \Exception(
            'CRITICAL: device_id is required for getStatus().'
        );
    }

    // Load config for THIS specific device
    $zimraConfig = ZimraConfig::where('device_id', $deviceId)->first();
    
    if (!$zimraConfig) {
        throw new \Exception("No ZIMRA configuration found for device {$deviceId}.");
    }
}
```

---

### **4. Fixed closeDay() Method**

**Before:**
```php
public function closeDay(array $payload = null)
{
    $zimraConfig = ZimraConfig::getActive(); // ❌ WRONG
    $deviceId = $zimraConfig->device_id;
}
```

**After:**
```php
public function closeDay(array $payload = null, int $deviceId = null)
{
    // CRITICAL: device_id MUST be provided
    if (!$deviceId) {
        throw new \Exception(
            'CRITICAL: device_id is required for closeDay().'
        );
    }

    // Load config for THIS specific device
    $zimraConfig = ZimraConfig::where('device_id', $deviceId)->first();
    
    if (!$zimraConfig) {
        throw new \Exception("No ZIMRA configuration found for device {$deviceId}.");
    }
}
```

**Also fixed:** `$this->getStatus()` → `$this->getStatus($deviceId)` on line 774

---

### **5. Fixed openDay() Method**

**Before:**
```php
public function openDay(int $fiscalDayNo = null)
{
    $zimraConfig = ZimraConfig::getActive(); // ❌ WRONG
    $deviceId = $zimraConfig->device_id;
}
```

**After:**
```php
public function openDay(int $fiscalDayNo = null, int $deviceId = null)
{
    // CRITICAL: device_id MUST be provided
    if (!$deviceId) {
        throw new \Exception(
            'CRITICAL: device_id is required for openDay().'
        );
    }

    // Load config for THIS specific device
    $zimraConfig = ZimraConfig::where('device_id', $deviceId)->first();
    
    if (!$zimraConfig) {
        throw new \Exception("No ZIMRA configuration found for device {$deviceId}.");
    }
}
```

---

### **6. Replaced Receipt::max() with device_states (CRITICAL)**

**Before (Lines 1551-1586):**
```php
// ❌ WRONG - Counters from receipts table
DB::transaction(function () use ($deviceId, $fiscalDayNo, &$receiptData) {
    $maxReceiptCounter = Receipt::where('device_id', $deviceId)
        ->where('fiscal_day_no', $fiscalDayNo)
        ->lockForUpdate()
        ->max('receipt_counter');
    
    $nextReceiptCounter = ($maxReceiptCounter ?? 0) + 1;

    $maxGlobalNo = Receipt::where('device_id', $deviceId)
        ->lockForUpdate()
        ->max('receipt_global_no');
    
    $nextGlobalNo = ($maxGlobalNo ?? 0) + 1;
    
    $receiptData['receiptCounter'] = $nextReceiptCounter;
    $receiptData['receiptGlobalNo'] = $nextGlobalNo;
});
```

**After (Lines 1552-1627):**
```php
// ✅ CORRECT - Counters from device_states (single source of truth)

// Check device_state reconciliation status BEFORE proceeding
$deviceState = DeviceState::where('device_id', $deviceId)->first();

if (!$deviceState) {
    throw new \Exception(
        "CRITICAL: No device_state record found for device {$deviceId}. " .
        "Run migration: php artisan migrate"
    );
}

if ($deviceState->requires_reconciliation) {
    throw new \Exception(
        "CRITICAL: Device {$deviceId} requires reconciliation. " .
        "FDMS accepted a receipt but DB persistence failed. " .
        "Error: {$deviceState->reconciliation_error}."
    );
}

// Verify FDMS state alignment with device_state
$fdmsLastGlobal = $fdmsStatus['lastReceiptGlobalNo'] ?? 0;
$deviceStateLastGlobal = $deviceState->last_receipt_global_no;

if ($fdmsLastGlobal !== $deviceStateLastGlobal) {
    Log::critical('FDMS_DEVICE_STATE_MISALIGNMENT', [
        'device_id' => $deviceId,
        'fdms_last_global' => $fdmsLastGlobal,
        'device_state_last_global' => $deviceStateLastGlobal,
        'difference' => $fdmsLastGlobal - $deviceStateLastGlobal,
    ]);
    
    throw new \Exception(
        "CRITICAL: FDMS/device_state misalignment detected. " .
        "FDMS lastReceiptGlobalNo: {$fdmsLastGlobal}, " .
        "device_state last_receipt_global_no: {$deviceStateLastGlobal}. " .
        "Reconciliation required before proceeding."
    );
}

// Calculate counters from device_state (NOT from receipts table)
DB::transaction(function () use ($deviceId, $fiscalDayNo, &$receiptData) {
    // Lock device_state row (prevents concurrent access)
    $lockedDeviceState = DeviceState::where('device_id', $deviceId)
        ->lockForUpdate()
        ->first();
    
    if (!$lockedDeviceState) {
        throw new \Exception("CRITICAL: device_state row disappeared during transaction");
    }
    
    // Calculate next counters from device_state (SINGLE SOURCE OF TRUTH)
    $nextReceiptCounter = $lockedDeviceState->getNextReceiptCounter($fiscalDayNo);
    $nextGlobalNo = $lockedDeviceState->getNextGlobalNo();
    
    Log::info('ZIMRA SubmitReceipt - Counters from device_state (LOCKED)', [
        'device_state_last_fiscal_day' => $lockedDeviceState->last_fiscal_day_no,
        'device_state_last_counter' => $lockedDeviceState->last_receipt_counter,
        'device_state_last_global' => $lockedDeviceState->last_receipt_global_no,
        'fdms_fiscal_day_no' => $fiscalDayNo,
        'next_receipt_counter' => $nextReceiptCounter,
        'next_global_no' => $nextGlobalNo,
    ]);
    
    $receiptData['receiptCounter'] = $nextReceiptCounter;
    $receiptData['receiptGlobalNo'] = $nextGlobalNo;
});
```

**Benefits:**
- ✅ Single source of truth for counters
- ✅ FDMS alignment check prevents divergence
- ✅ Reconciliation detection
- ✅ Atomic counter management

---

### **7. Added device_state Counter Increment (Lines 1925-1943)**

**After successful FDMS submission AND DB persistence:**

```php
/*
|--------------------------------------------------------------------------
| 🔟 Increment device_state Counters (CRITICAL)
|--------------------------------------------------------------------------
| ONLY increment after successful FDMS submission AND DB persistence
|--------------------------------------------------------------------------
*/
$deviceState->incrementCounters(
    $fiscalDayNo,
    $receiptData['receiptCounter'],
    $receiptData['receiptGlobalNo']
);

Log::info('device_state counters incremented', [
    'device_id' => $deviceId,
    'fiscal_day_no' => $fiscalDayNo,
    'receipt_counter' => $receiptData['receiptCounter'],
    'global_no' => $receiptData['receiptGlobalNo'],
]);
```

**Result:** Counters only increment after both FDMS and DB success, preventing divergence.

---

## 📊 Summary of Changes

| Method | Before | After |
|--------|--------|-------|
| `submitReceipt()` | Uses `getActive()` | Requires `device_id` param, loads specific device config |
| `getStatus()` | Uses `getActive()` | Requires `device_id` param, loads specific device config |
| `closeDay()` | Uses `getActive()` | Requires `device_id` param, loads specific device config |
| `openDay()` | Uses `getActive()` | Requires `device_id` param, loads specific device config |
| Counter calculation | `Receipt::max()` | `DeviceState::lockForUpdate()` + `getNextReceiptCounter()` |
| FDMS alignment | None | Checks FDMS vs device_state before proceeding |
| Counter increment | None | `$deviceState->incrementCounters()` after success |

---

## 🚨 Breaking Changes

**All controller methods must now pass `device_id`:**

```php
// OLD (BROKEN)
$zimraService->submitReceipt($receiptData);

// NEW (CORRECT)
$deviceId = $request->attributes->get('device_id'); // From middleware
$zimraService->submitReceipt($receiptData, $deviceId);
```

**Same for:**
- `getStatus($deviceId)`
- `closeDay($payload, $deviceId)`
- `openDay($fiscalDayNo, $deviceId)`

---

## ✅ What This Fixes

### **1. RCPT020 Issue**
- Device 32558 receipts now go through proper FDMS validation
- Device 32857 receipts use correct device configuration
- No more cross-device contamination

### **2. Multi-Company Isolation**
- Each company's receipts use their own device configuration
- No fallback to "active" device
- Strict device_id enforcement

### **3. Counter Integrity**
- Counters come from `device_states` (single source of truth)
- FDMS alignment check prevents divergence
- Atomic counter management with row locking

### **4. Reconciliation Safety**
- Detects when FDMS accepted but DB failed
- Blocks further submissions until reconciled
- Prevents cascading counter mismatches

---

## 🔧 Next Steps

1. **Update Controllers** to pass `device_id` from middleware
2. **Apply Middleware** to all fiscal routes
3. **Activate Device 32558** in `zimra_configs` table
4. **Test Both Devices** independently
5. **Verify RCPT020** is resolved for both devices

---

## 📋 Files Modified

- ✅ `app/Services/ZimraDeviceService.php` (3885 lines)
  - Added `DeviceState` import
  - Updated 4 method signatures
  - Replaced counter calculation logic
  - Added FDMS alignment check
  - Added counter increment after success

---

**Status:** Structural corrections complete
**Priority:** CRITICAL - Required for multi-company fiscal compliance
**Testing:** Ready for controller updates and end-to-end testing
