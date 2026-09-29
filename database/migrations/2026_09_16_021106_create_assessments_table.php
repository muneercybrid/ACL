<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `assessments` table.
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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();
            $table->string('title', 255)->default('');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('scope', ['practice', 'course', 'chapter', 'midterm', 'final'])->default('chapter');
            $table->text('description')->nullable();
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->decimal('passing_score', 5, 2);
            $table->string('status', 20)->default('draft');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['course_id'], 'assessments_course_id_foreign');
            $table->index(['chapter_id'], 'assessments_chapter_id_foreign');
            $table->index(['course_id', 'scope'], 'assessments_course_id_scope_index');
        });
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreign('course_id', 'assessments_course_id_foreign')
                ->references('id')
                ->on('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreign('chapter_id', 'assessments_chapter_id_foreign')
                ->references('id')
                ->on('course_chapters')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
