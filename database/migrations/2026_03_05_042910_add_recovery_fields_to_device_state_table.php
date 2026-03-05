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
        Schema::table('device_state', function (Blueprint $table) {
            $table->string('last_receipt_hash')->nullable()->after('last_fiscal_day_no');
            $table->unsignedBigInteger('last_receipt_id')->nullable()->after('last_receipt_hash');
            $table->timestamp('last_sync_at')->nullable()->after('last_receipt_id');
        });

        // Add status and FDMS audit fields to receipts
        Schema::table('receipts', function (Blueprint $table) {
            $table->enum('status', ['pending', 'submitted', 'finalized', 'failed'])->default('pending')->after('id');
            $table->string('fdms_operation_id')->nullable()->after('fdms_receipt_id');
            $table->timestamp('fdms_server_date')->nullable()->after('fdms_operation_id');
            $table->string('fdms_certificate_thumbprint')->nullable()->after('fdms_server_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_state', function (Blueprint $table) {
            $table->dropColumn(['last_receipt_hash', 'last_receipt_id', 'last_sync_at']);
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn(['status', 'fdms_operation_id', 'fdms_server_date', 'fdms_certificate_thumbprint']);
        });
    }
};
