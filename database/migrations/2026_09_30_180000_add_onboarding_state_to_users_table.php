<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the first-login onboarding state to `users`.
 *
 * `force_password_change` already exists and is already enforced by the
 * ForceEditProfile middleware, but it only covers the password. A level
 * coordinator is required to establish their own identity before doing any
 * work — a name and phone they chose, and an address of their own rather than
 * the generated one — so that the generated identity can be retired.
 *
 * `must_complete_onboarding` is the single flag the gate reads. It is
 * deliberately separate from `force_password_change` because they can be set
 * independently: a seeded test account that has already chosen a password has
 * no onboarding left to do, while a coordinator who has a password somehow but
 * has never confirmed their own details is still not cleared. The middleware
 * treats either being outstanding as "not ready".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // True until the person has supplied their own details. False is the
            // only value that lifts the gate.
            $table->boolean('must_complete_onboarding')->default(false)->after('force_password_change');

            // When it was satisfied. Kept because "has this person ever
            // onboarded" is a question ACL needs to answer without inferring it
            // from the absence of a flag.
            $table->dateTime('onboarding_completed_at')->nullable()->after('must_complete_onboarding');

            // The coordinator's own contact number, collected during onboarding.
            // Nullable: a coordinator is not forced to give a number to work, and
            // requiring one would just push people to type a fake one.
            $table->string('phone', 32)->nullable()->after('onboarding_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_complete_onboarding', 'onboarding_completed_at', 'phone']);
        });
    }
};
