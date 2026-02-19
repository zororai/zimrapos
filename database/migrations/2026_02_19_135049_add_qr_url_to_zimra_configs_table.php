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
            $table->string('qr_url')->nullable()->after('reporting_frequency');
            $table->json('taxes')->nullable()->after('qr_url');
            $table->string('device_operating_mode')->nullable()->after('taxes');
            $table->timestamp('certificate_valid_till')->nullable()->after('device_operating_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zimra_configs', function (Blueprint $table) {
            $table->dropColumn(['qr_url', 'taxes', 'device_operating_mode', 'certificate_valid_till']);
        });
    }
};
