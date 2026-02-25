<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Executing Receipt Cleanup ===\n\n";

// Define duplicates to clean
$duplicates = [
    1 => [58, 66, 70, 4, 5],  // Keep 58 (first valid), delete others
    2 => [1, 68, 6],           // Keep 1 (first valid), delete others
    3 => [2, 7],               // Keep 2 (first valid), delete others
];

$totalDeleted = 0;

foreach ($duplicates as $globalNo => $ids) {
    echo "Processing Global No {$globalNo}:\n";
    
    $keep = array_shift($ids); // First ID is the one to keep
    echo "  ✓ Keeping ID {$keep}\n";
    
    foreach ($ids as $deleteId) {
        $receipt = DB::table('receipts')->where('id', $deleteId)->first();
        if ($receipt) {
            echo "  ✗ Deleting ID {$deleteId} (Fiscal Day {$receipt->fiscal_day_no}, Invoice {$receipt->invoice_no})\n";
            DB::table('receipts')->where('id', $deleteId)->delete();
            $totalDeleted++;
        }
    }
    echo "\n";
}

echo "=== Cleanup Summary ===\n";
echo "Total receipts deleted: {$totalDeleted}\n\n";

// Verify cleanup
echo "=== Verification ===\n";
$remainingDuplicates = DB::select("
    SELECT receipt_global_no, COUNT(*) as count
    FROM receipts
    GROUP BY receipt_global_no
    HAVING COUNT(*) > 1
");

if (count($remainingDuplicates) > 0) {
    echo "⚠️  WARNING: Still have duplicates:\n";
    foreach ($remainingDuplicates as $dup) {
        echo "  Global No {$dup->receipt_global_no}: {$dup->count} receipts\n";
    }
} else {
    echo "✓ No duplicate global numbers remaining\n";
}

// Check total receipts
$totalReceipts = DB::table('receipts')->count();
$maxGlobal = DB::table('receipts')->max('receipt_global_no');
echo "\nTotal receipts: {$totalReceipts}\n";
echo "Max global number: {$maxGlobal}\n";

// Check for missing numbers
$existingGlobals = DB::table('receipts')->pluck('receipt_global_no')->unique()->sort()->values()->toArray();
$missing = [];
for ($i = 1; $i <= $maxGlobal; $i++) {
    if (!in_array($i, $existingGlobals)) {
        $missing[] = $i;
    }
}

if (count($missing) > 0) {
    echo "\n⚠️  Missing global numbers: " . implode(', ', $missing) . "\n";
    echo "This is still causing the CountersMismatch error.\n";
} else {
    echo "\n✓ No missing global numbers\n";
}

echo "\n=== Next Steps ===\n";
if (count($missing) > 0) {
    echo "The missing receipt #6 is still a problem.\n";
    echo "Options:\n";
    echo "1. Check if receipt #6 exists in ZIMRA's system but not in your database\n";
    echo "2. Contact ZIMRA support to resolve the missing receipt\n";
    echo "3. Check application logs for when receipt #6 should have been created\n";
} else {
    echo "Duplicates cleaned up successfully.\n";
    echo "You can now retry closing fiscal day 11.\n";
}
