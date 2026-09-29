<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `admin_audit_logs` table.
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
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_role', 50)->nullable()->default('');
            $table->unsignedBigInteger('acting_as_id')->nullable();
            $table->string('action', 100)->default('');
            $table->string('resource_type', 100)->default('');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->unsignedBigInteger('university_id')->nullable();
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->string('ip_address', 45)->nullable()->default('');
            $table->text('user_agent')->nullable();
            $table->string('request_id', 36)->nullable()->default('');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('severity', ['info', 'low', 'medium', 'high', 'critical'])->default('info');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('result', ['success', 'failure'])->default('success');
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['actor_id'], 'admin_audit_logs_actor_id_foreign');
            $table->index(['acting_as_id'], 'admin_audit_logs_acting_as_id_foreign');
            $table->index(['university_id'], 'admin_audit_logs_university_id_foreign');
            $table->index(['target_user_id'], 'admin_audit_logs_target_user_id_foreign');
            $table->index(['actor_id', 'created_at'], 'admin_audit_logs_actor_id_created_at_index');
            $table->index(['action', 'created_at'], 'admin_audit_logs_action_created_at_index');
            $table->index(['university_id', 'created_at'], 'admin_audit_logs_university_id_created_at_index');
            $table->index(['resource_type', 'resource_id'], 'admin_audit_logs_resource_type_resource_id_index');
            $table->index(['target_user_id', 'created_at'], 'admin_audit_logs_target_user_id_created_at_index');
            $table->index(['severity', 'created_at'], 'admin_audit_logs_severity_created_at_index');
            $table->index(['request_id'], 'admin_audit_logs_request_id_index');
        });
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->foreign('actor_id', 'admin_audit_logs_actor_id_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->foreign('acting_as_id', 'admin_audit_logs_acting_as_id_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->foreign('target_user_id', 'admin_audit_logs_target_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->foreign('university_id', 'admin_audit_logs_university_id_foreign')
                ->references('id')
                ->on('organizations')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
