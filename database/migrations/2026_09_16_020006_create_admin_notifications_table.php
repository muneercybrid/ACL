<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `admin_notifications` table.
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
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('type', ['security', 'user', 'university', 'verification', 'certificate', 'system', 'integration', 'ai', 'content']);
            $table->string('title', 255)->default('');
            $table->text('message');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('severity', ['info', 'warning', 'error', 'critical'])->default('info');
            $table->string('action_url', 255)->nullable()->default('');
            $table->boolean('is_read');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'admin_notifications_user_id_foreign');
            $table->index(['user_id', 'is_read', 'created_at'], 'admin_notifications_user_id_is_read_created_at_index');
            $table->index(['type', 'created_at'], 'admin_notifications_type_created_at_index');
        });
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->foreign('user_id', 'admin_notifications_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
