<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `certificates` table.
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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');
            $table->string('verification_code', 32)->default('');
            $table->string('title', 255)->default('');
            $table->text('description')->nullable();
            $table->date('issued_date');
            $table->date('expiry_date')->nullable();
            $table->string('certificate_type', 30)->default('course_completion');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('status', ['active', 'revoked', 'expired'])->default('active');
            $table->text('revoke_reason')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->string('pdf_path', 255)->nullable()->default('');
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('grade', 5)->nullable()->default('');
            $table->json('metadata')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['student_id', 'course_id'], 'certificates_student_id_course_id_unique');
            $table->unique(['verification_code'], 'certificates_verification_code_unique');
            $table->index(['student_id'], 'certificates_student_id_foreign');
            $table->index(['course_id'], 'certificates_course_id_foreign');
            $table->index(['issued_by'], 'certificates_issued_by_foreign');
            $table->index(['status', 'issued_date'], 'certificates_status_issued_date_index');
        });
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreign('student_id', 'certificates_student_id_foreign')
                ->references('id')
                ->on('students')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreign('course_id', 'certificates_course_id_foreign')
                ->references('id')
                ->on('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreign('issued_by', 'certificates_issued_by_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
