<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `external_courses` table.
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
        if (Schema::hasTable('external_courses')) {
            return;
        }

        Schema::create('external_courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('faculty_id')->nullable();
            $table->unsignedBigInteger('programme_id')->nullable();
            $table->unsignedBigInteger('external_track_id')->nullable();
            $table->string('title', 255)->default('');
            $table->string('slug', 255)->default('');
            $table->string('short_description', 255)->nullable()->default('');
            $table->longText('long_description')->nullable();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('difficulty', ['beginner', 'intermediate', 'advanced', 'expert'])->default('Beginner');
            $table->string('category', 255)->nullable()->default('');
            $table->string('subcategory', 255)->nullable()->default('');
            $table->string('industry_domain', 255)->nullable()->default('');
            $table->integer('estimated_hours')->nullable();
            $table->json('prerequisites')->nullable();
            $table->json('learning_outcomes')->nullable();
            $table->json('skills')->nullable();
            $table->json('tools')->nullable();
            $table->json('technologies')->nullable();
            $table->json('certification_alignment')->nullable();
            $table->string('provider_reference', 255)->nullable()->default('');
            $table->boolean('is_external');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('status', ['draft', 'published', 'locked', 'archived'])->default('published');
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'external_courses_slug_unique');
            $table->index(['faculty_id'], 'external_courses_faculty_id_foreign');
            $table->index(['programme_id'], 'external_courses_programme_id_foreign');
            $table->index(['external_track_id'], 'external_courses_external_track_id_foreign');
            $table->index(['programme_id', 'is_external'], 'external_courses_programme_id_is_external_index');
            $table->index(['status'], 'external_courses_status_index');
        });
        Schema::table('external_courses', function (Blueprint $table) {
            $table->foreign('faculty_id', 'external_courses_faculty_id_foreign')
                ->references('id')
                ->on('faculties')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('external_courses', function (Blueprint $table) {
            $table->foreign('programme_id', 'external_courses_programme_id_foreign')
                ->references('id')
                ->on('programmes')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('external_courses', function (Blueprint $table) {
            $table->foreign('external_track_id', 'external_courses_external_track_id_foreign')
                ->references('id')
                ->on('external_tracks')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_courses');
    }
};
