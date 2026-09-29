<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the UNIQUE constraint on `institutions.normalized_name` that
 * create_institutions_table declared but which never reached the database.
 *
 * The constraint could not be created earlier because every row carried a
 * corrupted, and therefore unique, value. After
 * 2026_09_28_170000_add_nuc_provenance_to_institutions_table repaired the
 * values, the 328 canonical rows hold genuinely unique keys and the 146
 * duplicate rows hold NULL, which MySQL permits to repeat under a UNIQUE
 * index.
 *
 * This is the engine-level expression of the business rule "only one row per
 * NUC institution" (Constitution 4.3). It is a control, not decoration: it is
 * what stops a future import from silently creating a 475th duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A grouped query cannot be summarised with ->count() in Laravel, so
        // the offending keys are materialised and counted in PHP. This runs
        // once, over 474 rows, and only during a migration.
        $duplicates = DB::table('institutions')
            ->whereNotNull('normalized_name')
            ->groupBy('normalized_name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('normalized_name')
            ->count();

        if ($duplicates > 0) {
            // Refuse to install a constraint the data would violate. Resolve
            // the duplicates first rather than dropping rows here.
            throw new RuntimeException(
                "Cannot add unique index on institutions.normalized_name: {$duplicates} value(s) still duplicated. "
                . 'Resolve the duplicate rows before running this migration.'
            );
        }

        foreach (Schema::getIndexes('institutions') as $index) {
            if ($index['columns'] === ['normalized_name'] && $index['unique']) {
                return; // already present
            }
        }

        Schema::table('institutions', function ($table) {
            $table->unique('normalized_name', 'institutions_normalized_name_unique');
        });
    }

    public function down(): void
    {
        foreach (Schema::getIndexes('institutions') as $index) {
            if ($index['name'] === 'institutions_normalized_name_unique') {
                Schema::table('institutions', function ($table) {
                    $table->dropUnique('institutions_normalized_name_unique');
                });

                return;
            }
        }
    }
};
