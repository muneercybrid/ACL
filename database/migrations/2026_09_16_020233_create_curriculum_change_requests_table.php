<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `curriculum_change_requests` table.
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
        Schema::create('curriculum_change_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('requested_by');
            $table->string('field', 255)->default('');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'published'])->default('pending_approval');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['course_id'], 'curriculum_change_requests_course_id_foreign');
            $table->index(['requested_by'], 'curriculum_change_requests_requested_by_foreign');
            $table->index(['approved_by'], 'curriculum_change_requests_approved_by_foreign');
            $table->index(['course_id', 'status'], 'curriculum_change_requests_course_id_status_index');
        });
        Schema::table('curriculum_change_requests', function (Blueprint $table) {
            $table->foreign('course_id', 'curriculum_change_requests_course_id_foreign')
                ->references('id')
                ->on('courses')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('curriculum_change_requests', function (Blueprint $table) {
            $table->foreign('requested_by', 'curriculum_change_requests_requested_by_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
        Schema::table('curriculum_change_requests', function (Blueprint $table) {
            $table->foreign('approved_by', 'curriculum_change_requests_approved_by_foreign')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_change_requests');
    }
};
