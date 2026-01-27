<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panier_taxes', function (Blueprint $table) {
            $table->id();
            $table->string('panier_id')->unique()->index();
            $table->string('name');
            $table->decimal('percentage', 8, 4);
            $table->string('code')->nullable();
            $table->json('panier_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panier_taxes');
    }
};
