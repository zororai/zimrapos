# Multi-Company Isolation - Implementation Patches

## 📋 Required Code Changes

Apply these patches to implement strict multi-company data isolation.

---

## 1️⃣ Register Middleware

**File:** `app/Http/Kernel.php`

**Add to `$middlewareAliases` array:**

```php
protected $middlewareAliases = [
    // ... existing middleware
    'resolve.company.device' => \App\Http\Middleware\ResolveCompanyDevice::class,
];
```

---

## 2️⃣ Update FiscalDay Model

**File:** `app/Models/FiscalDay.php`

**Find:** `getCurrentOpen()` method

**Replace with:**

```php
/**
 * Get current open fiscal day for device
 * 
 * CRITICAL: device_id is REQUIRED for multi-company isolation
 * 
 * @param int $deviceId Device ID (required)
 * @return FiscalDay|null
 */
public static function getCurrentOpen(int $deviceId): ?self
{
    return self::where('device_id', $deviceId)
        ->where('is_closed', false)
        ->orderBy('fiscal_day_no', 'desc')
        ->first();
}

/**
 * Get fiscal day by number for device
 * 
 * @param int $deviceId Device ID (required)
 * @param int $fiscalDayNo Fiscal day number
 * @return FiscalDay|null
 */
public static function getByNumber(int $deviceId, int $fiscalDayNo): ?self
{
    return self::where('device_id', $deviceId)
        ->where('fiscal_day_no', $fiscalDayNo)
        ->first();
}
```

---

## 3️⃣ Add Scopes to Receipt Model

**File:** `app/Models/Receipt.php`

**Add these scope methods:**

```php
/**
 * Scope: Filter receipts by device
 * 
 * @param \Illuminate\Database\Eloquent\Builder $query
 * @param int $deviceId
 * @return \Illuminate\Database\Eloquent\Builder
 */
public function scopeForDevice($query, int $deviceId)
{
    return $query->where('device_id', $deviceId);
}

/**
 * Scope: Filter receipts by device and fiscal day
 * 
 * @param \Illuminate\Database\Eloquent\Builder $query
 * @param int $deviceId
 * @param int $fiscalDayNo
 * @return \Illuminate\Database\Eloquent\Builder
 */
public function scopeForFiscalDay($query, int $deviceId, int $fiscalDayNo)
{
    return $query->where('device_id', $deviceId)
                 ->where('fiscal_day_no', $fiscalDayNo);
}

/**
 * Get last receipt for device
 * 
 * @param int $deviceId
 * @return Receipt|null
 */
public static function getLastForDevice(int $deviceId): ?self
{
    return self::where('device_id', $deviceId)
        ->orderBy('receipt_global_no', 'desc')
        ->first();
}
```

---

## 4️⃣ Add Authorization Guard to ZimraDeviceService

**File:** `app/Services/ZimraDeviceService.php`

**Add this private method:**

```php
/**
 * Authorize device access for authenticated company
 * 
 * CRITICAL SECURITY: Prevents cross-company device access
 * 
 * @param int $deviceId Device ID to authorize
 * @throws \Exception If unauthorized
 */
private function authorizeDeviceAccess(int $deviceId): void
{
    $user = auth()->user();
    
    if (!$user) {
        throw new \Exception('Unauthenticated: User must be logged in');
    }
    
    // Get user's company
    $companyId = $user->company_id ?? null;
    
    if (!$companyId) {
        throw new \Exception('User is not assigned to any company');
    }
    
    $company = \App\Models\Company::find($companyId);
    
    if (!$company) {
        throw new \Exception('Company not found');
    }
    
    if (!$company->is_active) {
        throw new \Exception('Company is not active');
    }
    
    // CRITICAL: Verify device belongs to this company
    if (!$company->ownsDevice($deviceId)) {
        Log::critical('UNAUTHORIZED_DEVICE_ACCESS_ATTEMPT', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'company_id' => $company->id,
            'company_name' => $company->name,
            'company_device_id' => $company->device_id,
            'attempted_device_id' => $deviceId,
            'ip' => request()->ip(),
            'url' => request()->fullUrl(),
            'timestamp' => now()->toIso8601String(),
        ]);
        
        throw new \Exception(
            "Unauthorized: Device {$deviceId} does not belong to your company. " .
            "Your company's device is {$company->device_id}."
        );
    }
    
    Log::info('Device access authorized', [
        'user_id' => $user->id,
        'company_id' => $company->id,
        'device_id' => $deviceId,
    ]);
}
```

**Add guard to public methods:**

