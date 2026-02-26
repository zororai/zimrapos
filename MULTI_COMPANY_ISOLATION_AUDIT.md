# Multi-Company Data Isolation - Code Audit & Fixes

## 🎯 Objective

Enforce strict data isolation between companies/devices in ZIMRA FDMS system.

**CRITICAL REQUIREMENT:** Every query touching `receipts`, `fiscal_days`, or `device_state` MUST include `where('device_id', $deviceId)`.

---

## ✅ Files Already Compliant

### **ZimraDeviceService.php**

All queries already include `device_id` filter:

```php
// Line 801-803: Fiscal day lookup
$fiscalDay = FiscalDay::where('device_id', $deviceId)
    ->where('fiscal_day_no', $zimraStatus['lastFiscalDayNo'] ?? 0)
    ->first();

// Line 969-972: Receipt lookup for CloseDay
$receipts = Receipt::where('device_id', $deviceId)
    ->where('fiscal_day_no', $fiscalDay->fiscal_day_no)
    ->where('is_valid', true)
    ->get();

// Line 984-987: Red errors check
$receiptsWithRedErrors = Receipt::where('device_id', $deviceId)
    ->where('fiscal_day_no', $fiscalDay->fiscal_day_no)
    ->where('has_red_errors', true)
    ->get();

// Line 989-992: Gray errors check
$receiptsWithGrayErrors = Receipt::where('device_id', $deviceId)
    ->where('fiscal_day_no', $fiscalDay->fiscal_day_no)
    ->where('has_gray_errors', true)
    ->get();

// Line 1527-1529: Invoice uniqueness check
$existingReceipt = Receipt::where('device_id', $deviceId)
    ->where('invoice_no', $invoiceNo)
    ->first();

// Line 1548-1551: Receipt counter calculation
$maxReceiptCounter = Receipt::where('device_id', $deviceId)
    ->where('fiscal_day_no', $fiscalDayNo)
    ->lockForUpdate()
    ->max('receipt_counter');

// Line 1556-1558: Global counter calculation
$maxGlobalNo = Receipt::where('device_id', $deviceId)
    ->lockForUpdate()
    ->max('receipt_global_no');

// Line 3684-3687: Previous receipt hash lookup
$previousReceipt = Receipt::where('device_id', $deviceId)
    ->where('fiscal_day_no', $fiscalDayNo)
    ->where('receipt_counter', $currentReceiptCounter - 1)
    ->first();
```

✅ **Status:** All queries properly filtered by device_id

---

### **ZimraDeviceService_REFACTORED.php**

All queries already include `device_id` filter:

```php
// Line 76: Device state lookup
$deviceState = DeviceState::where('device_id', $deviceId)->first();

// Line 232-234: Device state locking
$lockedDeviceState = DeviceState::where('device_id', $deviceId)
    ->lockForUpdate()
    ->first();
```

✅ **Status:** All queries properly filtered by device_id

---

## ⚠️ Files Requiring Updates

### **FiscalDay Model**

**File:** `app/Models/FiscalDay.php`

**Issue:** `getCurrentOpen()` method may not enforce device_id filter

**Current Code:**
```php
public static function getCurrentOpen($deviceId = null)
{
    $query = self::where('is_closed', false);
    
    if ($deviceId) {
        $query->where('device_id', $deviceId);
    }
    
    return $query->orderBy('fiscal_day_no', 'desc')->first();
}
```

**Problem:** If `$deviceId` is null, returns ANY open fiscal day (cross-company leak)

**Fix Required:**
```php
public static function getCurrentOpen(int $deviceId)
{
    // CRITICAL: device_id is now REQUIRED, not optional
    return self::where('device_id', $deviceId)
        ->where('is_closed', false)
        ->orderBy('fiscal_day_no', 'desc')
        ->first();
}
```

---

### **Receipt Model**

**File:** `app/Models/Receipt.php`

**Check for:** Any scope or query methods that don't enforce device_id

**Required Scopes:**
```php
// Add to Receipt model
public function scopeForDevice($query, int $deviceId)
{
    return $query->where('device_id', $deviceId);
}

public function scopeForFiscalDay($query, int $deviceId, int $fiscalDayNo)
{
    return $query->where('device_id', $deviceId)
                 ->where('fiscal_day_no', $fiscalDayNo);
}
```

