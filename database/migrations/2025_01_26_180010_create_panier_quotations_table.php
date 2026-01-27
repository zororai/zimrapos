<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panier_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('panier_id')->unique()->index();
            $table->string('customer_id')->nullable()->index();
            $table->string('currency_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('recipients')->nullable();
            $table->json('products')->nullable();
            $table->json('panier_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panier_quotations');
    }
};
