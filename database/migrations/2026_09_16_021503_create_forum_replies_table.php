<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `forum_replies` table.
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
        if (Schema::hasTable('forum_replies')) {
            return;
        }

        Schema::create('forum_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('forum_thread_id');
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->boolean('accepted');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['forum_thread_id'], 'forum_replies_forum_thread_id_foreign');
            $table->index(['user_id'], 'forum_replies_user_id_foreign');
        });
        Schema::table('forum_replies', function (Blueprint $table) {
            $table->foreign('forum_thread_id', 'forum_replies_forum_thread_id_foreign')
                ->references('id')
                ->on('forum_threads')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('forum_replies', function (Blueprint $table) {
            $table->foreign('user_id', 'forum_replies_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_replies');
    }
};
