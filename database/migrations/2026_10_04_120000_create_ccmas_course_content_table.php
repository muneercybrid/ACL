<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes central course content permanent rather than derived at runtime.
 *
 * Two problems are addressed here.
 *
 * The Learning Outcomes and Course Contents were being re-parsed from the raw
 * CCMAS text files on every call. Those files are build-time artefacts sitting
 * in storage; content the whole catalogue depends on should not disappear if
 * they are cleaned up, moved to an archive, or absent on a fresh checkout. It
 * belongs in the database, once, where it is queryable and backed up with
 * everything else.
 *
 * courses.normalized_code had no unique index. One row per code is what makes
 * a course shared -- a Cybersecurity student and a Biotechnology student must
 * resolve MTH101 to the same row to receive the same chapters -- but that was
 * a property of the data rather than a rule the schema enforced. Any importer,
 * seeder or integration that inserted a second row for an existing code would
 * have quietly split the course and given two schools different content for
 * the same course. Nothing would have raised an error.
 *
 * The uniqueness is added on the code rather than the id because the id is
 * already unique and says nothing. Existing data is checked first: the
 * migration refuses rather than deleting a duplicate course, because choosing
 * which duplicate survives is a decision for a person, not a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccmas_course_content', function (Blueprint $table) {
            // The course code is the identity here, exactly as it is for
            // courses themselves. Shared across disciplines by design.
            $table->string('code', 20)->primary();
            $table->string('title');
            $table->string('units', 80)->nullable();
            $table->text('learning_outcomes');
            $table->text('course_contents');
            // How many discipline documents carried this course, and which.
            // Kept because it is the evidence that a course is genuinely
            // shared rather than one institution's local course.
            $table->unsignedSmallInteger('variants')->default(1);
            $table->json('documents')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index('variants', 'ccmas_course_content_variants_index');
        });

        // Refuse rather than silently de-duplicate: if a duplicate code exists,
        // the two rows may already carry different chapters, and merging them
        // would discard one of them without saying which.
        $duplicates = DB::table('courses')
            ->select('normalized_code')
            ->whereNotNull('normalized_code')
            ->groupBy('normalized_code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('normalized_code');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot guarantee one course per code: '
                .$duplicates->count().' duplicate normalized_code value(s) exist ('
                .$duplicates->take(5)->implode(', ')
                .($duplicates->count() > 5 ? ', ...' : '')
                .'). Merge them by hand before running this migration.'
            );
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->unique('normalized_code', 'courses_normalized_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique('courses_normalized_code_unique');
        });

        Schema::dropIfExists('ccmas_course_content');
    }
};