# Receipt Continuity & FDMS State Diagnostic

## 🎯 Purpose

This diagnostic verifies:
1. **Receipt continuity** in your database (no gaps, no duplicates)
2. **FDMS current state** (what FDMS thinks is the last receipt/day)
3. **DB vs FDMS alignment** (are they in sync?)

**⚠️ CRITICAL: Do NOT submit new receipts or retry CloseDay until this diagnostic is complete.**

---

## ✅ STEP 1 — Receipt Continuity Query (Database)

### **Option A: Using Tinker (Recommended)**

Open terminal in your Laravel project:

```bash
php artisan tinker
```

Run this query:

```php
DB::select("
    SELECT receipt_global_no, fiscal_day_no, receipt_counter
    FROM receipts
    ORDER BY receipt_global_no ASC
");
```

**For cleaner output:**

```php
collect(DB::select("
    SELECT receipt_global_no, fiscal_day_no, receipt_counter
    FROM receipts
    ORDER BY receipt_global_no ASC
"))->map(function($r) {
    return [
        'global' => $r->receipt_global_no,
        'day' => $r->fiscal_day_no,
        'counter' => $r->receipt_counter,
    ];
})->toArray();
```

**Copy the FULL output.**

---

### **Option B: Using MySQL CLI**

```bash
mysql -u root -p
```

```sql
USE laravel;

SELECT receipt_global_no, fiscal_day_no, receipt_counter
FROM receipts
ORDER BY receipt_global_no ASC;
```

---

### **What We're Looking For:**

1. **Global Number Continuity**
   - Are global numbers continuous: 1, 2, 3, ..., 61, 62?
   - Any gaps? (e.g., 1, 2, 4, 5 — missing 3)
   - Any duplicates? (e.g., 1, 2, 2, 3)

2. **Fiscal Day Boundaries**
   - Does `fiscal_day_no` increment correctly?
   - Does `receipt_counter` reset to 1 when day changes?
   - Example expected pattern:
     ```
     global=1,  day=11, counter=1
     global=2,  day=11, counter=2
     ...
     global=60, day=11, counter=60
     global=61, day=12, counter=1  ← Day changed, counter reset
     global=62, day=12, counter=2
     ```

3. **Receipt Counter Continuity Per Day**
   - Within each fiscal day, are counters continuous?
   - Day 11: 1, 2, 3, ..., N (no gaps)
   - Day 12: 1, 2, 3, ..., M (no gaps)

---

### **Enhanced Diagnostic Query**

For detailed analysis, run:

```php
// Check for gaps in global numbers
DB::select("
    SELECT 
        a.receipt_global_no + 1 AS missing_start,
        MIN(b.receipt_global_no) - 1 AS missing_end
    FROM receipts a
    LEFT JOIN receipts b ON a.receipt_global_no < b.receipt_global_no
    WHERE NOT EXISTS (
        SELECT 1 FROM receipts c 
        WHERE c.receipt_global_no = a.receipt_global_no + 1
    )
    GROUP BY a.receipt_global_no
    HAVING missing_start <= missing_end
");
```

**Expected:** Empty array (no gaps)

---

```php
// Check for duplicate global numbers
DB::select("
    SELECT receipt_global_no, COUNT(*) as count
    FROM receipts
    GROUP BY receipt_global_no
    HAVING count > 1
");
```

**Expected:** Empty array (no duplicates)

---

```php
// Check for duplicate counters per day
DB::select("
    SELECT fiscal_day_no, receipt_counter, COUNT(*) as count
    FROM receipts
    GROUP BY fiscal_day_no, receipt_counter
    HAVING count > 1
");
```

**Expected:** Empty array (no duplicates per day)

---

