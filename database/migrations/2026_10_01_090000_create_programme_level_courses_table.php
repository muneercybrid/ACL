<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Courses a school has chosen to run for one programme at one level.
 *
 * Why a new table rather than `curriculum_courses`:
 *
 * `curriculum_courses` hangs off `curriculum_versions`, which is the national
 * NUC list. Its programme chain carries no organization — all 238 rows in
 * `programmes` have `organization_id` NULL — so a course written there is
 * visible to every school. A level coordinator at one university editing that
 * table would silently change what another university runs, which the scope
 * rule forbids.
 *
 * This table is keyed to `academic_programs`, which does carry
 * `organization_id`, and is scoped to the exact programme, level and school
 * the coordinator administers. Two schools running the same programme keep
 * independent lists.
 *
 * `ccmas_course_id` is null for a course the coordinator typed in themselves,
 * which is the escape hatch the NUC list does not cover. The title, code and
 * credit units are stored inline either way, so a list keeps reading correctly
 * if the CCMAS import is ever re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_level_courses', function (Blueprint $table) {
            $table->id();

            // The offering this belongs to. Carries organization_id, which is
            // what makes the row a school's course rather than a national one.
            $table->foreignId('academic_program_id')
                ->constrained('academic_programs')
                ->cascadeOnDelete();

            // Same range as level_coordinators.level, which had to be widened
            // from tinyint to hold 300-800.
            $table->unsignedSmallInteger('level');

            // Null when the coordinator entered the course by hand.
            $table->unsignedBigInteger('ccmas_course_id')->nullable();

            $table->string('course_code', 32);
            $table->string('title');
            $table->decimal('credit_units', 4, 1)->nullable();

            // 'ccmas' when taken from the NUC list, 'manual' when typed in.
            $table->string('source', 16)->default('ccmas');

            $table->string('semester', 16)->nullable();
            $table->string('course_type', 32)->nullable();
            $table->boolean('is_mandatory')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // The same course may not be added twice to one programme level,
            // however it arrived. Scoped to the offering so two schools may
            // each hold their own copy of the same course.
            $table->unique(
                ['academic_program_id', 'level', 'course_code'],
                'plc_offering_level_code_unq'
            );

            $table->index(['academic_program_id', 'level'], 'plc_offering_level_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_level_courses');
    }
};
