<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('zimra_configs', function (Blueprint $table) {
            $table->string('fiscal_day_status')->nullable()->after('certificate_valid_till');
            $table->integer('last_receipt_global_no')->nullable()->after('fiscal_day_status');
            $table->integer('last_fiscal_day_no')->nullable()->after('last_receipt_global_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zimra_configs', function (Blueprint $table) {
            $table->dropColumn(['fiscal_day_status', 'last_receipt_global_no', 'last_fiscal_day_no']);
        });
    }
};
