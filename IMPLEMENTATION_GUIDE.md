# Atomic Fiscal State - Implementation Guide

## 🎯 Overview

This guide walks you through implementing the atomic fiscal state architecture to eliminate CountersMismatch errors and ensure FDMS/DB consistency.

---

## 📋 Files Created

### **1. Database Migration**
- **File:** `database/migrations/2026_02_26_051200_create_device_state_table.php`
- **Purpose:** Creates `device_state` table (single source of truth for counters)
- **Auto-seeds:** Initializes with current counter values from receipts table

### **2. DeviceState Model**
- **File:** `app/Models/DeviceState.php`
- **Purpose:** Eloquent model for device_state table
- **Methods:**
  - `getNextReceiptCounter($fiscalDayNo)` - Calculate next counter
  - `getNextGlobalNo()` - Calculate next global number
  - `incrementCounters($fiscalDayNo, $counter, $globalNo)` - Update after success
  - `markForReconciliation($error)` - Flag FDMS/DB divergence
  - `clearReconciliation()` - Clear flag after manual fix

### **3. Refactored Service**
- **File:** `app/Services/ZimraDeviceService_REFACTORED.php`
- **Purpose:** Complete refactor of `submitReceipt()` method
- **Key Changes:**
  - Uses `device_state` instead of `MAX()` queries
  - Entire flow wrapped in single `DB::transaction()`
  - Row locking with `lockForUpdate()`
  - Same counter variables used for signing, FDMS, and DB
  - Detects and flags FDMS/DB divergence

### **4. Reconciliation Command**
- **File:** `app/Console/Commands/ReconcileDeviceState.php`
- **Purpose:** Handle FDMS/DB divergence scenarios
- **Usage:**
  ```bash
  php artisan zimra:reconcile-device-state 32558 --check-only
  php artisan zimra:reconcile-device-state 32558 --clear
  ```

### **5. Documentation**
- **File:** `ATOMIC_FISCAL_STATE_ARCHITECTURE.md`
- **Purpose:** Complete architectural documentation

---

## 🚀 Step-by-Step Implementation

### **Step 1: Run Migration**

```bash
cd c:\Users\Mazarura\Herd\zimra
php artisan migrate
```

**Expected Output:**
```
Running migrations.
2026_02_26_051200_create_device_state_table ......... DONE

✓ Seeded device_state for device 32558: Global #61, Day 11, Counter 1
```

**Verify:**
```bash
php artisan tinker
>>> DeviceState::first()
```

**Expected:**
```php
App\Models\DeviceState {
  device_id: 32558,
  last_receipt_global_no: 61,
  last_receipt_counter: 1,
  last_fiscal_day_no: 11,
  requires_reconciliation: false,
}
```

---

### **Step 2: Backup Current Service**

```bash
cp app/Services/ZimraDeviceService.php app/Services/ZimraDeviceService_BACKUP.php
```

---

### **Step 3: Replace submitReceipt() Method**

Open `app/Services/ZimraDeviceService.php` and replace the entire `submitReceipt()` method with the refactored version from `ZimraDeviceService_REFACTORED.php`.

**Key sections to replace:**

1. **Add DeviceState import** (top of file):
```php
use App\Models\DeviceState;
```

2. **Replace entire submitReceipt() method** (lines ~1404-1941)

**Critical changes:**
- Remove old counter calculation with `MAX()`
- Add reconciliation check at start
- Wrap entire flow in `DB::transaction()`
- Use `DeviceState::lockForUpdate()` for counter calculation
- Add FDMS/DB divergence detection
- Increment `device_state` only after successful DB insert

---

### **Step 4: Test with Dry Run**

```bash
php artisan tinker
```

```php
// Check device state
$deviceState = \App\Models\DeviceState::where('device_id', 32558)->first();
echo "Current state:\n";
echo "  Global: {$deviceState->last_receipt_global_no}\n";
echo "  Counter: {$deviceState->last_receipt_counter}\n";
echo "  Fiscal Day: {$deviceState->last_fiscal_day_no}\n";
echo "  Needs Reconciliation: " . ($deviceState->requires_reconciliation ? 'YES' : 'NO') . "\n";
```

