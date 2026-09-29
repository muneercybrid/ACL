<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `admin_user_roles` table.
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
        Schema::create('admin_user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('admin_role_id');
            $table->unsignedBigInteger('university_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['user_id', 'admin_role_id', 'university_id'], 'admin_user_roles_user_id_admin_role_id_university_id_unique');
            $table->index(['user_id'], 'admin_user_roles_user_id_foreign');
            $table->index(['admin_role_id'], 'admin_user_roles_admin_role_id_foreign');
            $table->index(['university_id'], 'admin_user_roles_university_id_foreign');
            $table->index(['user_id', 'university_id'], 'admin_user_roles_user_id_university_id_index');
        });
        Schema::table('admin_user_roles', function (Blueprint $table) {
            $table->foreign('user_id', 'admin_user_roles_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('admin_user_roles', function (Blueprint $table) {
            $table->foreign('admin_role_id', 'admin_user_roles_admin_role_id_foreign')
                ->references('id')
                ->on('admin_roles')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('admin_user_roles', function (Blueprint $table) {
            $table->foreign('university_id', 'admin_user_roles_university_id_foreign')
                ->references('id')
                ->on('organizations')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_roles');
    }
};
