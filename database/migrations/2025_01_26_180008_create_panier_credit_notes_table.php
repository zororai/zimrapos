<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panier_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('panier_id')->unique()->index();
            $table->string('sale_id')->nullable()->index();
            $table->string('customer_id')->nullable()->index();
            $table->decimal('total', 15, 2)->default(0);
            $table->text('reason')->nullable();
            $table->boolean('zimra_fiscalized')->default(false);
            $table->string('zimra_fiscal_code')->nullable();
            $table->json('products')->nullable();
            $table->json('panier_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panier_credit_notes');
    }
};
