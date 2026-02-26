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
     * Creates companies table for multi-company ZIMRA FDMS device isolation.
     * Each company has exactly one device_id for strict fiscal data separation.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tin')->unique();
            $table->unsignedBigInteger('device_id')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('device_id');
            $table->index('is_active');
        });
        
        // Seed existing companies from zimra_configs
        $this->seedExistingCompanies();
    }

    /**
     * Seed companies from existing zimra_configs
     */
    private function seedExistingCompanies(): void
    {
        $configs = DB::table('zimra_configs')
            ->whereNotNull('device_id')
            ->get();
        
        foreach ($configs as $config) {
            DB::table('companies')->insert([
                'name' => $config->company_name,
                'tin' => $config->company_tin,
                'device_id' => $config->device_id,
                'is_active' => $config->is_active ?? true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        echo "✓ Seeded " . count($configs) . " companies from zimra_configs\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
