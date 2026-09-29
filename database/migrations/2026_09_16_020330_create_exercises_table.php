<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `exercises` table.
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
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chapter_id');
            $table->unsignedTinyInteger('position');
            $table->string('title', 255)->default('');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('difficulty', ['basic', 'intermediate', 'advanced'])->default('basic');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('exercise_type', ['short_answer', 'multiple_choice', 'fill_blank', 'scenario', 'problem_solving', 'practical', 'discussion'])->default('short_answer');
            $table->longText('question');
            $table->longText('solution')->nullable();
            $table->longText('explanation')->nullable();
            $table->json('options')->nullable();
            $table->string('correct_answer', 255)->nullable()->default('');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['chapter_id'], 'exercises_chapter_id_foreign');
            $table->index(['chapter_id', 'difficulty'], 'exercises_chapter_id_difficulty_index');
        });
        Schema::table('exercises', function (Blueprint $table) {
            $table->foreign('chapter_id', 'exercises_chapter_id_foreign')
                ->references('id')
                ->on('course_chapters')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
