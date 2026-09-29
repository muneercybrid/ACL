<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `content_reviews` table.
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
        if (Schema::hasTable('content_reviews')) {
            return;
        }

        Schema::create('content_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chapter_id');
            $table->unsignedBigInteger('reviewer_id')->nullable();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('action', ['read', 'edit', 'request_changes', 'approve', 'reject', 'regenerate', 'compare'])->default('read');
            $table->text('comments')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['chapter_id'], 'content_reviews_chapter_id_foreign');
            $table->index(['reviewer_id'], 'content_reviews_reviewer_id_foreign');
            $table->index(['chapter_id', 'status'], 'content_reviews_chapter_id_status_index');
        });
        Schema::table('content_reviews', function (Blueprint $table) {
            $table->foreign('chapter_id', 'content_reviews_chapter_id_foreign')
                ->references('id')
                ->on('course_chapters')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('content_reviews', function (Blueprint $table) {
            $table->foreign('reviewer_id', 'content_reviews_reviewer_id_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reviews');
    }
};
