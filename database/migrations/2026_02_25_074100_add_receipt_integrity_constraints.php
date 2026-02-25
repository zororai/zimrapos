<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            // Prevent duplicate global numbers
            $table->unique('receipt_global_no', 'unique_receipt_global_no');
            
            // Prevent duplicate receipt counters within same fiscal day
            $table->unique(['device_id', 'fiscal_day_no', 'receipt_counter'], 'unique_receipt_counter_per_day');
            
            // Add soft deletes instead of hard deletes
            $table->softDeletes();
        });
        
        Schema::table('fiscal_days', function (Blueprint $table) {
            // Prevent duplicate fiscal day numbers per device
            $table->unique(['device_id', 'fiscal_day_no'], 'unique_fiscal_day_per_device');
            
            // Add soft deletes
            $table->softDeletes();
        });
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
