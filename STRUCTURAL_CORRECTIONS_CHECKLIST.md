# Structural Corrections - Implementation Checklist

## 🎯 Objective

Hard architectural corrections for multi-company ZIMRA FDMS integration.

---

## ✅ Completed

### 1️⃣ Database Constraints Fixed

**File:** `database/migrations/2026_02_26_082200_fix_receipts_constraints_multi_company.php`

**Changes:**
- ✅ Drops global unique constraint on `receipt_global_no`
- ✅ Adds `UNIQUE (device_id, receipt_global_no)`
- ✅ Adds `UNIQUE (device_id, fiscal_day_no, receipt_counter)`
- ✅ Safe constraint checking before drop/add
- ✅ MySQL compatible

**Result:** Company A and Company B can both have receipt #1

---

### 2️⃣ Company-Device Mapping Created

**File:** `database/migrations/2026_02_26_082300_create_company_devices_table.php`

**Changes:**
- ✅ Creates `company_devices` table
- ✅ Supports multiple devices per company
- ✅ One device belongs to exactly one company
- ✅ Active device selection via `is_active` flag
- ✅ Auto-migrates existing company → device_id mappings

**File:** `app/Models/CompanyDevice.php`

**Methods:**
- ✅ `getActiveForCompany(int $companyId)`
- ✅ `getCompanyIdForDevice(int $deviceId)`
- ✅ `deviceBelongsToCompany(int $deviceId, int $companyId)`

---

### 3️⃣ Company Model Updated

**File:** `app/Models/Company.php`

**Changes:**
- ✅ Uses `company_devices` relationship instead of direct `device_id`
- ✅ `devices()` - HasMany relationship
- ✅ `activeDevice()` - Get active device
- ✅ `getActiveDeviceId()` - Get active device ID
- ✅ `ownsDevice(int $deviceId)` - Verify ownership via CompanyDevice

---

### 4️⃣ Middleware Hardened

**File:** `app/Http/Middleware/ResolveCompanyDevice.php`

**Security Checks:**
1. ✅ Authenticate user
2. ✅ Load user's company
3. ✅ Verify company is active
4. ✅ Load active device from `company_devices`
5. ✅ Detect cross-company device access attempts
6. ✅ Log security violations with full audit trail
7. ✅ Inject `device_id` into request attributes (ONLY way to set device_id)

**Logging:**
- ✅ `USER_WITHOUT_COMPANY`
- ✅ `COMPANY_NOT_FOUND`
- ✅ `INACTIVE_COMPANY_ACCESS_ATTEMPT`
- ✅ `NO_ACTIVE_DEVICE`
- ✅ `UNAUTHORIZED_DEVICE_ACCESS_ATTEMPT` (CRITICAL)
- ✅ `DEVICE_ACCESS_AUTHORIZED`

---

### 5️⃣ Service Methods Corrected

**File:** `app/Services/ZimraDeviceService_CORRECTED.php`

**Critical Fixes:**

**NO Device Fallback:**
```php
// ❌ OLD (WRONG)
if (!$deviceId) {
    $zimraConfig = ZimraConfig::getActive();
    $deviceId = $zimraConfig->device_id ?? null;
}

// ✅ NEW (CORRECT)
if (!$deviceId) {
    throw new \Exception('device_id is required from middleware');
}
```

**Counters from device_states ONLY:**
```php
// ❌ OLD (WRONG)
$maxReceiptCounter = Receipt::where('device_id', $deviceId)
    ->max('receipt_counter');
$nextReceiptCounter = ($maxReceiptCounter ?? 0) + 1;

// ✅ NEW (CORRECT)
$deviceState = DeviceState::where('device_id', $deviceId)
    ->lockForUpdate()
    ->first();
$nextReceiptCounter = $deviceState->getNextReceiptCounter($fiscalDayNo);
```

