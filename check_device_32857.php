<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ZimraConfig;
use App\Models\DeviceState;
use App\Models\Receipt;
use App\Models\FiscalDay;
use Illuminate\Support\Facades\DB;

echo "=== DEVICE 32857 STATE CHECK ===\n\n";

// 1. Device Info
echo "--- DEVICE INFO ---\n";
$device = ZimraConfig::where('device_id', 32857)->first();

if (!$device) {
    echo "❌ Device 32857 not found in zimra_configs table\n";
    exit(1);
}

echo "Device ID: {$device->device_id}\n";
echo "Serial Number: {$device->serial_number}\n";
echo "Company: {$device->company_name}\n";
echo "TIN: {$device->company_tin}\n";
echo "Active: " . ($device->is_active ? 'YES' : 'NO') . "\n";
echo "Certificate: " . ($device->certificate ? '✓ Stored' : '✗ Missing') . "\n";
echo "QR URL: " . ($device->qr_url ?? 'Not set') . "\n";
echo "Operating Mode: " . ($device->device_operating_mode ?? 'Not set') . "\n";
echo "\n";

// 2. Device State (if exists)
echo "--- DEVICE STATE ---\n";
$deviceState = DeviceState::where('device_id', 32857)->first();

if ($deviceState) {
    echo "✓ device_state record exists\n";
    echo "Last Receipt Global No: {$deviceState->last_receipt_global_no}\n";
    echo "Last Receipt Counter: {$deviceState->last_receipt_counter}\n";
    echo "Last Fiscal Day No: {$deviceState->last_fiscal_day_no}\n";
    echo "Requires Reconciliation: " . ($deviceState->requires_reconciliation ? 'YES ⚠️' : 'NO') . "\n";
    if ($deviceState->reconciliation_error) {
        echo "Reconciliation Error: {$deviceState->reconciliation_error}\n";
    }
} else {
    echo "⚠️  No device_state record found\n";
    echo "Run migration to create device_state table\n";
}
echo "\n";

// 3. Receipts
echo "--- RECEIPTS ---\n";
$receiptCount = Receipt::where('device_id', 32857)->count();
echo "Total Receipts: {$receiptCount}\n";

if ($receiptCount > 0) {
    $lastReceipt = Receipt::where('device_id', 32857)
        ->orderBy('receipt_global_no', 'desc')
        ->first();
    
    echo "Last Receipt:\n";
    echo "  Global No: {$lastReceipt->receipt_global_no}\n";
    echo "  Fiscal Day: {$lastReceipt->fiscal_day_no}\n";
    echo "  Counter: {$lastReceipt->receipt_counter}\n";
    echo "  Invoice: {$lastReceipt->invoice_no}\n";
    
    // Check for gaps
    $receipts = DB::select("
        SELECT receipt_global_no 
        FROM receipts 
        WHERE device_id = 32857 
        ORDER BY receipt_global_no ASC
    ");
    
    $gaps = [];
    $prev = 0;
    foreach ($receipts as $r) {
        if ($prev > 0 && $r->receipt_global_no !== $prev + 1) {
            $gaps[] = "Gap between {$prev} and {$r->receipt_global_no}";
        }
        $prev = $r->receipt_global_no;
    }
    
    if (empty($gaps)) {
        echo "✓ No gaps in global numbers\n";
    } else {
        echo "⚠️  Gaps found:\n";
        foreach ($gaps as $gap) {
            echo "  - {$gap}\n";
        }
    }
    
    // Summary by day
    $summary = DB::select("
        SELECT 
            fiscal_day_no,
            COUNT(*) as count,
            MIN(receipt_counter) as min_counter,
            MAX(receipt_counter) as max_counter
        FROM receipts
        WHERE device_id = 32857
        GROUP BY fiscal_day_no
        ORDER BY fiscal_day_no ASC
    ");
    
    if (!empty($summary)) {
        echo "\nReceipts by Fiscal Day:\n";
        foreach ($summary as $day) {
            echo "  Day {$day->fiscal_day_no}: {$day->count} receipts, counters {$day->min_counter}-{$day->max_counter}\n";
        }
    }
} else {
    echo "✓ No receipts yet (fresh device)\n";
}
echo "\n";

// 4. Fiscal Days
echo "--- FISCAL DAYS ---\n";
$fiscalDays = FiscalDay::where('device_id', 32857)
    ->orderBy('fiscal_day_no', 'asc')
    ->get();

if ($fiscalDays->count() > 0) {
    echo "Total Fiscal Days: {$fiscalDays->count()}\n";
    foreach ($fiscalDays as $day) {
        $status = $day->is_closed ? 'CLOSED' : 'OPEN';
        echo "  Day {$day->fiscal_day_no}: {$status}";
        if ($day->is_closed) {
            echo " (closed at {$day->closed_at})";
        }
        echo "\n";
    }
} else {
    echo "✓ No fiscal days yet (fresh device)\n";
}
echo "\n";

// 5. FDMS Status (if possible)
echo "--- FDMS STATUS ---\n";
try {
    $service = app(\App\Services\ZimraDeviceService::class);
    $status = $service->getStatus(32857);
    
    echo "FDMS Reports:\n";
    echo "  Fiscal Day Status: " . ($status['fiscalDayStatus'] ?? 'N/A') . "\n";
    echo "  Last Receipt Global No: " . ($status['lastReceiptGlobalNo'] ?? 'N/A') . "\n";
    echo "  Last Fiscal Day No: " . ($status['lastFiscalDayNo'] ?? 'N/A') . "\n";
    echo "  Closing Error Code: " . ($status['fiscalDayClosingErrorCode'] ?? 'None') . "\n";
    
    // Compare with DB
    if ($deviceState) {
        echo "\nDB vs FDMS Comparison:\n";
        
        $fdmsGlobal = $status['lastReceiptGlobalNo'] ?? 0;
        if ($deviceState->last_receipt_global_no === $fdmsGlobal) {
            echo "  ✓ Last Global No matches: {$fdmsGlobal}\n";
        } else {
            echo "  ⚠️  Last Global No MISMATCH: DB={$deviceState->last_receipt_global_no}, FDMS={$fdmsGlobal}\n";
        }
        
        $fdmsDay = $status['lastFiscalDayNo'] ?? 0;
        if ($deviceState->last_fiscal_day_no === $fdmsDay) {
            echo "  ✓ Last Fiscal Day matches: {$fdmsDay}\n";
        } else {
            echo "  ⚠️  Last Fiscal Day MISMATCH: DB={$deviceState->last_fiscal_day_no}, FDMS={$fdmsDay}\n";
        }
    }
    
} catch (Exception $e) {
    echo "⚠️  Could not get FDMS status: {$e->getMessage()}\n";
}

echo "\n=== END CHECK ===\n";
