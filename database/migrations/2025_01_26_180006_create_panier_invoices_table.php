<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panier_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('panier_id')->unique()->index();
            $table->string('invoice_number')->nullable()->index();
            $table->string('customer_id')->nullable()->index();
            $table->string('currency_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->string('status')->nullable();
            $table->boolean('zimra_fiscalized')->default(false);
            $table->string('zimra_fiscal_code')->nullable();
            $table->json('products')->nullable();
            $table->json('panier_data')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('invoice_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panier_invoices');
    }
};
