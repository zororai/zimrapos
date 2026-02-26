<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * CRITICAL FIX: Remove global unique constraint on receipt_global_no
     * and replace with per-device uniqueness for multi-company isolation.
     */
    public function up(): void
    {
        // Get existing indexes to check what needs to be dropped
        $indexes = DB::select("SHOW INDEX FROM receipts WHERE Key_name LIKE '%receipt_global_no%'");
        
        Schema::table('receipts', function (Blueprint $table) use ($indexes) {
            // Drop global unique constraint on receipt_global_no if it exists
            foreach ($indexes as $index) {
                if ($index->Key_name !== 'PRIMARY' && $index->Non_unique == 0) {
                    try {
                        $table->dropUnique($index->Key_name);
                        echo "✓ Dropped global unique constraint: {$index->Key_name}\n";
                    } catch (\Exception $e) {
                        echo "⚠ Could not drop {$index->Key_name}: {$e->getMessage()}\n";
                    }
                }
            }
        });
        
        // Add per-device unique constraints
        Schema::table('receipts', function (Blueprint $table) {
            // Check if constraint already exists before adding
            $existingConstraints = DB::select("SHOW INDEX FROM receipts WHERE Key_name = 'receipts_device_global_unique'");
            
            if (empty($existingConstraints)) {
                $table->unique(['device_id', 'receipt_global_no'], 'receipts_device_global_unique');
                echo "✓ Added unique constraint: receipts_device_global_unique (device_id, receipt_global_no)\n";
            } else {
                echo "⚠ Constraint receipts_device_global_unique already exists\n";
            }
            
            // Check for day-counter constraint
            $existingDayCounter = DB::select("SHOW INDEX FROM receipts WHERE Key_name = 'receipts_device_day_counter_unique'");
            
            if (empty($existingDayCounter)) {
                $table->unique(['device_id', 'fiscal_day_no', 'receipt_counter'], 'receipts_device_day_counter_unique');
                echo "✓ Added unique constraint: receipts_device_day_counter_unique (device_id, fiscal_day_no, receipt_counter)\n";
            } else {
                echo "⚠ Constraint receipts_device_day_counter_unique already exists\n";
            }
        });
        
        echo "\n✅ Receipts table constraints fixed for multi-company isolation\n";
        echo "   - receipt_global_no: now unique per device (not globally)\n";
        echo "   - receipt_counter: unique per device per fiscal day\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            // Drop per-device constraints
            try {
                $table->dropUnique('receipts_device_global_unique');
            } catch (\Exception $e) {
                // Constraint may not exist
            }
            
            try {
                $table->dropUnique('receipts_device_day_counter_unique');
            } catch (\Exception $e) {
                // Constraint may not exist
            }
            
            // Restore global unique (only if rolling back)
            // WARNING: This will fail if multiple devices have same global_no
            try {
                $table->unique('receipt_global_no');
            } catch (\Exception $e) {
                echo "⚠ Cannot restore global unique constraint - data conflict exists\n";
            }
        });
    }
};
