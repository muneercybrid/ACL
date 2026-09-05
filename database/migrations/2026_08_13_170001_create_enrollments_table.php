<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('institutional_free'); // institutional_free|paid|retake|admin_grant
            $table->string('status')->default('active'); // active|completed|failed|withdrawn|expired
            // datetime, not timestamp: MariaDB TIMESTAMP cannot represent a
            // date past 2038-01-19 and raises error 1292 under strict mode.
            // An enrollment expiry is a plausible date to push beyond that.
            $table->dateTime('enrolled_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            // Security: a user can only ever hold ONE enrollment per offering.
            // This is enforced at the database level, not just in application code.
            $table->unique(['user_id', 'course_offering_id']);
            $table->index(['course_offering_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
