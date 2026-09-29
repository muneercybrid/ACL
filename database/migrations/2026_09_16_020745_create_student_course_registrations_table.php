<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `student_course_registrations` table.
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
        Schema::create('student_course_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('academic_session_id');
            $table->unsignedBigInteger('semester_id');
            $table->unsignedTinyInteger('level');
            $table->unsignedTinyInteger('credit_units');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('registration_source', ['curriculum', 'crf', 'manual', 'carry_over', 'elective'])->default('curriculum');
            $table->string('status', 20)->default('registered');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['student_id', 'course_id', 'academic_session_id', 'semester_id'], 'std_course_sess_sem');
            $table->index(['student_id'], 'student_course_registrations_student_id_foreign');
            $table->index(['course_id'], 'student_course_registrations_course_id_foreign');
            $table->index(['academic_session_id'], 'student_course_registrations_academic_session_id_foreign');
            $table->index(['semester_id'], 'student_course_registrations_semester_id_foreign');
        });
        Schema::table('student_course_registrations', function (Blueprint $table) {
            $table->foreign('student_id', 'student_course_registrations_student_id_foreign')
                ->references('id')
                ->on('students')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('student_course_registrations', function (Blueprint $table) {
            $table->foreign('course_id', 'student_course_registrations_course_id_foreign')
                ->references('id')
                ->on('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('student_course_registrations', function (Blueprint $table) {
            $table->foreign('academic_session_id', 'student_course_registrations_academic_session_id_foreign')
                ->references('id')
                ->on('academic_sessions')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('student_course_registrations', function (Blueprint $table) {
            $table->foreign('semester_id', 'student_course_registrations_semester_id_foreign')
                ->references('id')
                ->on('semesters')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_course_registrations');
    }
};
