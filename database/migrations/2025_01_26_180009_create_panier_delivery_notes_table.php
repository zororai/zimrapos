<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panier_delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('panier_id')->unique()->index();
            $table->string('customer_id')->nullable()->index();
            $table->text('delivery_address')->nullable();
            $table->json('recipients')->nullable();
            $table->json('products')->nullable();
            $table->json('panier_data')->nullable();
            $table->timestamp('delivery_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panier_delivery_notes');
    }
};
