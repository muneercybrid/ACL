<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `assessment_attempts` table.
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
        // Idempotency guard.
        //
        // TiDB commits DDL implicitly, so a table can exist on the server
        // while this migration is still unrecorded — a lost bookkeeping
        // insert leaves the two out of step. Re-creating the table would then
        // abort the batch. Re-running is harmless once the table is present.
        if (Schema::hasTable('assessment_attempts')) {
            return;
        }

        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('chapter_id')->nullable();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('status', ['in_progress', 'submitted', 'grading', 'graded', 'review_required'])->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->integer('time_taken_seconds')->nullable();
            $table->decimal('total_marks', 8, 2);
            $table->decimal('score_earned', 8, 2);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->text('grading_notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['student_id'], 'assessment_attempts_student_id_foreign');
            $table->index(['assessment_id'], 'assessment_attempts_assessment_id_foreign');
            $table->index(['course_id'], 'assessment_attempts_course_id_foreign');
            $table->index(['chapter_id'], 'assessment_attempts_chapter_id_foreign');
            $table->index(['student_id', 'assessment_id'], 'assessment_attempts_student_id_assessment_id_index');
            $table->index(['student_id', 'course_id'], 'assessment_attempts_student_id_course_id_index');
            $table->index(['status'], 'assessment_attempts_status_index');
        });
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->foreign('student_id', 'assessment_attempts_student_id_foreign')
                ->references('id')
                ->on('students')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->foreign('assessment_id', 'assessment_attempts_assessment_id_foreign')
                ->references('id')
                ->on('assessments')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->foreign('course_id', 'assessment_attempts_course_id_foreign')
                ->references('id')
                ->on('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->foreign('chapter_id', 'assessment_attempts_chapter_id_foreign')
                ->references('id')
                ->on('course_chapters')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempts');
    }
};
