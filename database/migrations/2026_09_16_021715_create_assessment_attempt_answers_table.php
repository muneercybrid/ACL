<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `assessment_attempt_answers` table.
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
        if (Schema::hasTable('assessment_attempt_answers')) {
            return;
        }

        Schema::create('assessment_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attempt_id');
            $table->unsignedBigInteger('question_id');
            $table->text('student_answer')->nullable();
            $table->text('correct_answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('marks_awarded', 5, 2);
            $table->decimal('marks_possible', 5, 2);
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('grading_status', ['pending', 'auto_graded', 'manual_review', 'manual_graded'])->default('pending');
            $table->text('feedback')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['attempt_id', 'question_id'], 'assessment_attempt_answers_attempt_id_question_id_unique');
            $table->index(['attempt_id'], 'assessment_attempt_answers_attempt_id_foreign');
            $table->index(['question_id'], 'assessment_attempt_answers_question_id_foreign');
            $table->index(['grading_status'], 'assessment_attempt_answers_grading_status_index');
        });
        Schema::table('assessment_attempt_answers', function (Blueprint $table) {
            $table->foreign('attempt_id', 'assessment_attempt_answers_attempt_id_foreign')
                ->references('id')
                ->on('assessment_attempts')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('assessment_attempt_answers', function (Blueprint $table) {
            $table->foreign('question_id', 'assessment_attempt_answers_question_id_foreign')
                ->references('id')
                ->on('assessment_questions')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempt_answers');
    }
};
