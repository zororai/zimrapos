<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panier_currencies', function (Blueprint $table) {
            $table->id();
            $table->string('panier_id')->unique()->index();
            $table->string('code', 10);
            $table->string('name')->nullable();
            $table->decimal('exchange_rate', 20, 10)->default(1);
            $table->json('panier_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panier_currencies');
    }
};
