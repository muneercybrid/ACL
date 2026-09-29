<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `curriculum_sources` table.
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
        Schema::create('curriculum_sources', function (Blueprint $table) {
            $table->id();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('source_type', ['nuc_ccmas', 'university_handbook', 'faculty_handbook', 'departmental', 'senate_approved', 'portal', 'manual', 'imported'])->default('manual');
            $table->string('title', 255)->default('');
            $table->string('document_name', 255)->nullable()->default('');
            $table->string('document_path', 255)->nullable()->default('');
            $table->string('url', 255)->nullable()->default('');
            $table->string('version', 255)->nullable()->default('');
            $table->string('academic_session', 255)->nullable()->default('');
            $table->date('date_verified')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('confidence', ['verified', 'high', 'medium', 'low', 'needs_review'])->default('needs_review');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['verified_by'], 'curriculum_sources_verified_by_foreign');
            $table->index(['source_type'], 'curriculum_sources_source_type_index');
            $table->index(['confidence'], 'curriculum_sources_confidence_index');
        });
        Schema::table('curriculum_sources', function (Blueprint $table) {
            $table->foreign('verified_by', 'curriculum_sources_verified_by_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_sources');
    }
};