**FDMS Alignment Check:**
```php
// ✅ NEW - Verify FDMS state matches device_state before proceeding
$this->verifyFdmsAlignment($deviceState, $fdmsStatus, $deviceId);
```

**Methods Updated:**
- ✅ `submitReceipt(array $receiptData, int $deviceId)` - Required device_id
- ✅ `closeDay(int $deviceId)` - Required device_id
- ✅ `getStatus(int $deviceId)` - Required device_id
- ✅ `verifyFdmsAlignment()` - New method for state alignment check

---

### 6️⃣ Tests with Mocked FDMS

**File:** `tests/Feature/MultiCompanyIsolationTest.php`

**Mocking:**
- ✅ `Http::fake()` for FDMS API responses
- ✅ Deterministic test results (no live FDMS dependency)

**Test Cases:**
1. ✅ `test_companies_have_isolated_receipts()` - Verify data isolation
2. ✅ `test_user_cannot_access_other_company_device()` - Verify security
3. ✅ `test_database_constraints_enforce_per_device_uniqueness()` - Verify constraints
4. ✅ `test_middleware_resolves_correct_device_id()` - Verify middleware
5. ✅ `test_company_can_have_multiple_devices()` - Verify multi-device support
6. ✅ `test_device_state_isolated_per_device()` - Verify state isolation

---

### 7️⃣ Documentation Created

**Files:**
- ✅ `STRUCTURAL_CORRECTIONS_COUNTER_LOGIC.md` - Counter logic fix documentation
- ✅ `STRUCTURAL_CORRECTIONS_CHECKLIST.md` - This file

---

## ⚠️ Pending Implementation

### Apply Changes to Existing ZimraDeviceService.php

**File:** `app/Services/ZimraDeviceService.php`

**Required Changes:**

1. **Remove device fallback (Lines ~1546-1573):**
```php
// Replace this entire block:
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

// With this:
DB::transaction(function () use ($deviceId, $fiscalDayNo, &$receiptData) {
    $deviceState = DeviceState::where('device_id', $deviceId)
        ->lockForUpdate()
        ->first();
    
    if (!$deviceState) {
        throw new \Exception("No device_state found for device {$deviceId}");
    }
    
    if ($deviceState->requires_reconciliation) {
        throw new \Exception("Device requires reconciliation");
    }
    
    $nextReceiptCounter = $deviceState->getNextReceiptCounter($fiscalDayNo);
    $nextGlobalNo = $deviceState->getNextGlobalNo();
    
    $receiptData['receiptCounter'] = $nextReceiptCounter;
    $receiptData['receiptGlobalNo'] = $nextGlobalNo;
});
```

2. **Add FDMS alignment check before counter increment:**
```php
// Add at start of submitReceipt()
$fdmsStatus = $this->getStatus($deviceId);
$deviceState = DeviceState::where('device_id', $deviceId)->first();
$this->verifyFdmsAlignment($deviceState, $fdmsStatus, $deviceId);
```

3. **Add verifyFdmsAlignment() method:**
```php
private function verifyFdmsAlignment(DeviceState $deviceState, array $fdmsStatus, int $deviceId): void
{
    $fdmsLastGlobal = $fdmsStatus['lastReceiptGlobalNo'] ?? 0;
    $deviceStateLastGlobal = $deviceState->last_receipt_global_no;
    
    if ($fdmsLastGlobal !== $deviceStateLastGlobal) {
        throw new \Exception(
            "FDMS/device_state misalignment: " .
            "FDMS={$fdmsLastGlobal}, device_state={$deviceStateLastGlobal}"
        );
    }
}
```

4. **Remove all device fallback logic:**
```php
// Find and remove all instances of:
if (!$deviceId) {
    $zimraConfig = ZimraConfig::getActive();
    $deviceId = $zimraConfig->device_id ?? null;
}

// Replace with:
if (!$deviceId) {
    throw new \Exception('device_id is required from middleware');
}
```

---

### Register Middleware

