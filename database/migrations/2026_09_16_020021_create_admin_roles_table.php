<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `admin_roles` table.
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
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->default('');
            $table->string('slug', 255)->default('');
            $table->text('description')->nullable();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('scope', ['super_admin', 'university_admin'])->default('university_admin');
            $table->boolean('is_system');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['name'], 'admin_roles_name_unique');
            $table->unique(['slug'], 'admin_roles_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_roles');
    }
};