```php
// Summary by fiscal day
DB::select("
    SELECT 
        fiscal_day_no,
        COUNT(*) as receipt_count,
        MIN(receipt_counter) as min_counter,
        MAX(receipt_counter) as max_counter,
        MIN(receipt_global_no) as first_global,
        MAX(receipt_global_no) as last_global
    FROM receipts
    GROUP BY fiscal_day_no
    ORDER BY fiscal_day_no ASC
");
```

**Expected pattern:**
```
Day 11: count=60, min_counter=1, max_counter=60, first_global=1, last_global=60
Day 12: count=2,  min_counter=1, max_counter=2,  first_global=61, last_global=62
```

---

## ✅ STEP 2 — Current FDMS State (GetStatus)

### **Option A: Using Tinker**

```bash
php artisan tinker
```

```php
$service = app(\App\Services\ZimraDeviceService::class);
$status = $service->getStatus(32558);

// Extract only what matters
[
    'fiscalDayStatus' => $status['fiscalDayStatus'] ?? null,
    'lastReceiptGlobalNo' => $status['lastReceiptGlobalNo'] ?? null,
    'lastFiscalDayNo' => $status['lastFiscalDayNo'] ?? null,
    'fiscalDayClosingErrorCode' => $status['fiscalDayClosingErrorCode'] ?? null,
];
```

---

### **Option B: Using Postman/Browser**

**Endpoint:**
```
GET /zimra/status/32558
```

**Copy ONLY these fields from the response:**

```json
{
  "fiscalDayStatus": "...",
  "lastReceiptGlobalNo": ...,
  "lastFiscalDayNo": ...,
  "fiscalDayClosingErrorCode": "..."
}
```

---

### **What We're Looking For:**

1. **lastReceiptGlobalNo**
   - Does FDMS think the last receipt is #62?
   - Or is it stuck at #61?
   - Or something else?

2. **lastFiscalDayNo**
   - Does FDMS think we're on Day 12?
   - Or is it stuck at Day 11?

3. **fiscalDayStatus**
   - `Open` = Day is open, can accept receipts
   - `Closed` = Day is closed
   - `ClosingInProgress` = CloseDay in progress

4. **fiscalDayClosingErrorCode**
   - `None` = No errors
   - `CountersMismatch` = Counters don't match
   - `MissingReceipts` = Receipts missing
   - `ReceiptsWithValidationErrors` = Red/gray errors

---

## 🔍 STEP 3 — Compare DB vs FDMS

### **Create Comparison Table**

| Metric | Database | FDMS | Status |
|--------|----------|------|--------|
| Last Global No | (from DB query) | (from GetStatus) | ✅ Match / ❌ Mismatch |
| Last Fiscal Day | (from DB query) | (from GetStatus) | ✅ Match / ❌ Mismatch |
| Day 11 Receipt Count | (from DB query) | N/A | - |
| Day 12 Receipt Count | (from DB query) | N/A | - |
| Global No Continuity | ✅ / ❌ | N/A | - |
| Counter Continuity | ✅ / ❌ | N/A | - |

---

### **Automated Comparison Script**

Save as `check_continuity.php` in project root:

