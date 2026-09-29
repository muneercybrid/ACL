<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `content_versions` table.
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
        Schema::create('content_versions', function (Blueprint $table) {
            $table->id();
            $table->string('versionable_type', 255)->default('');
            $table->unsignedBigInteger('versionable_id');
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('change_summary', 255)->nullable()->default('');
            $table->unsignedBigInteger('created_by')->nullable();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['versionable_type', 'versionable_id', 'version'], 'content_versions_versionable_type_versionable_id_version_unique');
            $table->index(['versionable_type', 'versionable_id'], 'content_versions_versionable_type_versionable_id_index');
            $table->index(['created_by'], 'content_versions_created_by_foreign');
        });
        Schema::table('content_versions', function (Blueprint $table) {
            $table->foreign('created_by', 'content_versions_created_by_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_versions');
    }
};
