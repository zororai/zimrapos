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
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('validation_code')->nullable()->after('zimra_response');
            $table->json('validation_errors')->nullable()->after('validation_code');
            $table->boolean('is_valid')->default(true)->after('validation_errors');
            $table->string('fdms_receipt_id')->nullable()->after('is_valid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn(['validation_code', 'validation_errors', 'is_valid', 'fdms_receipt_id']);
        });
    }
};
