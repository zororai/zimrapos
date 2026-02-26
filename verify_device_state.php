<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$deviceId = 32857;

echo "=== FDMS Status ===\n";
$zimraService = app(\App\Services\ZimraDeviceService::class);
$fdmsStatus = $zimraService->getStatus($deviceId);

echo "Fiscal Day Status: {$fdmsStatus['fiscalDayStatus']}\n";
echo "Last Fiscal Day No: {$fdmsStatus['lastFiscalDayNo']}\n";
echo "Last Receipt Global No: {$fdmsStatus['lastReceiptGlobalNo']}\n";
if (isset($fdmsStatus['fiscalDayClosingErrorCode'])) {
    echo "Closing Error: {$fdmsStatus['fiscalDayClosingErrorCode']}\n";
}

echo "\n=== device_state Table ===\n";
$deviceState = DB::table('device_state')
    ->where('device_id', $deviceId)
    ->first();

if ($deviceState) {
    echo "Last Fiscal Day No: {$deviceState->last_fiscal_day_no}\n";
    echo "Last Receipt Counter: {$deviceState->last_receipt_counter}\n";
    echo "Last Receipt Global No: {$deviceState->last_receipt_global_no}\n";
    echo "Requires Reconciliation: " . ($deviceState->requires_reconciliation ? 'YES' : 'NO') . "\n";
} else {
    echo "❌ device_state record not found!\n";
}

echo "\n=== Receipts Table ===\n";
$receipts = DB::table('receipts')
    ->where('device_id', $deviceId)
    ->orderBy('receipt_global_no', 'desc')
    ->limit(5)
    ->get(['id', 'fiscal_day_no', 'receipt_counter', 'receipt_global_no', 'invoice_no', 'is_valid']);

foreach ($receipts as $r) {
    echo "ID {$r->id}: Day {$r->fiscal_day_no}, Counter {$r->receipt_counter}, Global #{$r->receipt_global_no}, Invoice {$r->invoice_no}, Valid: " . ($r->is_valid ? 'YES' : 'NO') . "\n";
}

echo "\n=== Sync Required? ===\n";
if ($deviceState) {
    $fdmsGlobal = $fdmsStatus['lastReceiptGlobalNo'];
    $localGlobal = $deviceState->last_receipt_global_no;
    $fdmsDay = $fdmsStatus['lastFiscalDayNo'];
    $localDay = $deviceState->last_fiscal_day_no;
    
    if ($fdmsGlobal != $localGlobal || $fdmsDay != $localDay) {
        echo "❌ OUT OF SYNC\n";
        echo "  FDMS: Day {$fdmsDay}, Global #{$fdmsGlobal}\n";
        echo "  Local: Day {$localDay}, Global #{$localGlobal}\n";
        echo "\nSync device_state to FDMS? (yes/no): ";
        
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        if ($line === 'yes') {
            DB::table('device_state')
                ->where('device_id', $deviceId)
                ->update([
                    'last_fiscal_day_no' => $fdmsDay,
                    'last_receipt_global_no' => $fdmsGlobal,
                    'last_receipt_counter' => 1, // Day 4 has 1 receipt
                    'updated_at' => now(),
                ]);
            echo "\n✅ device_state synced with FDMS\n";
        } else {
            echo "\nNo changes made.\n";
        }
    } else {
        echo "✅ IN SYNC\n";
    }
}
