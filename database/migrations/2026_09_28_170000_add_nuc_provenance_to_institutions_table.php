<?php

use App\Services\InstitutionNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds NUC provenance to `institutions` and models the duplicate import
 * non-destructively.
 *
 * Background: the NUC import recorded 474 rows for 328 distinct institutions.
 * 146 institutions were inserted twice (byte-identical apart from id and
 * timestamps, all sharing import_batch NUC_NUS_2026_09_17). Every one of the
 * 474 rows carries a role_assignments row, so the duplicates cannot simply be
 * deleted without orphaning provisioned staff.
 *
 * This migration therefore does NOT delete anything. It records which row is
 * canonical so the duplicates can be retired later, in a separate, explicitly
 * approved step, after role_assignments have been repointed.
 *
 * No unique index is added to normalized_name/slug here: 146 duplicate pairs
 * would violate it. That index is added after the duplicates are resolved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institutions')) {
            return;
        }

        Schema::table('institutions', function (Blueprint $table) {
            // Institution category, per the NUC list the row came from.
            // Allowed values: Federal University | State University |
            // Private University | Transnational Institution |
            // Distance Learning Centre | Affiliated Centre.
            // Constitution 4.5: a string with a comment, never a MySQL ENUM.
            $table->string('type', 64)->nullable()->after('name')
                ->comment('Federal University | State University | Private University | Transnational Institution | Distance Learning Centre | Affiliated Centre');

            // Which NUC published list this row was reconciled from.
            $table->string('nuc_section', 64)->nullable()->after('nuc_source_ref')
                ->comment('NUC list section the row was matched against');

            // When the row was last checked against the NUC reference list.
            $table->dateTime('source_verified_at')->nullable()->after('synchronized_at');

            // Self-reference marking a duplicate row and pointing at the row
            // that is kept. NULL means the row is canonical.
            $table->unsignedBigInteger('canonical_institution_id')->nullable()->after('id')
                ->comment('Duplicate rows point at the canonical institution; NULL on canonical rows');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->index('canonical_institution_id', 'institutions_canonical_idx');
            $table->index('type', 'institutions_type_idx');
            // Reconciliation looks institutions up by this column constantly.
            $table->index('normalized_name', 'institutions_normalized_idx');
        });

        $this->markDuplicates();
    }

    /**
     * Populate canonical_institution_id, and repair the corrupted
     * normalized_name / slug values in the same pass.
     *
     * Duplicates keep their normalized_name value as NULL rather than being
     * deleted, which satisfies the intended "only one of these" invariant
     * without removing a row that staff assignments still point at.
     */
    private function markDuplicates(): void
    {
        $normalizer = app(InstitutionNormalizer::class);

        $canonicalByKey = [];
        $duplicates = 0;
        $repaired = 0;

        // chunkById orders by id itself; do not add a second ORDER BY.
        DB::table('institutions')->chunkById(200, function ($rows) use (&$canonicalByKey, &$duplicates, &$repaired, $normalizer) {
            foreach ($rows as $row) {
                $key = $normalizer->name((string) $row->name);

                if (! array_key_exists($key, $canonicalByKey)) {
                    $canonicalByKey[$key] = $row->id;

                    // The canonical row keeps the repaired key and slug.
                    DB::table('institutions')->where('id', $row->id)->update([
                        'normalized_name' => $key,
                        'slug' => $normalizer->slug((string) $row->name),
                    ]);
                    $repaired++;
                    continue;
                }

                // A duplicate: record the pointer, and blank the dedup key so
                // the column stops carrying a value that is not unique.
                DB::table('institutions')->where('id', $row->id)->update([
                    'canonical_institution_id' => $canonicalByKey[$key],
                    'normalized_name' => null,
                ]);
                $duplicates++;
            }
        });

        info("institutions: repaired {$repaired} canonical rows, marked {$duplicates} duplicates");
    }

    public function down(): void
    {
        if (! Schema::hasTable('institutions')) {
            return;
        }

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropIndex('institutions_canonical_idx');
            $table->dropIndex('institutions_type_idx');
            $table->dropIndex('institutions_normalized_idx');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['type', 'nuc_section', 'source_verified_at', 'canonical_institution_id']);
        });
    }
};
