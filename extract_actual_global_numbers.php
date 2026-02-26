<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$debugDir = storage_path('app/zimra/debug');
$canonicalFiles = glob($debugDir . '/*_canonical_string.txt');

echo "=== Extracting Actual Global Numbers from Canonical Strings ===\n\n";

$receipts = [];

foreach ($canonicalFiles as $file) {
    $canonical = file_get_contents($file);
    
    // Parse canonical string format: deviceID||receiptType||currency||globalNo||date||total||taxes
    // Example: 32857FISCALINVOICEUSD72026-02-26T05:57:356000
    
    // Extract global number - it comes after currency (USD) and before the date
    if (preg_match('/32857FISCALINVOICEUSD(\d+)2026/', $canonical, $matches)) {
        $globalNo = (int)$matches[1];
        
        // Get invoice number from filename
        $filename = basename($file);
        if (preg_match('/(INV-\d+)/', $filename, $invMatches)) {
            $invoiceNo = $invMatches[1];
        } else {
            $invoiceNo = 'UNKNOWN';
        }
        
        // Get timestamp from filename
        if (preg_match('/(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})/', $filename, $timeMatches)) {
            $timestamp = $timeMatches[1];
        } else {
            $timestamp = 'UNKNOWN';
        }
        
        $receipts[] = [
            'global_no' => $globalNo,
            'invoice_no' => $invoiceNo,
            'timestamp' => $timestamp,
            'canonical' => $canonical,
            'file' => basename($file),
        ];
    }
}

// Sort by timestamp to see the order they were submitted
usort($receipts, function($a, $b) {
    return strcmp($a['timestamp'], $b['timestamp']);
});

echo "Found " . count($receipts) . " receipts (in submission order):\n\n";

foreach ($receipts as $r) {
    echo "Global #{$r['global_no']}: {$r['invoice_no']} at {$r['timestamp']}\n";
}

// Now check which ones are missing from DB
echo "\n=== Missing from Database ===\n\n";

$dbReceipts = DB::table('receipts')
    ->where('device_id', 32857)
    ->pluck('receipt_global_no')
    ->toArray();

$uniqueGlobalNos = array_unique(array_column($receipts, 'global_no'));
sort($uniqueGlobalNos);

$missingGlobalNos = array_diff($uniqueGlobalNos, $dbReceipts);

if (empty($missingGlobalNos)) {
    echo "✅ All receipts are in the database.\n";
} else {
    echo "Missing Global Numbers: " . implode(', ', $missingGlobalNos) . "\n\n";
    
    // For each missing global number, find the LAST submission (the one that succeeded)
    $toRecreate = [];
    
    foreach ($missingGlobalNos as $globalNo) {
        $matchingReceipts = array_filter($receipts, function($r) use ($globalNo) {
            return $r['global_no'] == $globalNo;
        });
        
        // Get the last one (most recent submission)
        $lastReceipt = end($matchingReceipts);
        $toRecreate[] = $lastReceipt;
        
        echo "Global #{$globalNo}: {$lastReceipt['invoice_no']} (submitted at {$lastReceipt['timestamp']})\n";
    }
    
    echo "\n" . count($toRecreate) . " unique receipts need to be recreated.\n";
}
