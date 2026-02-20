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
            $table->string('company_name')->nullable()->after('id');
            $table->string('company_tin')->nullable()->after('company_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zimra_configs', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'company_tin']);
        });
    }
};
