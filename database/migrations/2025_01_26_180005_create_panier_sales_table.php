<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panier_sales', function (Blueprint $table) {
            $table->id();
            $table->string('panier_id')->unique()->index();
            $table->string('customer_id')->nullable()->index();
            $table->string('currency_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->nullable();
            $table->boolean('is_voided')->default(false);
            $table->string('void_reason')->nullable();
            $table->boolean('zimra_fiscalized')->default(false);
            $table->string('zimra_fiscal_code')->nullable();
            $table->json('products')->nullable();
            $table->json('panier_data')->nullable();
            $table->timestamp('sale_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panier_sales');
    }
};
