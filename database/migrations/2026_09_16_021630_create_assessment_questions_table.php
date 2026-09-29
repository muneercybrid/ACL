<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `assessment_questions` table.
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
        if (Schema::hasTable('assessment_questions')) {
            return;
        }

        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedSmallInteger('position');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('question_type', ['mcq', 'short_answer', 'essay', 'practical', 'scenario', 'case_study'])->default('mcq');
            $table->longText('question');
            $table->json('options')->nullable();
            $table->text('correct_answer')->nullable();
            $table->text('explanation')->nullable();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('difficulty', ['basic', 'intermediate', 'advanced'])->default('basic');
            $table->unsignedBigInteger('learning_objective_id')->nullable();
            $table->decimal('marks', 5, 2);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['assessment_id'], 'assessment_questions_assessment_id_foreign');
            $table->index(['learning_objective_id'], 'assessment_questions_learning_objective_id_foreign');
            $table->index(['assessment_id', 'position'], 'assessment_questions_assessment_id_position_index');
        });
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->foreign('assessment_id', 'assessment_questions_assessment_id_foreign')
                ->references('id')
                ->on('assessments')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->foreign('learning_objective_id', 'assessment_questions_learning_objective_id_foreign')
                ->references('id')
                ->on('learning_objectives')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_questions');
    }
};
