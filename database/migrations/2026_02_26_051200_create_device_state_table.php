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
     * CRITICAL: This table is the single source of truth for fiscal counters.
     * It prevents race conditions and ensures FDMS/DB counter consistency.
     */
    public function up(): void
    {
        Schema::create('device_state', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->unique();
            
            // Last successfully persisted receipt counters
            // These are incremented ONLY after BOTH FDMS acceptance AND DB persistence
            $table->unsignedBigInteger('last_receipt_global_no')->default(0);
            $table->unsignedInteger('last_receipt_counter')->default(0);
            $table->unsignedInteger('last_fiscal_day_no')->default(0);
            
            // Reconciliation tracking
            // If FDMS accepts but DB fails, this flag is set to trigger manual intervention
            $table->boolean('requires_reconciliation')->default(false);
            $table->text('reconciliation_error')->nullable();
            $table->timestamp('reconciliation_error_at')->nullable();
            
            // Locking mechanism for concurrent requests
            $table->timestamp('locked_at')->nullable();
            $table->string('locked_by')->nullable(); // Process ID or request ID
            
            $table->timestamps();
            
            // Indexes
            $table->index('device_id');
            $table->index('requires_reconciliation');
        });
        
        // Seed device_state with current max values from receipts table
        $this->seedDeviceState();
    }
    
    /**
     * Seed device_state table with current counter values from receipts
     * 
     * CRITICAL: This initializes the device_state with the current database state.
     * After this migration, ALL counter calculations MUST use device_state, NOT receipts.
     */
    private function seedDeviceState(): void
    {
        // Get all unique device IDs
        $deviceIds = DB::table('receipts')
            ->select('device_id')
            ->distinct()
            ->pluck('device_id');
        
        foreach ($deviceIds as $deviceId) {
            // Get max global number for this device
            $maxGlobalNo = DB::table('receipts')
                ->where('device_id', $deviceId)
                ->max('receipt_global_no') ?? 0;
            
            // Get current fiscal day and max counter
            $currentFiscalDay = DB::table('fiscal_days')
                ->where('device_id', $deviceId)
                ->where('status', 'open')
                ->orderBy('fiscal_day_no', 'desc')
                ->first();
            
            $lastFiscalDayNo = $currentFiscalDay ? $currentFiscalDay->fiscal_day_no : 0;
            
            // Get max receipt counter for current fiscal day
            $maxReceiptCounter = 0;
            if ($lastFiscalDayNo > 0) {
                $maxReceiptCounter = DB::table('receipts')
                    ->where('device_id', $deviceId)
                    ->where('fiscal_day_no', $lastFiscalDayNo)
                    ->max('receipt_counter') ?? 0;
            }
            
            // Insert device state
            DB::table('device_state')->insert([
                'device_id' => $deviceId,
                'last_receipt_global_no' => $maxGlobalNo,
                'last_receipt_counter' => $maxReceiptCounter,
                'last_fiscal_day_no' => $lastFiscalDayNo,
                'requires_reconciliation' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            echo "✓ Seeded device_state for device {$deviceId}: Global #{$maxGlobalNo}, Day {$lastFiscalDayNo}, Counter {$maxReceiptCounter}\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_state');
    }
};
