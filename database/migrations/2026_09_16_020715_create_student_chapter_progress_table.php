<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `student_chapter_progress` table.
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
        Schema::create('student_chapter_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('chapter_id');
            $table->timestamp('viewed_at')->nullable();
            $table->integer('view_count')->default(0);
            $table->boolean('completed');
            $table->timestamp('completed_at')->nullable();
            $table->decimal('quiz_score', 5, 2)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['student_id', 'chapter_id'], 'student_chapter_progress_student_id_chapter_id_unique');
            $table->index(['student_id'], 'student_chapter_progress_student_id_foreign');
            $table->index(['course_id'], 'student_chapter_progress_course_id_foreign');
            $table->index(['chapter_id'], 'student_chapter_progress_chapter_id_foreign');
            $table->index(['student_id', 'course_id'], 'student_chapter_progress_student_id_course_id_index');
        });
        Schema::table('student_chapter_progress', function (Blueprint $table) {
            $table->foreign('student_id', 'student_chapter_progress_student_id_foreign')
                ->references('id')
                ->on('students')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('student_chapter_progress', function (Blueprint $table) {
            $table->foreign('course_id', 'student_chapter_progress_course_id_foreign')
                ->references('id')
                ->on('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('student_chapter_progress', function (Blueprint $table) {
            $table->foreign('chapter_id', 'student_chapter_progress_chapter_id_foreign')
                ->references('id')
                ->on('course_chapters')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_chapter_progress');
    }
};