```php
public function submitReceipt(array $receiptData, ?int $deviceId = null): array
{
    // Get device ID from config if not provided
    if (!$deviceId) {
        $zimraConfig = ZimraConfig::getActive();
        $deviceId = $zimraConfig->device_id ?? null;
    }
    
    if (!$deviceId) {
        throw new \Exception('No device ID provided or configured');
    }
    
    // CRITICAL: Authorize device access
    $this->authorizeDeviceAccess($deviceId);
    
    // Rest of method...
}

public function closeDay(?int $deviceId = null): array
{
    // Get device ID from config if not provided
    if (!$deviceId) {
        $zimraConfig = ZimraConfig::getActive();
        $deviceId = $zimraConfig->device_id ?? null;
    }
    
    if (!$deviceId) {
        throw new \Exception('No device ID provided or configured');
    }
    
    // CRITICAL: Authorize device access
    $this->authorizeDeviceAccess($deviceId);
    
    // Rest of method...
}

public function getStatus(?int $deviceId = null): array
{
    // Get device ID from config if not provided
    if (!$deviceId) {
        $zimraConfig = ZimraConfig::getActive();
        $deviceId = $zimraConfig->device_id ?? null;
    }
    
    if (!$deviceId) {
        throw new \Exception('No device ID provided or configured');
    }
    
    // CRITICAL: Authorize device access
    $this->authorizeDeviceAccess($deviceId);
    
    // Rest of method...
}
```

---

## 5️⃣ Apply Middleware to Routes

**File:** `routes/api.php`

**Wrap fiscal routes with middleware:**

```php
// Multi-company protected routes
Route::middleware(['auth:sanctum', 'resolve.company.device'])->group(function () {
    
    // Receipt operations
    Route::post('/zimra/receipts/submit', [ZimraController::class, 'submitReceipt']);
    Route::get('/zimra/receipts/{id}', [ZimraController::class, 'getReceipt']);
    Route::get('/zimra/receipts', [ZimraController::class, 'listReceipts']);
    
    // Fiscal day operations
    Route::post('/zimra/fiscal-day/open', [ZimraController::class, 'openDay']);
    Route::post('/zimra/fiscal-day/close', [ZimraController::class, 'closeDay']);
    Route::get('/zimra/fiscal-day/status', [ZimraController::class, 'getDayStatus']);
    Route::get('/zimra/fiscal-day/current', [ZimraController::class, 'getCurrentDay']);
    
    // Device status
    Route::get('/zimra/device/status', [ZimraController::class, 'getDeviceStatus']);
    Route::get('/zimra/device/config', [ZimraController::class, 'getDeviceConfig']);
});
```

---

## 6️⃣ Update Controllers

**File:** `app/Http/Controllers/ZimraController.php` (or similar)

**Extract device_id from middleware:**

```php
<?php

namespace App\Http\Controllers;

use App\Services\ZimraDeviceService;
use Illuminate\Http\Request;

class ZimraController extends Controller
{
    public function __construct(
        private ZimraDeviceService $zimraService
    ) {}
    
    /**
     * Submit receipt
     * 
     * CRITICAL: device_id comes from middleware, NOT from request input
     */
    public function submitReceipt(Request $request)
    {
        // Get device_id from middleware (set by ResolveCompanyDevice)
        $deviceId = $request->attributes->get('device_id');
        
        // Validate request
        $validated = $request->validate([
            'invoiceNo' => 'required|string',
            'receiptType' => 'required|string',
            'receiptCurrency' => 'required|string',
            'receiptDate' => 'required|string',
            'receiptLines' => 'required|array',
            'receiptTaxes' => 'required|array',
            'receiptPayments' => 'required|array',
            'receiptTotal' => 'required|numeric',
            // DO NOT accept device_id from request
        ]);
        
        try {
            $result = $this->zimraService->submitReceipt($validated, $deviceId);
            
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Close fiscal day
     */
    public function closeDay(Request $request)
    {
        // Get device_id from middleware
        $deviceId = $request->attributes->get('device_id');
        
        try {
            $result = $this->zimraService->closeDay($deviceId);
            
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Get device status
     */
    public function getDeviceStatus(Request $request)
    {
        // Get device_id from middleware
        $deviceId = $request->attributes->get('device_id');
        
        try {
            $status = $this->zimraService->getStatus($deviceId);
            
            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * List receipts for company's device
     */
    public function listReceipts(Request $request)
    {
        // Get device_id from middleware
        $deviceId = $request->attributes->get('device_id');
        
        // Get company from middleware
        $company = $request->attributes->get('company');
        
        $receipts = \App\Models\Receipt::forDevice($deviceId)
            ->orderBy('receipt_global_no', 'desc')
            ->paginate(50);
        
        return response()->json([
            'success' => true,
            'data' => $receipts,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'device_id' => $company->device_id,
            ],
        ]);
    }
}
```

---

## 7️⃣ Add Company Relationship to User Model

**File:** `app/Models/User.php`

**Add relationship:**

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Get the company this user belongs to
 */
public function company(): BelongsTo
{
    return $this->belongsTo(Company::class);
}
```

**Add to migration (if company_id doesn't exist):**

```php
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null');
});
```

---

## 8️⃣ Example Usage

### **Company A submits receipt:**

```php
// User from Company A logs in
$userA = User::where('company_id', 1)->first(); // Company A
auth()->login($userA);

// Submit receipt (device_id automatically resolved from company)
POST /api/zimra/receipts/submit
{
  "invoiceNo": "INV-001",
  "receiptType": "FiscalReceipt",
  // ... other fields
  // NO device_id in request
}

