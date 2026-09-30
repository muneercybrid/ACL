<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes a level coordinator scopeable to a school, and fixes levels 300-800.
 *
 * Two defects in the existing table made the intended model unrepresentable.
 *
 * 1. `level` was unsignedTinyInteger, whose range is 0-255. Levels 300, 400,
 *    500, 600 and 800 raised SQLSTATE 22003 "Numeric value out of range" and
 *    could not be stored at all. That is five of the seven levels ACL runs, and
 *    it is why an earlier coordinator seeding run reported "skipped: 100" —
 *    those rows were failing for a reason that looked like bad data. Widened to
 *    unsignedSmallInteger (0-65535), which covers every level ACL uses.
 *
 * 2. There was no school dimension. `programme_id` points at `programmes`,
 *    which are the national NUC catalogue entries shared by every university,
 *    so a row could express "a coordinator for B.Sc Cybersecurity level 300"
 *    but never "a coordinator for B.Sc Cybersecurity level 300 at Bayero
 *    University". Two competing universities could not each appoint their own
 *    coordinator for the same programme and level, which is the whole point of
 *    a level coordinator being scoped. `organization_id` adds that dimension.
 *
 * The uniqueness rule changes with it: the old key was
 * (programme_id, level, academic_session_id), which under MariaDB's treatment
 * of NULL in unique indexes does not prevent two schools holding a coordinator
 * for the same programme and level — and once organization_id exists, the rule
 * is "one coordinator per school, programme, level, session".
 *
 * `organization_id` is nullable so existing rows remain valid; a NULL means
 * "not yet attached to a school", which is the state a freshly created row is
 * in until an institution appoints someone. It is not a licence for a
 * global coordinator: every authorization check requires a concrete school.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('level_coordinators', function (Blueprint $table) {
            // Widen first: the old column cannot hold the values the new
            // uniqueness rule and the level range both need.
            $table->unsignedSmallInteger('level')->change();
        });

        Schema::table('level_coordinators', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('programme_id');
            $table->foreign('organization_id', 'level_coordinators_organization_id_foreign')
                ->references('id')
                ->on('organizations')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        // Replace the old key. It is dropped before the new one is added
        // because the two overlap on programme_id and level.
        Schema::table('level_coordinators', function (Blueprint $table) {
            $table->dropUnique('level_coord_unq');
        });

        Schema::table('level_coordinators', function (Blueprint $table) {
            // This index is a control, not decoration: it is what makes "one
            // coordinator per school, programme, level and session" true at the
            // database level rather than only in application code.
            $table->unique(
                ['organization_id', 'programme_id', 'level', 'academic_session_id'],
                'level_coord_unq_school'
            );
        });
    }

    public function down(): void
    {
        Schema::table('level_coordinators', function (Blueprint $table) {
            $table->dropUnique('level_coord_unq_school');
        });

        Schema::table('level_coordinators', function (Blueprint $table) {
            // The column is dropped before its foreign key constraint so the
            // constraint does not outlive the column it references.
            $table->dropForeign('level_coordinators_organization_id_foreign');
            $table->dropColumn('organization_id');
        });

        Schema::table('level_coordinators', function (Blueprint $table) {
            $table->unique(['programme_id', 'level', 'academic_session_id'], 'level_coord_unq');
        });

        // Narrowed only after the table no longer needs the wider range. If any
        // row holds a level above 255 this fails loudly rather than truncating
        // a real value.
        Schema::table('level_coordinators', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->change();
        });
    }
};