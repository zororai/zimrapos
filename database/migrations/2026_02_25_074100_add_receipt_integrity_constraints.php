<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // STEP 1: Remove duplicate receipt counters (keep earliest by id)
        $this->removeDuplicateCounters();
        
        // STEP 2: Recalculate receipt counters per fiscal day
        $this->recalculateCounters();
        
        // STEP 3: Add constraints
        Schema::table('receipts', function (Blueprint $table) {
            // Prevent duplicate global numbers
            if (!$this->hasIndex('receipts', 'unique_receipt_global_no')) {
                $table->unique('receipt_global_no', 'unique_receipt_global_no');
            }
            
            // Prevent duplicate receipt counters within same fiscal day
            if (!$this->hasIndex('receipts', 'unique_receipt_counter_per_day')) {
                $table->unique(['device_id', 'fiscal_day_no', 'receipt_counter'], 'unique_receipt_counter_per_day');
            }
            
            // Add soft deletes instead of hard deletes
            if (!Schema::hasColumn('receipts', 'deleted_at')) {
                $table->softDeletes();
            }
        });
        
        Schema::table('fiscal_days', function (Blueprint $table) {
            // Prevent duplicate fiscal day numbers per device
            if (!$this->hasIndex('fiscal_days', 'unique_fiscal_day_per_device')) {
                $table->unique(['device_id', 'fiscal_day_no'], 'unique_fiscal_day_per_device');
            }
            
            // Add soft deletes
            if (!Schema::hasColumn('fiscal_days', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }
    
    private function removeDuplicateCounters(): void
    {
        // Find duplicate receipt counters
        $duplicates = DB::select("
            SELECT device_id, fiscal_day_no, receipt_counter
            FROM receipts
            GROUP BY device_id, fiscal_day_no, receipt_counter
            HAVING COUNT(*) > 1
        ");
        
        foreach ($duplicates as $dup) {
            // Get all receipts with this duplicate counter
            $receipts = DB::table('receipts')
                ->where('device_id', $dup->device_id)
                ->where('fiscal_day_no', $dup->fiscal_day_no)
                ->where('receipt_counter', $dup->receipt_counter)
                ->orderBy('id')
                ->get(['id']);
            
            // Keep first (earliest id), delete rest
            $keepId = $receipts->first()->id;
            $deleteIds = $receipts->skip(1)->pluck('id')->toArray();
            
            if (!empty($deleteIds)) {
                DB::table('receipts')->whereIn('id', $deleteIds)->delete();
            }
        }
    }
    
    private function recalculateCounters(): void
    {
        // Get all fiscal days with receipts
        $fiscalDays = DB::table('receipts')
            ->select('device_id', 'fiscal_day_no')
            ->groupBy('device_id', 'fiscal_day_no')
            ->get();
        
        foreach ($fiscalDays as $fd) {
            // Get receipts ordered by creation time
            $receipts = DB::table('receipts')
                ->where('device_id', $fd->device_id)
                ->where('fiscal_day_no', $fd->fiscal_day_no)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id']);
            
            // Reassign counters starting from 1
            $counter = 1;
            foreach ($receipts as $receipt) {
                DB::table('receipts')
                    ->where('id', $receipt->id)
                    ->update(['receipt_counter' => $counter]);
                $counter++;
            }
        }
    }
    
    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return !empty($indexes);
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique('unique_receipt_global_no');
            $table->dropUnique('unique_receipt_counter_per_day');
            $table->dropSoftDeletes();
        });
        
        Schema::table('fiscal_days', function (Blueprint $table) {
            $table->dropUnique('unique_fiscal_day_per_device');
            $table->dropSoftDeletes();
        });
    }
};
