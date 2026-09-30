<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points a school's programme-level offering at a shared central course.
 *
 * Before this, `programme_level_courses` described a course entirely by its own
 * code and title, which meant every school authored its own copy and no two
 * shared anything. It now names a row in `courses` — the platform's single
 * catalogue — so a course's content is written once and read by everyone who
 * runs it, while `course_code` stays as the school's own label for it in that
 * programme.
 *
 * The unique key moves from the code to the course. A programme cannot run the
 * same central course twice under two different codes — that would be one
 * course listed twice — but two different courses may legitimately share a
 * title, and two schools may use the same code for different courses, which is
 * exactly why the code alone was never a safe key.
 *
 * The table is empty, so `course_id` can be NOT NULL. If rows are ever
 * restored from the pre-central mapping backup they will need a course resolved
 * for them first, which is deliberate: nothing may be offered without an
 * identity to share.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_level_courses', function (Blueprint $table) {
            $table->dropUnique('plc_offering_level_code_unq');

            $table->foreignId('course_id')
                ->after('id')
                ->constrained('courses')
                ->cascadeOnDelete()
                ->comment('The central course this offering shares content with');

            $table->unique(
                ['academic_program_id', 'level', 'course_id'],
                'plc_offering_level_course_unq'
            );
        });
    }

    public function down(): void
    {
        Schema::table('programme_level_courses', function (Blueprint $table) {
            $table->dropUnique('plc_offering_level_course_unq');
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');

            $table->unique(
                ['academic_program_id', 'level', 'course_code'],
                'plc_offering_level_code_unq'
            );
        });
    }
};
