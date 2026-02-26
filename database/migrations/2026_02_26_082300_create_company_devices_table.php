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
     * Creates company_devices table for proper company-device mapping.
     * Supports multiple devices per company with active device selection.
     */
    public function up(): void
    {
        Schema::create('company_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('device_id')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['company_id', 'is_active']);
            $table->index('device_id');
        });
        
        // Migrate existing company-device mappings
        $this->migrateExistingMappings();
    }

    /**
     * Migrate existing company → device_id mappings to company_devices table
     */
    private function migrateExistingMappings(): void
    {
        $companies = DB::table('companies')->whereNotNull('device_id')->get();
        
        foreach ($companies as $company) {
            DB::table('company_devices')->insert([
                'company_id' => $company->id,
                'device_id' => $company->device_id,
                'is_active' => $company->is_active ?? true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        echo "✓ Migrated " . count($companies) . " company-device mappings\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_devices');
    }
};