// Middleware resolves: Company A → device_id 32558
// Receipt saved with device_id 32558
// receipt_global_no = 1 (for device 32558)
```

### **Company B submits receipt:**

```php
// User from Company B logs in
$userB = User::where('company_id', 2)->first(); // Company B
auth()->login($userB);

// Submit receipt
POST /api/zimra/receipts/submit
{
  "invoiceNo": "INV-001", // Same invoice number as Company A
  "receiptType": "FiscalReceipt",
  // ... other fields
}

// Middleware resolves: Company B → device_id 32857
// Receipt saved with device_id 32857
// receipt_global_no = 1 (for device 32857)
// NO conflict with Company A's receipt
```

### **Company A tries to access Company B's device:**

```php
// User from Company A logs in
auth()->login($userA);

// Try to submit to Company B's device (malicious attempt)
POST /api/zimra/receipts/submit
{
  "device_id": 32857, // Company B's device
  "invoiceNo": "INV-HACK",
  // ...
}

// Middleware detects mismatch:
// - User's company device_id: 32558
// - Requested device_id: 32857
// Response: 403 Forbidden
// Log: UNAUTHORIZED_DEVICE_ACCESS_ATTEMPT
```

---

## 9️⃣ Testing Script

**File:** `tests/Feature/MultiCompanyIsolationTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_companies_have_isolated_receipts()
    {
        // Create two companies
        $companyA = Company::create([
            'name' => 'Company A',
            'tin' => '1000000000',
            'device_id' => 32558,
            'is_active' => true,
        ]);
        
        $companyB = Company::create([
            'name' => 'Company B',
            'tin' => '2000000000',
            'device_id' => 32857,
            'is_active' => true,
        ]);
        
        // Create users
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $userB = User::factory()->create(['company_id' => $companyB->id]);
        
        // Company A submits receipt
        $this->actingAs($userA)
            ->postJson('/api/zimra/receipts/submit', [
                'invoiceNo' => 'INV-A-001',
                // ... receipt data
            ])
            ->assertStatus(200);
        
        // Company B submits receipt
        $this->actingAs($userB)
            ->postJson('/api/zimra/receipts/submit', [
                'invoiceNo' => 'INV-B-001',
                // ... receipt data
            ])
            ->assertStatus(200);
        
        // Verify isolation
        $receiptsA = Receipt::forDevice($companyA->device_id)->count();
        $receiptsB = Receipt::forDevice($companyB->device_id)->count();
        
        $this->assertEquals(1, $receiptsA);
        $this->assertEquals(1, $receiptsB);
        
        // Verify both can have receipt_global_no = 1
        $receiptA = Receipt::forDevice($companyA->device_id)->first();
        $receiptB = Receipt::forDevice($companyB->device_id)->first();
        
        $this->assertEquals(1, $receiptA->receipt_global_no);
        $this->assertEquals(1, $receiptB->receipt_global_no);
    }
    
    public function test_user_cannot_access_other_company_device()
    {
        $companyA = Company::create([
            'name' => 'Company A',
            'tin' => '1000000000',
            'device_id' => 32558,
            'is_active' => true,
        ]);
        
        $companyB = Company::create([
            'name' => 'Company B',
            'tin' => '2000000000',
            'device_id' => 32857,
            'is_active' => true,
        ]);
        
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        
        // Try to submit to Company B's device
        $this->actingAs($userA)
            ->postJson('/api/zimra/receipts/submit', [
                'device_id' => $companyB->device_id, // Unauthorized
                'invoiceNo' => 'INV-HACK',
                // ...
            ])
            ->assertStatus(403)
            ->assertJson([
                'error' => 'UnauthorizedDeviceAccess',
            ]);
    }
}
```

---

## 🔟 Verification Commands

```bash
# 1. Run migrations
php artisan migrate

# 2. Seed companies from zimra_configs
# (automatically done in migration)

# 3. Verify companies created
php artisan tinker
>>> Company::all()

# 4. Assign users to companies
>>> User::find(1)->update(['company_id' => 1]);

# 5. Test isolation
>>> $companyA = Company::find(1);
>>> $companyB = Company::find(2);
>>> Receipt::forDevice($companyA->device_id)->count();
>>> Receipt::forDevice($companyB->device_id)->count();

# 6. Run tests
php artisan test --filter MultiCompanyIsolationTest
```

---

## ✅ Implementation Checklist

- [ ] Register middleware in `app/Http/Kernel.php`
- [ ] Update `FiscalDay::getCurrentOpen()` to require device_id
- [ ] Add scopes to `Receipt` model
- [ ] Add `authorizeDeviceAccess()` to `ZimraDeviceService`
- [ ] Add authorization guards to service methods
- [ ] Apply middleware to routes in `routes/api.php`
- [ ] Update controllers to use middleware device_id
- [ ] Add `company_id` to users table
- [ ] Add `company()` relationship to User model
- [ ] Run migrations
- [ ] Assign users to companies
- [ ] Test cross-company isolation
- [ ] Verify database constraints
- [ ] Run automated tests

---

**Status:** Ready for implementation
**Priority:** CRITICAL - Required for multi-company fiscal compliance
