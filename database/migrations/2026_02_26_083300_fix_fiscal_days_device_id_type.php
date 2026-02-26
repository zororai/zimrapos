<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Fix fiscal_days.device_id type from INT to BIGINT UNSIGNED
     * for consistency with other tables.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE fiscal_days MODIFY COLUMN device_id BIGINT UNSIGNED NOT NULL');
        echo "✓ Changed fiscal_days.device_id to BIGINT UNSIGNED\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE fiscal_days MODIFY COLUMN device_id INT NOT NULL');
    }
};
