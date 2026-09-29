<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `student_external_identities` table.
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
        Schema::create('student_external_identities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('provider', 255)->default('');
            $table->string('identifier', 255)->default('');
            $table->json('metadata')->nullable();
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('status', ['unverified', 'pending', 'verified'])->default('unverified');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('payload')->nullable();
            $table->unique(['provider', 'identifier'], 'student_external_identities_provider_identifier_unique');
            $table->index(['student_id'], 'student_external_identities_student_id_foreign');
            $table->index(['provider', 'identifier'], 'student_external_identities_provider_identifier_index');
        });
        Schema::table('student_external_identities', function (Blueprint $table) {
            $table->foreign('student_id', 'student_external_identities_student_id_foreign')
                ->references('id')
                ->on('students')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_external_identities');
    }
};