```php
<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== RECEIPT CONTINUITY DIAGNOSTIC ===\n\n";

// 1. Database State
echo "--- DATABASE STATE ---\n";

$receipts = DB::select("
    SELECT receipt_global_no, fiscal_day_no, receipt_counter
    FROM receipts
    ORDER BY receipt_global_no ASC
");

$lastReceipt = end($receipts);
echo "Last Receipt:\n";
echo "  Global No: {$lastReceipt->receipt_global_no}\n";
echo "  Fiscal Day: {$lastReceipt->fiscal_day_no}\n";
echo "  Counter: {$lastReceipt->receipt_counter}\n\n";

// Check continuity
$gaps = [];
$duplicates = [];
$prevGlobal = 0;

foreach ($receipts as $r) {
    if ($prevGlobal > 0 && $r->receipt_global_no !== $prevGlobal + 1) {
        $gaps[] = "Gap between {$prevGlobal} and {$r->receipt_global_no}";
    }
    if ($prevGlobal === $r->receipt_global_no) {
        $duplicates[] = "Duplicate global no: {$r->receipt_global_no}";
    }
    $prevGlobal = $r->receipt_global_no;
}

if (empty($gaps)) {
    echo "✓ No gaps in global numbers\n";
} else {
    echo "✗ Gaps found:\n";
    foreach ($gaps as $gap) echo "  - {$gap}\n";
}

if (empty($duplicates)) {
    echo "✓ No duplicate global numbers\n";
} else {
    echo "✗ Duplicates found:\n";
    foreach ($duplicates as $dup) echo "  - {$dup}\n";
}

echo "\n";

// Summary by day
$summary = DB::select("
    SELECT 
        fiscal_day_no,
        COUNT(*) as receipt_count,
        MIN(receipt_counter) as min_counter,
        MAX(receipt_counter) as max_counter,
        MIN(receipt_global_no) as first_global,
        MAX(receipt_global_no) as last_global
    FROM receipts
    GROUP BY fiscal_day_no
    ORDER BY fiscal_day_no ASC
");

echo "Summary by Fiscal Day:\n";
foreach ($summary as $day) {
    echo "  Day {$day->fiscal_day_no}: {$day->receipt_count} receipts, ";
    echo "counters {$day->min_counter}-{$day->max_counter}, ";
    echo "global {$day->first_global}-{$day->last_global}\n";
}

echo "\n";

// 2. FDMS State
echo "--- FDMS STATE ---\n";

try {
    $service = app(\App\Services\ZimraDeviceService::class);
    $status = $service->getStatus(32558);
    
    echo "FDMS Reports:\n";
    echo "  Last Global No: " . ($status['lastReceiptGlobalNo'] ?? 'N/A') . "\n";
    echo "  Last Fiscal Day: " . ($status['lastFiscalDayNo'] ?? 'N/A') . "\n";
    echo "  Fiscal Day Status: " . ($status['fiscalDayStatus'] ?? 'N/A') . "\n";
    echo "  Closing Error: " . ($status['fiscalDayClosingErrorCode'] ?? 'None') . "\n";
    
    echo "\n";
    
    // 3. Comparison
    echo "--- COMPARISON ---\n";
    
    $dbLastGlobal = $lastReceipt->receipt_global_no;
    $fdmsLastGlobal = $status['lastReceiptGlobalNo'] ?? null;
    
    if ($dbLastGlobal === $fdmsLastGlobal) {
        echo "✓ Last Global No matches: {$dbLastGlobal}\n";
    } else {
        echo "✗ Last Global No MISMATCH:\n";
        echo "  DB: {$dbLastGlobal}\n";
        echo "  FDMS: {$fdmsLastGlobal}\n";
    }
    
    $dbLastDay = $lastReceipt->fiscal_day_no;
    $fdmsLastDay = $status['lastFiscalDayNo'] ?? null;
    
    if ($dbLastDay === $fdmsLastDay) {
        echo "✓ Last Fiscal Day matches: {$dbLastDay}\n";
    } else {
        echo "✗ Last Fiscal Day MISMATCH:\n";
        echo "  DB: {$dbLastDay}\n";
        echo "  FDMS: {$fdmsLastDay}\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error getting FDMS status: {$e->getMessage()}\n";
}

echo "\n=== END DIAGNOSTIC ===\n";
```

**Run:**
```bash
php check_continuity.php
```

---

## 📋 Expected Output Examples

### **✅ HEALTHY STATE**

```
=== RECEIPT CONTINUITY DIAGNOSTIC ===

--- DATABASE STATE ---
Last Receipt:
  Global No: 62
  Fiscal Day: 12
  Counter: 2

✓ No gaps in global numbers
✓ No duplicate global numbers

Summary by Fiscal Day:
  Day 11: 60 receipts, counters 1-60, global 1-60
  Day 12: 2 receipts, counters 1-2, global 61-62

--- FDMS STATE ---
FDMS Reports:
  Last Global No: 62
  Last Fiscal Day: 12
  Fiscal Day Status: Open
  Closing Error: None

--- COMPARISON ---
✓ Last Global No matches: 62
✓ Last Fiscal Day matches: 12

=== END DIAGNOSTIC ===
```

