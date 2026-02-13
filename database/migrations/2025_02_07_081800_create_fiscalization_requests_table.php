<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscalization_requests', function (Blueprint $table) {
            $table->id();
            $table->string('document_id')->index();
            $table->string('document_type')->index();
            $table->json('request_data');
            $table->string('status')->default('pending')->index();
            $table->json('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscalization_requests');
    }
};
