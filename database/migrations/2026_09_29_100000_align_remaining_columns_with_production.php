<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the last two columns where the migration-built schema and production
 * had diverged.
 *
 * A column-level diff of a database built purely from migrations against the
 * live production schema left exactly these two outstanding after the
 * reconstruction of the missing tables and columns. Both are additive and
 * nullable-as-production-has-them, so nothing existing changes.
 *
 * `semesters.number` is the semester ordinal (1 = first, 2 = second). It is
 * populated for the 2026-09-08 rows and NULL for the older seeded ones, which
 * is the state production has; the migration preserves that rather than
 * backfilling, because the older rows can be numbered only by assumption.
 *
 * `curriculum_courses.delivery_mode` records how a course is delivered. It
 * carries a MySQL ENUM in production. That is a documented deviation from the
 * Development Constitution section 4.5, which prefers string plus an inline
 * comment; the ENUM is reproduced exactly so a fresh database matches the one
 * the application serves, and the deviation is recorded for a follow-up
 * migration that changes production deliberately rather than silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('semesters', 'number')) {
            Schema::table('semesters', function ($table) {
                $table->tinyInteger('number')->nullable();
            });
        }

        if (! Schema::hasColumn('curriculum_courses', 'delivery_mode')) {
            Schema::table('curriculum_courses', function ($table) {
                $table->enum('delivery_mode', ['physical', 'online', 'blended', 'hybrid'])
                    ->default('physical');
            });
        }
    }

    public function down(): void
    {
        Schema::table('semesters', function ($table) {
            $table->dropColumn('number');
        });

        Schema::table('curriculum_courses', function ($table) {
            $table->dropColumn('delivery_mode');
        });
    }
};
