<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `content_generation_jobs` table.
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
        Schema::create('content_generation_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_code', 255)->default('');
            $table->unsignedBigInteger('programme_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();
            $table->unsignedTinyInteger('level')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('scope', ['platform', 'university', 'programme', 'level', 'semester', 'course', 'chapter'])->default('course');
            $table->string('action', 255)->default('');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('status', ['queued', 'processing', 'completed', 'failed', 'cancelled'])->default('queued');
            $table->unsignedSmallInteger('total_items')->default(0);
            $table->unsignedSmallInteger('processed_items')->default(0);
            $table->unsignedSmallInteger('failed_items')->default(0);
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['job_code'], 'content_generation_jobs_job_code_unique');
            $table->index(['programme_id'], 'content_generation_jobs_programme_id_foreign');
            $table->index(['course_id'], 'content_generation_jobs_course_id_foreign');
            $table->index(['chapter_id'], 'content_generation_jobs_chapter_id_foreign');
            $table->index(['initiated_by'], 'content_generation_jobs_initiated_by_foreign');
            $table->index(['status', 'created_at'], 'content_generation_jobs_status_created_at_index');
        });
        Schema::table('content_generation_jobs', function (Blueprint $table) {
            $table->foreign('programme_id', 'content_generation_jobs_programme_id_foreign')
                ->references('id')
                ->on('programmes')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('content_generation_jobs', function (Blueprint $table) {
            $table->foreign('course_id', 'content_generation_jobs_course_id_foreign')
                ->references('id')
                ->on('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('content_generation_jobs', function (Blueprint $table) {
            $table->foreign('chapter_id', 'content_generation_jobs_chapter_id_foreign')
                ->references('id')
                ->on('course_chapters')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('content_generation_jobs', function (Blueprint $table) {
            $table->foreign('initiated_by', 'content_generation_jobs_initiated_by_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_generation_jobs');
    }
};