**Expected:**
```
Current state:
  Global: 61
  Counter: 1
  Fiscal Day: 11
  Needs Reconciliation: NO
```

---

### **Step 5: Submit Test Receipt**

```php
$service = app(\App\Services\ZimraDeviceService::class);

$testReceipt = [
    'invoiceNo' => 'TEST-' . time(),
    'receiptType' => 'FiscalReceipt',
    'receiptCurrency' => 'USD',
    'receiptDate' => now()->toIso8601String(),
    'receiptLines' => [
        [
            'receiptLineNo' => 1,
            'receiptLineType' => 'Sale',
            'receiptLineHSCode' => '123456',
            'receiptLineName' => 'Test Product',
            'receiptLinePrice' => 50.00,
            'receiptLineQuantity' => 1,
            'receiptLineTotal' => 50.00,
            'taxPercent' => 0.0,
            'taxID' => 513,
        ]
    ],
    'receiptTaxes' => [
        [
            'taxCode' => 'A',
            'taxID' => 513,
            'taxPercent' => 0.0,
            'taxAmount' => 0.00,
            'salesAmountWithTax' => 50.00,
        ]
    ],
    'receiptPayments' => [
        [
            'moneyTypeCode' => 'Cash',
            'paymentAmount' => 50.00,
        ]
    ],
    'receiptTotal' => 50.00,
];

try {
    $result = $service->submitReceipt($testReceipt);
    echo "✓ Receipt submitted successfully\n";
    echo "  DB Receipt ID: {$result['db_receipt_id']}\n";
    echo "  Receipt Counter: {$result['receipt_counter']}\n";
    echo "  Global No: {$result['global_no']}\n";
} catch (\Exception $e) {
    echo "✗ Error: {$e->getMessage()}\n";
}
```

---

### **Step 6: Verify Counter Increment**

```php
$deviceState->refresh();
echo "Updated state:\n";
echo "  Global: {$deviceState->last_receipt_global_no}\n";
echo "  Counter: {$deviceState->last_receipt_counter}\n";
```

**Expected:**
```
Updated state:
  Global: 62  (incremented from 61)
  Counter: 2  (incremented from 1)
```

---

### **Step 7: Test Concurrent Requests (Race Condition)**

Create test script `test_concurrent_receipts.php`:

```php
<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\DeviceState;
use Illuminate\Support\Facades\DB;

echo "=== Testing Concurrent Receipt Submissions ===\n\n";

$deviceState = DeviceState::where('device_id', 32558)->first();
echo "Initial state: Global #{$deviceState->last_receipt_global_no}, Counter {$deviceState->last_receipt_counter}\n\n";

// Simulate 5 concurrent requests
$processes = [];
for ($i = 1; $i <= 5; $i++) {
    $cmd = "php artisan tinker --execute=\"app(\App\Services\ZimraDeviceService::class)->submitReceipt(['invoiceNo' => 'CONCURRENT-{$i}', ...])\"";
    $processes[] = proc_open($cmd, [], $pipes);
    echo "Started request {$i}\n";
}

// Wait for all to complete
foreach ($processes as $proc) {
    proc_close($proc);
}

echo "\nAll requests completed\n\n";

// Check final state
$deviceState->refresh();
echo "Final state: Global #{$deviceState->last_receipt_global_no}, Counter {$deviceState->last_receipt_counter}\n";

// Check for duplicates
$duplicates = DB::select("
    SELECT receipt_counter, COUNT(*) as count
    FROM receipts
    WHERE device_id = 32558 AND fiscal_day_no = 11
    GROUP BY receipt_counter
    HAVING count > 1
");

if (empty($duplicates)) {
    echo "✓ No duplicate counters\n";
} else {
    echo "✗ Found duplicate counters:\n";
    print_r($duplicates);
}
```

