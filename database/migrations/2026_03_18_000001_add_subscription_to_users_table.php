<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan')->default('trial')->after('remember_token'); // trial, basic, pro
            $table->timestamp('trial_ends_at')->nullable()->after('plan');
            $table->timestamp('subscribed_at')->nullable()->after('trial_ends_at');
            $table->string('company_name')->nullable()->after('subscribed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['plan', 'trial_ends_at', 'subscribed_at', 'company_name']);
        });
    }
};
