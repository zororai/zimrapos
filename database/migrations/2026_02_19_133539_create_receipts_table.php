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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('device_id');
            $table->string('invoice_no');
            $table->string('receipt_type');
            $table->string('receipt_currency', 3);
            $table->integer('receipt_counter');
            $table->integer('receipt_global_no');
            $table->integer('fiscal_day_no');
            $table->decimal('receipt_total', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->string('tax_code', 1);
            $table->decimal('tax_percent', 5, 2);
            $table->string('payment_method');
            $table->json('receipt_lines');
            $table->json('receipt_taxes');
            $table->json('receipt_payments');
            $table->string('receipt_hash')->nullable();
            $table->string('receipt_signature')->nullable();
            $table->string('receipt_qr_code')->nullable();
            $table->string('verification_code')->nullable();
            $table->json('zimra_response')->nullable();
            $table->timestamp('receipt_date');
            $table->timestamps();
            
            $table->index(['device_id', 'invoice_no']);
            $table->index(['device_id', 'fiscal_day_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
