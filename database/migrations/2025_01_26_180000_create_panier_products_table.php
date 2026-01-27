<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panier_products', function (Blueprint $table) {
            $table->id();
            $table->string('panier_id')->unique()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('buying_price', 15, 2)->nullable();
            $table->decimal('selling_price', 15, 2);
            $table->integer('quantity')->default(0);
            $table->string('hs_code')->nullable();
            $table->string('sku')->nullable();
            $table->boolean('is_inventory_item')->default(true);
            $table->string('applicable_tax_id')->nullable();
            $table->json('suppliers')->nullable();
            $table->json('panier_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panier_products');
    }
};
