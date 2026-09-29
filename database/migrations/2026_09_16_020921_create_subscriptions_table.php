<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `subscriptions` table.
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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name', 255)->default('');
            $table->string('stripe_id', 255)->nullable()->default('');
            $table->string('stripe_status', 255)->nullable()->default('');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('status', ['active', 'trialing', 'past_due', 'canceled', 'unpaid'])->default('active');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['stripe_id'], 'subscriptions_stripe_id_unique');
            $table->index(['user_id'], 'subscriptions_user_id_foreign');
            $table->index(['user_id', 'status'], 'subscriptions_user_id_status_index');
            $table->index(['stripe_status'], 'subscriptions_stripe_status_index');
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('user_id', 'subscriptions_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
