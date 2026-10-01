<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision flashcards for a central chapter.
 *
 * Flashcards are asked for alongside the exercises and the chapter quiz, and
 * they belong to the same central layer as those: like chapter_sections,
 * exercises and assessments, a flashcard hangs off course_chapters rather than
 * the offering-scoped `chapters` table, so one set serves every programme and
 * every institution that runs the course.
 *
 * The obvious place to have put these was lesson_blocks, which already has a
 * `type` column. That table cannot be used: lesson_blocks hangs off lessons,
 * lessons hang off `chapters`, and `chapters` is keyed by course_offering_id.
 * A student who had not enrolled would have no flashcard for the chapter they
 * are reading, which is precisely when revision cards are wanted.
 *
 * status defaults to draft, matching assessments and course_chapters. Nothing
 * here is servable to a student until a person reviews and approves it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapter_flashcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')
                ->constrained('course_chapters')
                ->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->text('front');
            $table->text('back');
            $table->enum('status', ['draft', 'approved', 'published'])->default('draft');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['chapter_id', 'position'], 'chapter_flashcards_chapter_position_unique');
            $table->index(['chapter_id', 'status'], 'chapter_flashcards_chapter_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_flashcards');
    }
};
