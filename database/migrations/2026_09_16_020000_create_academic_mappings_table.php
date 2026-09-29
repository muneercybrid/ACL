<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `academic_mappings` table.
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
        Schema::create('academic_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('programme_name', 255)->default('');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('faculty_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('academic_program_id');
            $table->boolean('is_active');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['organization_id'], 'academic_mappings_organization_id_foreign');
            $table->index(['faculty_id'], 'academic_mappings_faculty_id_foreign');
            $table->index(['department_id'], 'academic_mappings_department_id_foreign');
            $table->index(['academic_program_id'], 'academic_mappings_academic_program_id_foreign');
            $table->index(['programme_name', 'organization_id'], 'academic_mappings_programme_name_organization_id_index');
        });
        Schema::table('academic_mappings', function (Blueprint $table) {
            $table->foreign('organization_id', 'academic_mappings_organization_id_foreign')
                ->references('id')
                ->on('organizations')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('academic_mappings', function (Blueprint $table) {
            $table->foreign('faculty_id', 'academic_mappings_faculty_id_foreign')
                ->references('id')
                ->on('faculties')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('academic_mappings', function (Blueprint $table) {
            $table->foreign('department_id', 'academic_mappings_department_id_foreign')
                ->references('id')
                ->on('departments')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('academic_mappings', function (Blueprint $table) {
            $table->foreign('academic_program_id', 'academic_mappings_academic_program_id_foreign')
                ->references('id')
                ->on('academic_programs')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_mappings');
    }
};
