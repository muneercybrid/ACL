<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `forum_threads` table.
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
        Schema::create('forum_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('academic_program_id')->nullable();
            $table->string('title', 255)->default('');
            $table->text('body');
            $table->string('chapter_tag', 255)->nullable()->default('');
            $table->boolean('resolved');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'forum_threads_user_id_foreign');
            $table->index(['organization_id', 'academic_program_id'], 'forum_threads_organization_id_academic_program_id_index');
            $table->index(['organization_id'], 'forum_threads_organization_id_index');
            $table->index(['academic_program_id'], 'forum_threads_academic_program_id_index');
        });
        Schema::table('forum_threads', function (Blueprint $table) {
            $table->foreign('user_id', 'forum_threads_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_threads');
    }
};
