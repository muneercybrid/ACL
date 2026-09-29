<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `curriculum_change_logs` table.
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
        Schema::create('curriculum_change_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('field', 255)->default('');
            $table->text('before_value')->nullable();
            $table->text('after_value')->nullable();
            $table->text('reason')->nullable();
            $table->string('request_id', 255)->nullable()->default('');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['course_id'], 'curriculum_change_logs_course_id_foreign');
            $table->index(['changed_by'], 'curriculum_change_logs_changed_by_foreign');
            $table->index(['course_id', 'created_at'], 'curriculum_change_logs_course_id_created_at_index');
        });
        Schema::table('curriculum_change_logs', function (Blueprint $table) {
            $table->foreign('course_id', 'curriculum_change_logs_course_id_foreign')
                ->references('id')
                ->on('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('curriculum_change_logs', function (Blueprint $table) {
            $table->foreign('changed_by', 'curriculum_change_logs_changed_by_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_change_logs');
    }
};
