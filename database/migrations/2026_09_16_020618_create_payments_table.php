<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `payments` table.
 *
 * This table exists in production but no migration created it, which is why a
 * fresh database could not be built and the test suite could not run at all.
 * The definition below is taken from the live production schema, so the schema a
 * test runs against is the schema the application actually serves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 20)->default('');
            $table->string('reference', 255)->default('');
            $table->unsignedBigInteger('amount_kobo');
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 20)->default('pending');
            $table->string('purpose', 40)->default('ai_subscription');
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['reference'], 'payments_reference_unique');
            $table->index(['user_id'], 'payments_user_id_foreign');
            $table->index(['provider'], 'payments_provider_index');
            $table->index(['status'], 'payments_status_index');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('user_id', 'payments_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
