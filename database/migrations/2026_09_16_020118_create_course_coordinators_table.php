<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `course_coordinators` table.
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
        Schema::create('course_coordinators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['course_id', 'user_id', 'academic_session_id'], 'course_coordinators_course_id_user_id_academic_session_id_unique');
            $table->index(['course_id'], 'course_coordinators_course_id_foreign');
            $table->index(['user_id'], 'course_coordinators_user_id_foreign');
            $table->index(['academic_session_id'], 'course_coordinators_academic_session_id_foreign');
        });
        Schema::table('course_coordinators', function (Blueprint $table) {
            $table->foreign('course_id', 'course_coordinators_course_id_foreign')
                ->references('id')
                ->on('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('course_coordinators', function (Blueprint $table) {
            $table->foreign('user_id', 'course_coordinators_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('course_coordinators', function (Blueprint $table) {
            $table->foreign('academic_session_id', 'course_coordinators_academic_session_id_foreign')
                ->references('id')
                ->on('academic_sessions')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_coordinators');
    }
};