---

### **❌ PROBLEM STATE (Example 1: Gap in Global Numbers)**

```
--- DATABASE STATE ---
Last Receipt:
  Global No: 62
  Fiscal Day: 12
  Counter: 2

✗ Gaps found:
  - Gap between 59 and 61

Summary by Fiscal Day:
  Day 11: 59 receipts, counters 1-60, global 1-59
  Day 12: 2 receipts, counters 1-2, global 61-62

--- FDMS STATE ---
FDMS Reports:
  Last Global No: 60
  Last Fiscal Day: 11
  Fiscal Day Status: Open
  Closing Error: MissingReceipts

--- COMPARISON ---
✗ Last Global No MISMATCH:
  DB: 62
  FDMS: 60
✗ Last Fiscal Day MISMATCH:
  DB: 12
  FDMS: 11
```

**Diagnosis:** Receipt #60 exists in FDMS but missing from DB. Day 12 opened prematurely.

---

### **❌ PROBLEM STATE (Example 2: Duplicate Counters)**

```
--- DATABASE STATE ---
Last Receipt:
  Global No: 62
  Fiscal Day: 12
  Counter: 2

✓ No gaps in global numbers
✗ Duplicates found:
  - Duplicate global no: 61

Summary by Fiscal Day:
  Day 11: 60 receipts, counters 1-60, global 1-60
  Day 12: 3 receipts, counters 1-2, global 61-62

--- FDMS STATE ---
FDMS Reports:
  Last Global No: 61
  Last Fiscal Day: 12
  Fiscal Day Status: Open
  Closing Error: None

--- COMPARISON ---
✗ Last Global No MISMATCH:
  DB: 62
  FDMS: 61
✓ Last Fiscal Day matches: 12
```

**Diagnosis:** Duplicate receipt #61 in DB. FDMS only has one copy.

---

## 🚨 DO NOT DO THESE THINGS

Until diagnostic is complete:

- ❌ Submit new receipts
- ❌ Retry CloseDay
- ❌ Modify receipts table
- ❌ Reset counters
- ❌ Delete receipts
- ❌ Open new fiscal day

**Why?** You'll compound the problem and make it harder to diagnose.

---

## 📊 What To Share

After running the diagnostic, share:

1. **Full output from database query** (all receipts)
2. **FDMS GetStatus response** (the 4 key fields)
3. **Comparison results** (from automated script)
4. **Any error messages** encountered

---

## 🎯 Next Steps (After Diagnostic)

Based on results:

### **If DB and FDMS match perfectly:**
- ✅ Safe to continue normal operations
- ✅ Can attempt CloseDay for Day 12

### **If DB has more receipts than FDMS:**
- ⚠️ CRITICAL: FDMS/DB divergence
- Need to identify which receipts FDMS rejected
- May need to delete extra receipts from DB

### **If FDMS has more receipts than DB:**
- ⚠️ CRITICAL: Missing receipts in DB
- Need to fetch missing receipts from FDMS
- Use GetReceipt API to retrieve

### **If there are gaps or duplicates:**
- ⚠️ Data integrity issue
- Need to repair receipts table
- May need to re-sync with FDMS

---

## 📞 Support

After running diagnostic, provide:
1. Full terminal output
2. Screenshots of FDMS portal (if accessible)
3. Any error logs from `storage/logs/laravel.log`

**Do not proceed with fixes until diagnostic is reviewed.**

---

**Created:** 2026-02-26  
**Status:** Diagnostic Tool  
**Priority:** CRITICAL - Run Before Any Operations
