<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `verification_records` table.
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
        Schema::create('verification_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('provider', 255)->default('');
            $table->string('verification_type', 255)->default('');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['student_id'], 'verification_records_student_id_foreign');
            $table->index(['student_id', 'provider', 'status'], 'verification_records_student_id_provider_status_index');
        });
        Schema::table('verification_records', function (Blueprint $table) {
            $table->foreign('student_id', 'verification_records_student_id_foreign')
                ->references('id')
                ->on('students')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_records');
    }
};
