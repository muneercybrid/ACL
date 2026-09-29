<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Classifies institutions that the captured NUC register does not list.
 *
 * Why these rows exist at all
 * ---------------------------
 * docs/reference/nigerian-tertiary-institutions.md is a point-in-time capture
 * taken on 2026-09-05. Real institutions are established, renamed and merged
 * after any single capture, so a handful of legitimate universities are simply
 * not in it. They arrived in ACL through earlier imports and through student
 * registration, they carry real users, and deleting them would strand those
 * users — so they stay, and this migration gives them an honest type.
 *
 * What it writes
 * --------------
 * Only `type`, derived from the `ownership` already recorded on the row, and
 * only where `type` is currently NULL. Nothing else is touched: no name, no
 * normalized key, no slug, no id, and therefore no reference can move.
 *
 * `nuc_section` is deliberately left NULL. It records which register list the
 * institution was verified against, and these were not verified against one.
 * Leaving it NULL is the accurate answer; inventing a section would be a lie
 * that later reconciliation would treat as a match.
 */
return new class extends Migration
{
    /** ownership value => type value. */
    private const TYPE_BY_OWNERSHIP = [
        'Federal' => 'Federal University',
        'State' => 'State University',
        'Private' => 'Private University',
    ];

    public function up(): void
    {
        $classified = 0;
        $unclassified = [];

        foreach (self::TYPE_BY_OWNERSHIP as $ownership => $type) {
            $classified += DB::table('institutions')
                ->whereNull('type')
                ->where('ownership', $ownership)
                ->update([
                    'type' => $type,
                    'updated_at' => now(),
                ]);
        }

        // Anything still untyped has an ownership the mapping does not cover.
        // Report it rather than guessing, so the gap stays visible.
        foreach (DB::table('institutions')->whereNull('type')->get(['id', 'name', 'ownership']) as $row) {
            $unclassified[] = sprintf('#%d %s (ownership=%s)', $row->id, $row->name, var_export($row->ownership, true));
        }

        info('acl: classified institutions absent from the NUC register', [
            'classified' => $classified,
            'still_unclassified' => $unclassified,
        ]);

        if ($unclassified !== []) {
            // Not fatal: the rows are still usable, they simply have no type.
            // Surfaced here so it appears in the migration log.
            logger()->warning('Institutions remain untyped after register classification', $unclassified);
        }
    }

    public function down(): void
    {
        // Scoped to rows this migration actually touched.
        //
        // A naive `where('type', $type)->update(['type' => null])` would strip
        // the type from every institution classified by the register rebuild,
        // which is most of the table. The rows this migration wrote are
        // exactly those with no register match, and a register match is what
        // sets nuc_section — so `nuc_section IS NULL` isolates them.
        foreach (array_values(self::TYPE_BY_OWNERSHIP) as $type) {
            DB::table('institutions')
                ->where('type', $type)
                ->whereNull('nuc_section')
                ->update(['type' => null]);
        }
    }
};
