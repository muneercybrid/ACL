<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a central course to the NUC row it was taken from.
 *
 * `courses` is the platform's single catalogue: one row per course, holding the
 * identity and whatever content is eventually written against it. `ccmas_courses`
 * stays the provenance record — which NUC document, line, programme and
 * discipline a course came from — and keeps its repetitions, because that
 * repetition is the source document's own structure and is the evidence, not
 * waste.
 *
 * This column is the one explicit join between the two. It is what stops the
 * same NUC course being turned into two central courses the next time two
 * schools both add it: the second lookup finds the row the first one created.
 *
 * Nullable, because a course authored on the platform has no NUC source. The
 * unique index is what makes the link idempotent, and MySQL treats NULLs as
 * distinct in a unique index, so many platform courses may leave it null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedBigInteger('ccmas_course_id')
                ->nullable()
                ->after('nuc_discipline_id')
                ->comment('The NUC CCMAS row this central course was taken from, if any');

            $table->unique('ccmas_course_id', 'courses_ccmas_course_unq');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique('courses_ccmas_course_unq');
            $table->dropColumn('ccmas_course_id');
        });
    }
};
