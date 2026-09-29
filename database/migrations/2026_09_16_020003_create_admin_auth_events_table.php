<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `admin_auth_events` table.
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
        Schema::create('admin_auth_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email_attempted', 255)->nullable()->default('');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('event_type', ['login_attempt', 'login_success', 'login_failure', 'logout', 'password_reset_requested', 'password_reset_completed', 'password_changed', 'mfa_success', 'mfa_failure', 'account_locked', 'account_unlocked', 'session_created', 'session_revoked']);
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('status', ['success', 'failure'])->default('success');
            $table->string('failure_reason', 255)->nullable()->default('');
            $table->string('ip_address', 45)->nullable()->default('');
            $table->text('user_agent')->nullable();
            $table->string('device_id', 255)->nullable()->default('');
            $table->string('session_id', 255)->nullable()->default('');
            $table->string('request_id', 36)->nullable()->default('');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'admin_auth_events_user_id_foreign');
            $table->index(['user_id', 'created_at'], 'admin_auth_events_user_id_created_at_index');
            $table->index(['event_type', 'created_at'], 'admin_auth_events_event_type_created_at_index');
            $table->index(['status', 'created_at'], 'admin_auth_events_status_created_at_index');
            $table->index(['ip_address', 'created_at'], 'admin_auth_events_ip_address_created_at_index');
            $table->index(['email_attempted'], 'admin_auth_events_email_attempted_index');
        });
        Schema::table('admin_auth_events', function (Blueprint $table) {
            $table->foreign('user_id', 'admin_auth_events_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_auth_events');
    }
};
