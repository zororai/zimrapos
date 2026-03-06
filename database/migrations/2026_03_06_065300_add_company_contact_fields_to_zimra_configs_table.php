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
            $table->text('company_address')->nullable()->after('company_tin');
            $table->string('company_email')->nullable()->after('company_address');
            $table->string('company_phone')->nullable()->after('company_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zimra_configs', function (Blueprint $table) {
            $table->dropColumn(['company_address', 'company_email', 'company_phone']);
        });
    }
};
