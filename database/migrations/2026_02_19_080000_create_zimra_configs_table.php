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
        Schema::create('zimra_configs', function (Blueprint $table) {
            $table->id();
            $table->string('base_url');
            $table->string('device_model');
            $table->string('device_version');
            $table->integer('device_id')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('activation_key')->nullable();
            $table->text('private_key')->nullable();
            $table->text('certificate')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zimra_configs');
    }
};