**File:** `app/Http/Kernel.php`

**Add to `$middlewareAliases`:**
```php
'resolve.company.device' => \App\Http\Middleware\ResolveCompanyDevice::class,
```

---

### Apply Middleware to Routes

**File:** `routes/api.php`

**Wrap fiscal routes:**
```php
Route::middleware(['auth:sanctum', 'resolve.company.device'])->group(function () {
    Route::post('/zimra/receipts/submit', [ZimraController::class, 'submitReceipt']);
    Route::post('/zimra/fiscal-day/close', [ZimraController::class, 'closeDay']);
    Route::get('/zimra/device/status', [ZimraController::class, 'getDeviceStatus']);
});
```

---

### Update Controllers

**File:** `app/Http/Controllers/ZimraController.php`

**Extract device_id from middleware:**
```php
public function submitReceipt(Request $request)
{
    // Get device_id from middleware (NOT from request input)
    $deviceId = $request->attributes->get('device_id');
    
    $result = $this->zimraService->submitReceipt($validated, $deviceId);
    
    return response()->json($result);
}
```

---

### Add company_id to Users Table

**Migration:**
```php
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null');
});
```

---

## 🧪 Verification Steps

### 1. Run Migrations
```bash
php artisan migrate
```

**Expected:**
```
✓ Receipts constraints fixed
✓ company_devices table created
✓ Existing mappings migrated
```

### 2. Verify Constraints
```sql
SHOW INDEX FROM receipts WHERE Key_name LIKE '%receipt_global_no%';
```

**Expected:**
- `receipts_device_global_unique` (device_id, receipt_global_no)
- NO global unique on receipt_global_no alone

### 3. Test Multi-Company Isolation
```bash
php artisan test --filter MultiCompanyIsolationTest
```

**Expected:** All tests pass

### 4. Verify No Receipt::max() Usage
```bash
grep -r "Receipt::.*max(" app/Services/
```

**Expected:** No results (all replaced with device_states)

### 5. Verify No Device Fallback
```bash
grep -r "ZimraConfig::getActive" app/Services/ZimraDeviceService.php
```

**Expected:** No results in submitReceipt/closeDay/getStatus methods

---

## 📋 Final Checklist

- [ ] Migrations run successfully
- [ ] `receipts_device_global_unique` constraint exists
- [ ] `receipts_device_day_counter_unique` constraint exists
- [ ] NO global unique on `receipt_global_no`
- [ ] `company_devices` table exists
- [ ] `CompanyDevice` model created
- [ ] `Company` model uses `company_devices` relationship
- [ ] Middleware registered in `Kernel.php`
- [ ] Middleware applied to fiscal routes
- [ ] Controllers extract `device_id` from middleware
- [ ] Service methods require `device_id` parameter
- [ ] NO device fallback in service methods
- [ ] Counters come ONLY from `device_states`
- [ ] NO `Receipt::max()` usage
- [ ] FDMS alignment check implemented
- [ ] Tests use mocked FDMS responses
- [ ] All tests pass
- [ ] `users` table has `company_id` column
- [ ] Users assigned to companies

---

## 🚨 Critical Rules

### DO:
✅ Always pass `device_id` from middleware
✅ Always use `DeviceState::lockForUpdate()`
✅ Always check `requires_reconciliation` flag
✅ Always verify FDMS alignment before counter increment
✅ Always filter queries by `device_id`
✅ Always log security violations

### DON'T:
❌ Never use `Receipt::max()` for counters
❌ Never fallback to `ZimraConfig::getActive()`
❌ Never allow `device_id` from request input
❌ Never skip FDMS alignment check
❌ Never increment counters before DB persistence
❌ Never trust user-provided `device_id`

---

**Status:** Structural corrections ready for final implementation
**Priority:** CRITICAL - Fiscal compliance requirement
**Risk:** HIGH - Cross-company contamination if not implemented correctly
