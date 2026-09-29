<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `course_outlines` table.
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
        Schema::create('course_outlines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedInteger('version')->default(1);
            $table->text('description')->nullable();
            $table->json('learning_outcomes')->nullable();
            $table->json('recommended_resources')->nullable();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('status', ['not_available', 'draft', 'approved', 'published'])->default('not_available');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_locked');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['course_id', 'version'], 'course_outlines_course_id_version_unique');
            $table->index(['course_id'], 'course_outlines_course_id_foreign');
            $table->index(['created_by'], 'course_outlines_created_by_foreign');
            $table->index(['approved_by'], 'course_outlines_approved_by_foreign');
        });
        Schema::table('course_outlines', function (Blueprint $table) {
            $table->foreign('course_id', 'course_outlines_course_id_foreign')
                ->references('id')
                ->on('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('course_outlines', function (Blueprint $table) {
            $table->foreign('created_by', 'course_outlines_created_by_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('course_outlines', function (Blueprint $table) {
            $table->foreign('approved_by', 'course_outlines_approved_by_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_outlines');
    }
};