---

### **DeviceState Model**

**File:** `app/Models/DeviceState.php`

**Current Code (Line 70-90):**
```php
public function getNextReceiptCounter(int $currentFiscalDayNo): int
{
    // If fiscal day changed, reset counter to 1
    if ($this->last_fiscal_day_no !== $currentFiscalDayNo) {
        return 1;
    }
    
    // Otherwise increment from last counter
    return $this->last_receipt_counter + 1;
}
```

✅ **Status:** Already operates on single device_state row (implicitly filtered)

---

### **Controllers**

**Files to Audit:**
- `app/Http/Controllers/ZimraController.php`
- `app/Http/Controllers/FiscalDayController.php`
- Any other controllers calling fiscal services

**Required Changes:**

1. **Apply ResolveCompanyDevice middleware:**
```php
// In route definition
Route::middleware(['auth', 'resolve.company.device'])->group(function () {
    Route::post('/receipts/submit', [ZimraController::class, 'submitReceipt']);
    Route::post('/fiscal-day/close', [FiscalDayController::class, 'closeDay']);
    Route::get('/fiscal-day/status', [FiscalDayController::class, 'getStatus']);
});
```

2. **Extract device_id from request (set by middleware):**
```php
public function submitReceipt(Request $request)
{
    // CRITICAL: Get device_id from middleware, NOT from request input
    $deviceId = $request->attributes->get('device_id');
    
    // NEVER allow frontend to pass device_id
    // $deviceId = $request->input('device_id'); // ❌ WRONG
    
    $result = $this->zimraService->submitReceipt($receiptData, $deviceId);
    
    return response()->json($result);
}
```

---

## 🔒 Service-Level Authorization Guards

### **Add to ZimraDeviceService**

**At the start of every public method:**

```php
public function submitReceipt(array $receiptData, int $deviceId): array
{
    // CRITICAL: Verify device belongs to authenticated company
    $this->authorizeDeviceAccess($deviceId);
    
    // Rest of method...
}

public function closeDay(int $deviceId): array
{
    // CRITICAL: Verify device belongs to authenticated company
    $this->authorizeDeviceAccess($deviceId);
    
    // Rest of method...
}

private function authorizeDeviceAccess(int $deviceId): void
{
    $user = auth()->user();
    
    if (!$user) {
        throw new \Exception('Unauthenticated');
    }
    
    $company = Company::find($user->company_id);
    
    if (!$company) {
        throw new \Exception('User not assigned to any company');
    }
    
    if (!$company->ownsDevice($deviceId)) {
        Log::critical('UNAUTHORIZED_DEVICE_ACCESS', [
            'user_id' => $user->id,
            'company_id' => $company->id,
            'company_device_id' => $company->device_id,
            'attempted_device_id' => $deviceId,
        ]);
        
        throw new \Exception('Unauthorized: Device does not belong to your company');
    }
}
```

---

## 📊 Database Constraint Updates

### **Migration Already Created**

**File:** `database/migrations/2026_02_26_081600_update_constraints_for_multi_device.php`

**Changes:**
1. ✅ Remove global unique on `receipt_global_no`
2. ✅ Add unique `(device_id, receipt_global_no)`
3. ✅ Add unique `(device_id, fiscal_day_no, receipt_counter)`
4. ✅ Add unique `(device_id, fiscal_day_no)` on fiscal_days
5. ✅ Add unique `device_id` on device_state

**Result:** Receipt #1 can exist for Company A and Company B separately

---

## 🧪 Verification Checklist

### **1. Query Audit**

Run this to find any queries missing device_id filter:

```bash
# Search for Receipt queries without device_id
grep -r "Receipt::" app/Services app/Http/Controllers | grep -v "device_id"

# Search for FiscalDay queries without device_id
grep -r "FiscalDay::" app/Services app/Http/Controllers | grep -v "device_id"

# Search for DeviceState queries without device_id
grep -r "DeviceState::" app/Services app/Http/Controllers | grep -v "device_id"
```

### **2. Test Cross-Company Isolation**

