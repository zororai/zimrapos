<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_days', function (Blueprint $table) {
            $table->id();
            $table->integer('fiscal_day_no');
            $table->integer('device_id');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->string('open_operation_id')->nullable();
            $table->string('close_operation_id')->nullable();
            $table->integer('receipt_counter')->default(0);
            $table->json('fiscal_counters')->nullable();
            $table->json('close_response')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'fiscal_day_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_days');
    }
};
