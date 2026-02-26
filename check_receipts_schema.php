<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== RECEIPTS TABLE SCHEMA CHECK ===\n\n";

// Get table structure
$createTable = DB::select("SHOW CREATE TABLE receipts");
echo "CREATE TABLE Statement:\n";
echo $createTable[0]->{'Create Table'} . "\n\n";

// Get indexes
echo "=== INDEXES ===\n";
$indexes = DB::select("SHOW INDEX FROM receipts");

foreach ($indexes as $index) {
    echo "Index: {$index->Key_name}\n";
    echo "  Column: {$index->Column_name}\n";
    echo "  Unique: " . ($index->Non_unique == 0 ? 'YES' : 'NO') . "\n";
    echo "  Seq: {$index->Seq_in_index}\n";
    echo "\n";
}

// Check for specific constraints
echo "=== CONSTRAINT CHECK ===\n";

$hasGlobalUnique = false;
$hasDeviceGlobalUnique = false;
$hasDeviceDayCounterUnique = false;

foreach ($indexes as $index) {
    if (stripos($index->Key_name, 'receipt_global_no') !== false && $index->Non_unique == 0) {
        if ($index->Seq_in_index == 1 && stripos($index->Key_name, 'device') === false) {
            $hasGlobalUnique = true;
            echo "❌ FOUND: Global unique on receipt_global_no (Key: {$index->Key_name})\n";
        }
    }
    
    if ($index->Key_name === 'receipts_device_global_unique') {
        $hasDeviceGlobalUnique = true;
    }
    
    if ($index->Key_name === 'receipts_device_day_counter_unique') {
        $hasDeviceDayCounterUnique = true;
    }
}

echo "\n";
echo "Global unique on receipt_global_no: " . ($hasGlobalUnique ? "❌ EXISTS (WRONG)" : "✓ NOT FOUND (CORRECT)") . "\n";
echo "Composite unique (device_id, receipt_global_no): " . ($hasDeviceGlobalUnique ? "✓ EXISTS (CORRECT)" : "❌ MISSING (WRONG)") . "\n";
echo "Composite unique (device_id, fiscal_day_no, receipt_counter): " . ($hasDeviceDayCounterUnique ? "✓ EXISTS (CORRECT)" : "❌ MISSING (WRONG)") . "\n";

// Check device_id column type
echo "\n=== DEVICE_ID COLUMN TYPE CHECK ===\n";
$columns = DB::select("SHOW COLUMNS FROM receipts WHERE Field = 'device_id'");

if (!empty($columns)) {
    $deviceIdColumn = $columns[0];
    echo "Column: device_id\n";
    echo "Type: {$deviceIdColumn->Type}\n";
    echo "Null: {$deviceIdColumn->Null}\n";
    
    if (stripos($deviceIdColumn->Type, 'bigint') !== false) {
        echo "✓ Type is BIGINT (CORRECT)\n";
    } elseif (stripos($deviceIdColumn->Type, 'varchar') !== false) {
        echo "❌ Type is VARCHAR (WRONG - should be BIGINT)\n";
    } else {
        echo "⚠️  Type is {$deviceIdColumn->Type} (verify if correct)\n";
    }
}

// Check other tables for device_id type consistency
echo "\n=== DEVICE_ID TYPE CONSISTENCY CHECK ===\n";

$tables = ['receipts', 'fiscal_days', 'device_state', 'companies', 'company_devices'];

foreach ($tables as $table) {
    try {
        $columns = DB::select("SHOW COLUMNS FROM {$table} WHERE Field = 'device_id'");
        if (!empty($columns)) {
            $col = $columns[0];
            echo "{$table}.device_id: {$col->Type}";
            
            if (stripos($col->Type, 'bigint') !== false) {
                echo " ✓\n";
            } elseif (stripos($col->Type, 'varchar') !== false) {
                echo " ❌ (should be BIGINT)\n";
            } else {
                echo " ⚠️\n";
            }
        }
    } catch (\Exception $e) {
        echo "{$table}: Table not found\n";
    }
}

echo "\n=== END SCHEMA CHECK ===\n";
