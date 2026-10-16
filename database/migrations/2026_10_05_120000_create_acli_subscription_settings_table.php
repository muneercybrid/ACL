<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ACLi subscription settings.
 *
 * One row. The superadmin edits it directly: toggle the paid
 * gate on or off, set the price, choose the payment type and
 * schedule. There is no per-user subscription table yet --
 * the entitlement service reads this row and the user's own
 * subscription status to decide access.
 *
 * Keeping this as a single-row config table is deliberate:
 * it is the only place the price, the type and the schedule
 * live, so the entitlement check cannot drift from the
 * admin's intent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acli_subscription_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('payment_enabled')->default(false);
            $table->string('payment_type', 32)->default('one_time'); // one_time | monthly | yearly
            $table->decimal('price', 10, 2)->default(0.00);
            $table->string('currency', 8)->default('NGN');
            $table->integer('grace_period_days')->default(7);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // One row only. The entitlement service reads it with
        // first() and the admin UI edits it with update().
        DB::table('acli_subscription_settings')->insert([
            'payment_enabled' => false,
            'payment_type' => 'one_time',
            'price' => 0.00,
            'currency' => 'NGN',
            'grace_period_days' => 7,
            'description' => 'ACLi AI features are free by default. Enable payment to gate access.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('acli_subscription_settings');
    }
};
