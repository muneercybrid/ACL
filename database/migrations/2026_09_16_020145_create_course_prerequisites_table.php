<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `course_prerequisites` table.
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
        Schema::create('course_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('prerequisite_id');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('type', ['prerequisite', 'corequisite'])->default('prerequisite');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['course_id', 'prerequisite_id', 'type'], 'course_prerequisites_course_id_prerequisite_id_type_unique');
            $table->index(['course_id'], 'course_prerequisites_course_id_foreign');
            $table->index(['prerequisite_id'], 'course_prerequisites_prerequisite_id_foreign');
        });
        Schema::table('course_prerequisites', function (Blueprint $table) {
            $table->foreign('course_id', 'course_prerequisites_course_id_foreign')
                ->references('id')
                ->on('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('course_prerequisites', function (Blueprint $table) {
            $table->foreign('prerequisite_id', 'course_prerequisites_prerequisite_id_foreign')
                ->references('id')
                ->on('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_prerequisites');
    }
};
