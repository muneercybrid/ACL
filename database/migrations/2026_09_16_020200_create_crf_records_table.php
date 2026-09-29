<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `crf_records` table.
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
        Schema::create('crf_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('academic_session_id');
            $table->unsignedBigInteger('semester_id');
            $table->string('document_path', 255)->nullable()->default('');
            $table->json('extracted_courses')->nullable();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('reconciliation_status', ['pending', 'matched', 'variance_detected', 'reviewed'])->default('pending');
            $table->json('variance_details')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['student_id', 'academic_session_id', 'semester_id'], 'crf_records_student_id_academic_session_id_semester_id_unique');
            $table->index(['student_id'], 'crf_records_student_id_foreign');
            $table->index(['academic_session_id'], 'crf_records_academic_session_id_foreign');
            $table->index(['semester_id'], 'crf_records_semester_id_foreign');
            $table->index(['reviewed_by'], 'crf_records_reviewed_by_foreign');
        });
        Schema::table('crf_records', function (Blueprint $table) {
            $table->foreign('student_id', 'crf_records_student_id_foreign')
                ->references('id')
                ->on('students')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('crf_records', function (Blueprint $table) {
            $table->foreign('academic_session_id', 'crf_records_academic_session_id_foreign')
                ->references('id')
                ->on('academic_sessions')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('crf_records', function (Blueprint $table) {
            $table->foreign('semester_id', 'crf_records_semester_id_foreign')
                ->references('id')
                ->on('semesters')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('crf_records', function (Blueprint $table) {
            $table->foreign('reviewed_by', 'crf_records_reviewed_by_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crf_records');
    }
};
