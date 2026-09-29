<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `admin_platform_settings` table.
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
        Schema::create('admin_platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->default('');
            $table->string('group', 50)->default('');
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['key'], 'admin_platform_settings_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_platform_settings');
    }
};
