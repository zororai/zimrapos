<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zimra_configs', function (Blueprint $table) {
            $table->integer('reporting_frequency')->default(300)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('zimra_configs', function (Blueprint $table) {
            $table->dropColumn('reporting_frequency');
        });
    }
};
