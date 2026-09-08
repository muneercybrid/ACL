<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('ai_entitlement')->default(false);
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->integer('daily_token_limit')->nullable()->default(null);
            $table->integer('monthly_token_limit')->nullable()->default(null);
            $table->integer('usage_count_today')->default(0);
            $table->integer('usage_count_this_month')->default(0);
            $table->timestamp('daily_reset_at')->nullable();
            $table->timestamp('monthly_reset_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'ai_entitlement',
                'ai_provider',
                'ai_model',
                'daily_token_limit',
                'monthly_token_limit',
                'usage_count_today',
                'usage_count_this_month',
                'daily_reset_at',
                'monthly_reset_at',
            ]);
        });
    }
};