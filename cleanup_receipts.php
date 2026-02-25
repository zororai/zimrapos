<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Receipt Cleanup Strategy ===\n\n";

echo "STEP 1: Identify which receipts to keep\n";
echo "Strategy: Keep the VALID receipt for each global number\n\n";

// For each duplicate global number, keep only the valid one
$duplicates = [1, 2, 3];

foreach ($duplicates as $globalNo) {
    echo "Global No {$globalNo}:\n";
    
    $receipts = DB::table('receipts')
        ->where('receipt_global_no', $globalNo)
        ->orderBy('is_valid', 'desc')
        ->orderBy('id', 'asc')
        ->get(['id', 'fiscal_day_no', 'invoice_no', 'is_valid', 'receipt_hash']);
    
    echo "  Found {$receipts->count()} receipts\n";
    
    $keep = $receipts->first();
    echo "  KEEP: ID {$keep->id} (Fiscal Day {$keep->fiscal_day_no}, Valid: {$keep->is_valid})\n";
    
    foreach ($receipts->skip(1) as $delete) {
        echo "  DELETE: ID {$delete->id} (Fiscal Day {$delete->fiscal_day_no}, Valid: {$delete->is_valid})\n";
    }
    echo "\n";
}

echo "\nSTEP 2: Handle missing global number #6\n";
echo "This is a gap in the sequence. Options:\n";
echo "  a) If receipt #6 was never created, this is a system error\n";
echo "  b) If receipt #6 was deleted, you need to recreate it or contact ZIMRA\n";
echo "  c) Check if any receipt should have been #6\n\n";

$aroundSix = DB::table('receipts')
    ->whereIn('receipt_global_no', [4, 5, 7, 8])
    ->orderBy('receipt_global_no')
    ->get(['id', 'receipt_global_no', 'fiscal_day_no', 'invoice_no', 'created_at']);

echo "Receipts around missing #6:\n";
foreach ($aroundSix as $r) {
    echo "  Global #{$r->receipt_global_no}: ID {$r->id}, Fiscal Day {$r->fiscal_day_no}, {$r->created_at}\n";
}

echo "\n\n=== RECOMMENDED ACTIONS ===\n";
echo "1. Delete duplicate receipts (keep valid ones)\n";
echo "2. Investigate missing global #6 - this is critical\n";
echo "3. After cleanup, verify global numbers are sequential: 1,2,3,4,5,7,8...61\n";
echo "4. Contact ZIMRA support if global #6 cannot be recovered\n\n";

echo "⚠️  WARNING: Do not execute cleanup without understanding the impact!\n";
echo "⚠️  You may need to contact ZIMRA to resolve the missing receipt #6 issue.\n";
