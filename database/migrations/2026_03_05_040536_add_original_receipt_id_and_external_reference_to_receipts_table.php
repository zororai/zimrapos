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
            $table->unsignedBigInteger('original_receipt_id')->nullable()->after('receipt_type');
            $table->string('external_reference')->nullable()->after('invoice_no');
            $table->boolean('is_voided')->default(false)->after('is_valid');
            $table->unsignedBigInteger('voided_by_receipt_id')->nullable()->after('is_voided');
            
            $table->foreign('original_receipt_id')->references('id')->on('receipts')->onDelete('set null');
            $table->foreign('voided_by_receipt_id')->references('id')->on('receipts')->onDelete('set null');
            
            $table->index('original_receipt_id');
            $table->index('external_reference');
            $table->index('is_voided');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['original_receipt_id']);
            $table->dropForeign(['voided_by_receipt_id']);
            $table->dropColumn(['original_receipt_id', 'external_reference', 'is_voided', 'voided_by_receipt_id']);
        });
    }
};