```php
// Company A submits receipt
$companyA = Company::where('device_id', 32558)->first();
auth()->login($companyA->users->first());

$receiptA = $zimraService->submitReceipt([...], 32558);
// Expected: receipt_global_no = 1, receipt_counter = 1

// Company B submits receipt
$companyB = Company::where('device_id', 32857)->first();
auth()->login($companyB->users->first());

$receiptB = $zimraService->submitReceipt([...], 32857);
// Expected: receipt_global_no = 1, receipt_counter = 1

// Verify isolation
$countA = Receipt::where('device_id', 32558)->count(); // Should be 1
$countB = Receipt::where('device_id', 32857)->count(); // Should be 1

// Verify Company A cannot access Company B's device
auth()->login($companyA->users->first());
try {
    $zimraService->submitReceipt([...], 32857); // Company B's device
    // Should throw UnauthorizedException
} catch (\Exception $e) {
    echo "✓ Cross-company access blocked: {$e->getMessage()}";
}
```

### **3. Test Middleware Protection**

```bash
# Try to submit receipt with wrong device_id in request
curl -X POST /api/receipts/submit \
  -H "Authorization: Bearer {company_a_token}" \
  -d '{"device_id": 32857, ...}'

# Expected: 403 Forbidden - "Unauthorized device access"
```

### **4. Verify Database Constraints**

```sql
-- Try to insert duplicate global_no for SAME device (should fail)
INSERT INTO receipts (device_id, receipt_global_no, ...) VALUES (32558, 1, ...);
INSERT INTO receipts (device_id, receipt_global_no, ...) VALUES (32558, 1, ...);
-- Expected: Duplicate entry error

-- Try to insert same global_no for DIFFERENT devices (should succeed)
INSERT INTO receipts (device_id, receipt_global_no, ...) VALUES (32558, 1, ...);
INSERT INTO receipts (device_id, receipt_global_no, ...) VALUES (32857, 1, ...);
-- Expected: Success (different devices can have same global_no)
```

---

## 📝 Code Changes Summary

### **Files Created:**
1. ✅ `database/migrations/2026_02_26_081500_create_companies_table.php`
2. ✅ `database/migrations/2026_02_26_081600_update_constraints_for_multi_device.php`
3. ✅ `app/Models/Company.php`
4. ✅ `app/Http/Middleware/ResolveCompanyDevice.php`

### **Files to Update:**
1. ⚠️ `app/Models/FiscalDay.php` - Make device_id required in `getCurrentOpen()`
2. ⚠️ `app/Models/Receipt.php` - Add device-scoped query methods
3. ⚠️ `app/Services/ZimraDeviceService.php` - Add `authorizeDeviceAccess()` guard
4. ⚠️ `app/Http/Kernel.php` - Register middleware
5. ⚠️ `routes/api.php` - Apply middleware to fiscal routes
6. ⚠️ Controllers - Extract device_id from middleware, not request input

---

## 🚨 Critical Rules

### **DO:**
✅ Always filter by `device_id` in ALL queries
✅ Get `device_id` from middleware/company, NEVER from request input
✅ Verify device ownership before ANY fiscal operation
✅ Use database constraints to enforce uniqueness per device
✅ Log unauthorized access attempts

### **DON'T:**
❌ Allow `device_id` to be passed from frontend
❌ Make `device_id` optional in query methods
❌ Allow cross-company data access
❌ Trust user input for device selection
❌ Skip authorization checks

---

## 📋 Implementation Order

1. ✅ Run migrations (companies table + constraints)
2. ⚠️ Register middleware in `app/Http/Kernel.php`
3. ⚠️ Update FiscalDay model (required device_id)
4. ⚠️ Add authorization guards to services
5. ⚠️ Apply middleware to routes
6. ⚠️ Update controllers to use middleware device_id
7. ✅ Test cross-company isolation
8. ✅ Verify database constraints

---

## 🎯 Success Criteria

After implementation:

- ✅ Company A cannot see Company B's receipts
- ✅ Company A cannot submit receipts to Company B's device
- ✅ Receipt counters are independent per device
- ✅ Fiscal days are independent per device
- ✅ Database enforces per-device uniqueness
- ✅ Unauthorized access attempts are logged
- ✅ No queries exist without device_id filter

**Status:** Multi-company isolation architecture ready for implementation
