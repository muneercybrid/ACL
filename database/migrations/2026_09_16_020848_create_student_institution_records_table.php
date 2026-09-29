<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `student_institution_records` table.
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
        Schema::create('student_institution_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('organization_id');
            $table->string('institution_registration_number', 255)->default('');
            $table->string('acl_registration_number', 255)->nullable()->default('');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('registration_number_status', ['unverified', 'pending', 'verified'])->default('unverified');
            $table->unsignedBigInteger('faculty_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('academic_program_id')->nullable();
            $table->json('source_data')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['organization_id', 'institution_registration_number'], 'sir_org_reg_unique');
            $table->unique(['acl_registration_number'], 'student_institution_records_acl_registration_number_unique');
            $table->index(['student_id'], 'student_institution_records_student_id_foreign');
            $table->index(['organization_id'], 'student_institution_records_organization_id_foreign');
            $table->index(['faculty_id'], 'student_institution_records_faculty_id_foreign');
            $table->index(['department_id'], 'student_institution_records_department_id_foreign');
            $table->index(['academic_program_id'], 'student_institution_records_academic_program_id_foreign');
        });
        Schema::table('student_institution_records', function (Blueprint $table) {
            $table->foreign('student_id', 'student_institution_records_student_id_foreign')
                ->references('id')
                ->on('students')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('student_institution_records', function (Blueprint $table) {
            $table->foreign('organization_id', 'student_institution_records_organization_id_foreign')
                ->references('id')
                ->on('organizations')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('student_institution_records', function (Blueprint $table) {
            $table->foreign('faculty_id', 'student_institution_records_faculty_id_foreign')
                ->references('id')
                ->on('faculties')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('student_institution_records', function (Blueprint $table) {
            $table->foreign('department_id', 'student_institution_records_department_id_foreign')
                ->references('id')
                ->on('departments')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('student_institution_records', function (Blueprint $table) {
            $table->foreign('academic_program_id', 'student_institution_records_academic_program_id_foreign')
                ->references('id')
                ->on('academic_programs')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_institution_records');
    }
};
