<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `course_chapters` table.
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
        Schema::create('course_chapters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedTinyInteger('position');
            $table->string('title', 255)->default('');
            $table->string('slug', 255)->default('');
            $table->text('introduction')->nullable();
            $table->text('summary')->nullable();
            $table->text('key_takeaways')->nullable();
            $table->text('further_reading')->nullable();
            $table->unsignedInteger('version')->default(1);
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('status', ['not_generated', 'generating', 'draft', 'under_review', 'changes_requested', 'approved', 'published', 'archived'])->default('not_generated');
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['course_id', 'position'], 'course_chapters_course_id_position_unique');
            $table->index(['course_id'], 'course_chapters_course_id_foreign');
            $table->index(['generated_by'], 'course_chapters_generated_by_foreign');
            $table->index(['reviewed_by'], 'course_chapters_reviewed_by_foreign');
            $table->index(['approved_by'], 'course_chapters_approved_by_foreign');
            $table->index(['course_id', 'status'], 'course_chapters_course_id_status_index');
        });
        Schema::table('course_chapters', function (Blueprint $table) {
            $table->foreign('course_id', 'course_chapters_course_id_foreign')
                ->references('id')
                ->on('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('course_chapters', function (Blueprint $table) {
            $table->foreign('generated_by', 'course_chapters_generated_by_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('course_chapters', function (Blueprint $table) {
            $table->foreign('reviewed_by', 'course_chapters_reviewed_by_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('course_chapters', function (Blueprint $table) {
            $table->foreign('approved_by', 'course_chapters_approved_by_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_chapters');
    }
};