---

### **Step 8: Test Reconciliation Scenario**

Simulate FDMS/DB divergence:

```php
// Temporarily break DB constraint to force failure
DB::statement("ALTER TABLE receipts DROP INDEX receipts_receipt_global_no_unique");

// Try to submit receipt with duplicate global no
$deviceState = DeviceState::where('device_id', 32558)->first();
$deviceState->last_receipt_global_no = 60; // Force duplicate
$deviceState->save();

try {
    $result = $service->submitReceipt($testReceipt);
} catch (\Exception $e) {
    echo "Expected error: {$e->getMessage()}\n";
}

// Check reconciliation flag
$deviceState->refresh();
if ($deviceState->requires_reconciliation) {
    echo "✓ Device marked for reconciliation\n";
    echo "Error: {$deviceState->reconciliation_error}\n";
}

// Restore constraint
DB::statement("ALTER TABLE receipts ADD UNIQUE (receipt_global_no)");
```

---

### **Step 9: Test Reconciliation Command**

```bash
# Check reconciliation status
php artisan zimra:reconcile-device-state 32558 --check-only

# Clear reconciliation flag (after manual fix)
php artisan zimra:reconcile-device-state 32558 --clear
```

---

### **Step 10: Production Deployment**

1. **Backup database:**
   ```bash
   php artisan zimra:backup
   ```

2. **Run migration:**
   ```bash
   php artisan migrate --force
   ```

3. **Verify device_state seeded correctly:**
   ```bash
   php artisan tinker --execute="DeviceState::all()"
   ```

4. **Deploy refactored code:**
   - Replace `submitReceipt()` method
   - Add `DeviceState` import
   - Test with single receipt

5. **Monitor logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep "device_state"
   ```

---

## 🧪 Testing Checklist

- [ ] Migration creates `device_state` table
- [ ] Migration seeds correct counter values
- [ ] `DeviceState` model methods work correctly
- [ ] Single receipt submission increments counters
- [ ] Concurrent requests don't create duplicates
- [ ] FDMS rejection rolls back transaction
- [ ] DB failure marks device for reconciliation
- [ ] Reconciliation flag blocks future submissions
- [ ] Reconciliation command works correctly
- [ ] CloseDay uses correct counters
- [ ] No CountersMismatch errors

---

## 🔍 Monitoring

### **Check Device State**

```bash
php artisan tinker --execute="DeviceState::where('device_id', 32558)->first()"
```

### **Compare device_state vs receipts**

```bash
php artisan zimra:reconcile-device-state 32558 --check-only
```

### **Watch for reconciliation errors**

```bash
tail -f storage/logs/laravel.log | grep "FDMS/DB DIVERGENCE"
```

---

## ⚠️ Rollback Plan

If issues occur:

1. **Restore backup:**
   ```bash
   cp app/Services/ZimraDeviceService_BACKUP.php app/Services/ZimraDeviceService.php
   ```

2. **Rollback migration:**
   ```bash
   php artisan migrate:rollback --step=1
   ```

3. **Restore database from backup:**
   ```bash
   # Use latest backup from storage/app/backups/
   ```

---

## 📞 Support

If you encounter issues:

1. Check logs: `storage/logs/laravel.log`
2. Check device state: `php artisan zimra:reconcile-device-state 32558 --check-only`
3. Review documentation: `ATOMIC_FISCAL_STATE_ARCHITECTURE.md`

---

## ✅ Success Criteria

After implementation:

- ✅ No CountersMismatch errors during CloseDay
- ✅ No duplicate receipt counters
- ✅ No FDMS/DB state divergence
- ✅ Concurrent requests handled correctly
- ✅ Reconciliation scenarios detected and flagged
- ✅ All receipts have sequential counters per fiscal day

---

**Status:** Ready for Implementation  
**Priority:** CRITICAL  
**Estimated Time:** 2-4 hours (including testing)
