<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `admin_role_permissions` table.
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
        Schema::create('admin_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_role_id');
            $table->unsignedBigInteger('admin_permission_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['admin_role_id', 'admin_permission_id'], 'admin_role_permissions_admin_role_id_admin_permission_id_unique');
            $table->index(['admin_role_id'], 'admin_role_permissions_admin_role_id_foreign');
            $table->index(['admin_permission_id'], 'admin_role_permissions_admin_permission_id_foreign');
        });
        Schema::table('admin_role_permissions', function (Blueprint $table) {
            $table->foreign('admin_role_id', 'admin_role_permissions_admin_role_id_foreign')
                ->references('id')
                ->on('admin_roles')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('admin_role_permissions', function (Blueprint $table) {
            $table->foreign('admin_permission_id', 'admin_role_permissions_admin_permission_id_foreign')
                ->references('id')
                ->on('admin_permissions')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_permissions');
    }
};
