<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InstitutionNormalizer;
use App\Services\NucInstitutionSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rebuilds `institutions` so the table is an authoritative, deduplicated
 * image of the NUC register.
 *
 * This is the destructive half of the institution work, and the destructive
 * part is deliberately NOT "drop the table and start again".
 *
 * Why not a wipe
 * --------------
 * `institutions` has no incoming foreign keys. It is referenced softly by
 * `users.institution_id` (475 rows) and `role_assignments` (474 rows, the
 * provisioned Institution Admin for every institution).
 *
 * Note that `student_registration_verifications.verified_institution` is NOT
 * one of these references: it is a TEXT column holding the institution name as
 * the applicant typed it, so it survives the removal of duplicate rows
 * untouched and must never be repointed to an id.
 * Truncating the table would silently orphan every one of those, locking out
 * 476 staff accounts. So the rebuild works by identity instead:
 *
 *   - a row that matches a NUC entry KEEPS ITS ID and is overwritten with the
 *     authoritative NUC data, so every soft reference keeps resolving;
 *   - rows that are duplicates of another row are repointed at the survivor
 *     and then removed;
 *   - rows with no NUC equivalent are removed only when nothing references
 *     them, and are reported when something does;
 *   - NUC entries with no row are created, so colleges, polytechnics, distance
 *     learning centres, transnational institutions and affiliated centres all
 *     appear for the first time.
 *
 * The result is idempotent: a second run changes nothing.
 */
class InstitutionRebuild extends Command
{
    protected $signature = 'acl:institutions:rebuild
        {--apply : Perform the writes. Without this flag the command only reports.}
        {--keep-unlisted : Keep institutions absent from the NUC list instead of removing them.}
        {--no-provision : Do not provision an administrator for newly created institutions.}';

    protected $description = 'Rebuild institutions from the NUC register, preserving ids and staff accounts (idempotent)';

    private const ALIAS_THRESHOLD = 0.85;
    private const AMBIGUOUS_THRESHOLD = 0.70;
    private const MIN_PREFIX_TOKENS = 3;
    private const BATCH = 100;

    /** @var list<array{id:int,name:string,canonical_id:int}> duplicate rows to retire */
    private array $duplicateRows = [];

    /** @var array<string, array<string,string>> table => (column => data type), memoised per run */
    private array $columnTypes = [];

    /** @var list<string> aliases whose ACL-side row is no longer present */
    private array $unresolvedAliases = [];

    public function handle(InstitutionNormalizer $normalizer, NucInstitutionSource $source): int
    {
        $entries = $source->all();
        if ($entries === []) {
            $this->error('NUC source produced no entries — refusing to continue.');

            return self::FAILURE;
        }

        $this->info('NUC register : ' . count($entries) . ' entries from ' . $source->path());

        // ---- Phase 1: re-normalise every existing row with the current
        // ---- normaliser, so DB keys and NUC keys are produced by identical
        // ---- rules. This is what makes a second run a no-op.
        $existing = $this->loadExisting($normalizer, true);
        $this->info('Database     : ' . $this->rows() . ' rows ('
            . $this->canonicalCount() . ' canonical, ' . $this->duplicateCount() . ' duplicate)');

        // ---- Phase 2: resolve the NUC entries against those rows.
        $plan = $this->plan($entries, $existing, $normalizer);

        $this->renderPlan($plan);

        if (! $this->option('apply')) {
            $this->newLine();
            $this->line('<comment>Dry run. Re-run with --apply to rebuild.</comment>');

            return self::SUCCESS;
        }

        // ---- Phase 3: write.
        //
        // Deliberately NOT one large transaction. Against a database that
        // answers a query in ~190ms, a transaction spanning 281 updates, 140
        // inserts and 146 deletes holds locks for minutes and is killed by the
        // engine's transaction limits part-way through — leaving the table
        // half-rewritten, which defeats the point of wrapping it.
        //
        // Instead each step commits in small batches and every step is
        // idempotent, so an interrupted run is resumed simply by running the
        // command again. Order matters: references are repointed before the
        // rows they point at disappear.
        $report = [];
        foreach ([
            'renormalised' => fn () => $this->renormalise($existing, $normalizer),
            'updated' => fn () => $this->updateMatched($plan['matched'], $normalizer),
            'repointed' => fn () => $this->repointDuplicates($plan['duplicateGroups']),
            'removed_duplicates' => fn () => $this->removeDuplicateRows($plan['duplicateGroups']),
            'created' => fn () => $this->create($plan['toCreate'], $normalizer),
            'removed_unlisted' => fn () => $this->removeUnlisted($plan['unlisted']),
        ] as $step => $run) {
            $this->line(sprintf('  %-22s running...', str_replace('_', ' ', $step)));
            $report[$step] = $run();
            $this->line(sprintf('  %-22s %d', str_replace('_', ' ', $step), $report[$step]));
        }

        $this->newLine();
        $this->info('Rebuild complete.');
        foreach ($report as $what => $n) {
            $this->line(sprintf('  %-22s %d', str_replace('_', ' ', $what), $n));
        }

        if ($plan['ambiguous'] !== []) {
            $this->warn(sprintf(
                '%d NUC entries were left unmatched because they are too close to an existing row to guess at. '
                . 'They are listed by "acl:institutions:reconcile".',
                count($plan['ambiguous'])
            ));
        }

        if (! $this->option('no-provision')) {
            $this->call('acl:institutions:provision-admins', ['--only-missing' => true]);
        }

        Log::info('acl:institutions:rebuild', $report + ['created_names' => array_slice(array_column($plan['toCreate'], 'name'), 0, 50)]);

        return self::SUCCESS;
    }

    /**
     * The reviewed equivalence table, validated on load so a malformed entry
     * is reported rather than silently treated as a non-match.
     *
     * @return array<string,string>
     */
    private function aliases(): array
    {
        $aliases = config('institution_aliases', []);

        if (! is_array($aliases)) {
            $this->error('config/institution_aliases.php did not return an array; ignoring it.');

            return [];
        }

        foreach ($aliases as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                $this->error('config/institution_aliases.php contains a non-string entry; ignoring the table.');

                return [];
            }
        }

        return $aliases;
    }

    /**
     * Decide, for every NUC entry and every existing row, what happens to it.
     */
    private function plan(array $entries, array $existing, InstitutionNormalizer $normalizer): array
    {
        $byKey = [];
        foreach ($existing as $key => $row) {
            $byKey[$key] ??= $row;
        }

        $index = [];
        $indexedRows = [];
        foreach ($byKey as $key => $row) {
            $index[] = ['row' => $row, 'tokens' => array_values(array_unique(explode(' ', $key)))];
            $indexedRows[$row['id']] = true;
        }

        $matched = [];
        $toCreate = [];
        $ambiguous = [];
        $claimed = [];
        $seen = [];

        // Reviewed equivalences, consulted only after an exact match has
        // failed. Keyed by the register's normalized name so the lookup is a
        // single array hit, and only ever applied when it names a row that is
        // actually present — a stale alias can never match nothing into being
        // a match.
        $aliases = $this->aliases();
        $aliasTarget = [];
        foreach ($aliases as $aclKey => $nucName) {
            if (isset($byKey[$aclKey])) {
                $aliasTarget[$normalizer->name($nucName)] = $byKey[$aclKey];
            }
        }

        foreach ($entries as $entry) {
            $key = $normalizer->name($entry['name']);

            if (isset($seen[$key])) {
                continue; // the register lists this body more than once
            }
            $seen[$key] = true;

            if (isset($byKey[$key])) {
                $matched[] = ['entry' => $entry, 'row' => $byKey[$key]];
                $claimed[$byKey[$key]['id']] = true;

                continue;
            }

            // A reviewed equivalence outranks a fuzzy score: it records that
            // somebody already compared these two names and found them to be
            // the same body.
            if (isset($aliasTarget[$key])) {
                $matched[] = ['entry' => $entry, 'row' => $aliasTarget[$key], 'alias' => true];
                $claimed[$aliasTarget[$key]['id']] = true;

                continue;
            }

            $tokens = array_values(array_unique(explode(' ', $key)));
            $best = null;
            foreach ($index as $candidate) {
                $score = self::isTokenPrefix($tokens, $candidate['tokens'])
                    ? 1.0
                    : self::dice($tokens, $candidate['tokens']);

                if ($best === null || $score > $best['score']) {
                    $best = ['row' => $candidate['row'], 'score' => $score];
                }
            }

            if ($best !== null && $best['score'] >= self::ALIAS_THRESHOLD) {
                // Same institution, different spelling. Claim the row so it is
                // not also treated as unlisted, and do not create a new one.
                $matched[] = ['entry' => $entry, 'row' => $best['row'], 'score' => $best['score']];
                $claimed[$best['row']['id']] = true;

                continue;
            }

            if ($best !== null && $best['score'] >= self::AMBIGUOUS_THRESHOLD) {
                $ambiguous[] = ['entry' => $entry, 'row' => $best['row'], 'score' => $best['score']];
                $claimed[$best['row']['id']] = true;

                continue;
            }

            $toCreate[] = ['entry' => $entry, 'key' => $key, 'name' => $entry['name']];
        }

        // Existing rows nothing matched. Duplicates are grouped so their
        // references can be moved to a survivor before they are removed.
        $unmatched = [];
        foreach ($byKey as $row) {
            if (! isset($claimed[$row['id']]) && $row['is_duplicate'] === false) {
                $unmatched[] = $row;
            }
        }

        $duplicateGroups = [];
        foreach ($this->duplicateRows as $row) {
            $duplicateGroups[$row['canonical_id']][] = $row['id'];
        }

        return [
            'matched' => $matched,
            'toCreate' => $toCreate,
            'ambiguous' => $ambiguous,
            'unlisted' => $unmatched,
            'duplicateGroups' => $duplicateGroups,
        ];
    }

    private function renderPlan(array $plan): void
    {
        $this->newLine();
        $this->line('<info>Rebuild plan</info>');
        $this->table(
            ['Action', 'Count'],
            [
                ['Matched to a NUC entry (id preserved)', (string) count($plan['matched'])],
                ['  ...matched by reviewed alias', (string) count(array_filter($plan['matched'], fn ($m) => ! empty($m['alias'])))],
                ['  ...matched by spelling variant', (string) count(array_filter($plan['matched'], fn ($m) => isset($m['score'])))],
                ['Aliases applied but no matching row', (string) count($this->unresolvedAliases)],
                ['Will be created from the register', (string) count($plan['toCreate'])],
                ['Duplicate rows to remove', (string) array_sum(array_map('count', $plan['duplicateGroups']))],
                ['Rows absent from the register', (string) count($plan['unlisted'])],
                ['Ambiguous — left alone', (string) count($plan['ambiguous'])],
            ]
        );

        if ($plan['toCreate'] !== []) {
            $bySection = [];
            foreach ($plan['toCreate'] as $item) {
                $bySection[$item['entry']['section']][] = $item['entry']['name'];
            }
            $this->newLine();
            $this->line('<comment>New institutions by register section:</comment>');
            $this->table(
                ['Section', 'New', 'Example'],
                array_map(
                    fn ($s, $rows) => [$s, (string) count($rows), (string) $rows[0]],
                    array_keys($bySection),
                    $bySection
                )
            );
        }
    }

    // ---------------------------------------------------------------- writes

    /** Recompute normalized_name / slug / nuc_* for every row, in bulk. */
    private function renormalise(array $existing, InstitutionNormalizer $normalizer): int
    {
        $done = 0;
        $now = now();

        foreach (array_chunk($existing, self::BATCH) as $chunk) {
            foreach ($chunk as $row) {
                $key = $normalizer->name((string) $row['name']);
                if ($key === $row['normalized'] && $row['slug'] === $normalizer->slug((string) $row['name'])) {
                    continue;
                }

                DB::table('institutions')->where('id', $row['id'])->update([
                    'normalized_name' => $key,
                    'slug' => $normalizer->slug((string) $row['name']),
                    'nuc_name' => $row['nuc_name'] ?? $row['name'],
                    'nuc_normalized_name' => $row['nuc_normalized'] ?? $key,
                    'updated_at' => $now,
                ]);
                $done++;
            }
        }

        return $done;
    }

    /**
     * Overwrite matched rows with the register's authoritative values, keeping
     * the row id so users, roles and verifications keep pointing at it.
     */
    private function updateMatched(array $matched, InstitutionNormalizer $normalizer): int
    {
        $now = now();
        $done = 0;

        foreach ($matched as $m) {
            $entry = $m['entry'];

            $row = [
                // ACL keeps its own display name unless the row has none; the
                // register spelling is always preserved in nuc_name.
                'nuc_name' => $entry['name'],
                'nuc_normalized_name' => $normalizer->name($entry['name']),
                'type' => $entry['type'],
                'nuc_section' => $entry['section'],
                'ownership' => $entry['ownership'],
                'nuc_source_ref' => $entry['section'],
                'source_verified_at' => $now,
                'updated_at' => $now,
            ];

            // Descriptive fields are written only when the register carries a
            // value, so a blank in a directory listing never erases something
            // ACL already holds. Where both sides have a value the register
            // wins: it is the authoritative, dated source.
            foreach (['website' => $entry['website'], 'established_year' => $entry['est'], 'state' => $entry['state']] as $column => $value) {
                if ($value !== null) {
                    $row[$column] = $value;
                }
            }

            DB::table('institutions')->where('id', $m['row']['id'])->update($row);
            $done++;
        }

        return $done;
    }

    /**
     * Move every soft reference from a duplicate row to its survivor.
     *
     * Runs before the duplicates are deleted, which is the whole reason the
     * duplicates were not simply removed earlier.
     */
    private function repointDuplicates(array $groups): int
    {
        $moved = 0;

        foreach ($groups as $canonicalId => $duplicateIds) {
            // One small transaction per duplicate group keeps the lock window
            // to a handful of statements instead of holding locks across the
            // whole rebuild.
            $moved += $this->inTransaction(function () use ($canonicalId, $duplicateIds) {
                return $this->repointGroup($canonicalId, $duplicateIds);
            });
        }

        return $moved;
    }

    private function repointGroup(int $canonicalId, array $duplicateIds): int
    {
        $moved = 0;
        $moved += $this->repoint('users', 'institution_id', $duplicateIds, $canonicalId);
        $moved += $this->repoint('role_assignments', 'entity_id', $duplicateIds, $canonicalId, 'entity_type', 'App\\Models\\Institution');
        $moved += $this->repoint('student_registration_verifications', 'organization_id', $duplicateIds, $canonicalId);
        $moved += $this->repoint('student_institution_records', 'organization_id', $duplicateIds, $canonicalId);
        $moved += $this->repoint('faculties', 'organization_id', $duplicateIds, $canonicalId);
        $moved += $this->repoint('institution_onboardings', 'organization_id', $duplicateIds, $canonicalId);
        $moved += $this->repoint('institution_staff_invitations', 'organization_id', $duplicateIds, $canonicalId);
        $moved += $this->repoint('superadmin_audit_logs', 'organization_id', $duplicateIds, $canonicalId);
        $moved += $this->repoint('organization_memberships', 'organization_id', $duplicateIds, $canonicalId);

        return $moved;
    }

    /**
     * Runs a unit of work in its own transaction. TiDB Serverless kills long
     * transactions, so ACL commits in bounded pieces and relies on idempotency
     * rather than on one rollback covering an entire long job.
     */
    private function inTransaction(callable $work)
    {
        return DB::transaction($work);
    }

    private function repoint(string $table, string $column, array $from, int $to, ?string $typeColumn = null, ?string $typeValue = null): int
    {
        if ($from === [] || ! \Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
            return 0;
        }

        // Only integer reference columns are repointed. Some columns that
        // sound like references (student_registration_verifications.
        // verified_institution) are TEXT and hold the institution name as the
        // applicant typed it; writing an id into those raises
        // "Invalid datetime format: Truncated incorrect INTEGER value".
        // Column types are memoised for the whole run. information_schema is
        // expensive on TiDB Cloud, and this is consulted once per table per
        // duplicate group — about 1300 lookups without the cache, which is what
        // turned a two-minute pass into an eleven-minute one.
        if (! array_key_exists($table, $this->columnTypes)) {
            $rows = DB::select(
                'SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            );

            $map = [];
            foreach ($rows as $r) {
                $map[$r->COLUMN_NAME] = $r->DATA_TYPE;
            }
            $this->columnTypes[$table] = $map;
        }

        if (($this->columnTypes[$table][$column] ?? null) !== 'bigint') {
            return 0;
        }

        $query = DB::table($table)->whereIn($column, $from);
        if ($typeColumn !== null) {
            $query->where($typeColumn, $typeValue);
        }

        return $query->update([$column => $to]);
    }

    private function removeDuplicateRows(array $groups): int
    {
        $ids = [];
        foreach ($groups as $duplicateIds) {
            foreach ($duplicateIds as $id) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return 0;
        }

        return DB::table('institutions')->whereIn('id', $ids)->delete();
    }

    private function create(array $toCreate, InstitutionNormalizer $normalizer): int
    {
        if ($toCreate === []) {
            return 0;
        }

        $now = now();
        $created = 0;

        foreach (array_chunk($toCreate, self::BATCH) as $chunk) {
            $rows = [];
            foreach ($chunk as $item) {
                $e = $item['entry'];
                $rows[] = [
                    'name' => $e['name'],
                    'normalized_name' => $item['key'],
                    'nuc_name' => $e['name'],
                    'nuc_normalized_name' => $item['key'],
                    'slug' => $normalizer->slug($e['name']),
                    'ownership' => $e['ownership'],
                    'state' => $e['state'],
                    'established_year' => $e['est'],
                    'website' => $e['website'],
                    'type' => $e['type'],
                    'nuc_section' => $e['section'],
                    'nuc_source_ref' => $e['section'],
                    'institution_status' => 'ACTIVE',
                    'onboarding_status' => 'NOT_ONBOARDED',
                    'import_batch' => 'NUC_REBUILD_' . date('Ymd'),
                    'source_verified_at' => $now,
                    'synchronized_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // insertOrIgnore: the UNIQUE index on normalized_name is the
            // authority, and a concurrent run cannot create a duplicate.
            $inserted = DB::table('institutions')->insertOrIgnore($rows);
            $created += $inserted;
        }

        return $created;
    }

    /**
     * Remove rows the register does not list — but only when nothing points at
     * them, and never when --keep-unlisted was passed.
     */
    private function removeUnlisted(array $unlisted): int
    {
        if ($this->option('keep-unlisted') || $unlisted === []) {
            return 0;
        }

        $removable = [];
        foreach ($unlisted as $row) {
            if ($this->isReferenced($row['id'])) {
                $this->warn(sprintf('  keeping unlisted #%d %s — still referenced', $row['id'], $row['name']));

                continue;
            }
            $removable[] = $row['id'];
        }

        if ($removable === []) {
            return 0;
        }

        foreach (array_chunk($removable, self::BATCH) as $chunk) {
            DB::table('institutions')->whereIn('id', $chunk)->delete();
        }

        return count($removable);
    }

    private function isReferenced(int $institutionId): bool
    {
        foreach ([
            ['users', 'institution_id'],
            ['student_registration_verifications', 'organization_id'],
            ['student_institution_records', 'organization_id'],
            ['faculties', 'organization_id'],
            ['institution_onboardings', 'organization_id'],
            ['institution_staff_invitations', 'organization_id'],
            ['organization_memberships', 'organization_id'],
            ['courses', 'institution_id'],
            ['programmes', 'organization_id'],
        ] as [$table, $column]) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, $column)
                && DB::table($table)->where($column, $institutionId)->exists()) {
                return true;
            }
        }

        return DB::table('role_assignments')
            ->where('entity_type', 'App\\Models\\Institution')
            ->where('entity_id', $institutionId)
            ->exists();
    }

    // ----------------------------------------------------------------- load

    private function loadExisting(InstitutionNormalizer $normalizer, bool $renormaliseFlag): array
    {
        $rows = [];
        $seenKey = [];

        DB::table('institutions')
            ->select('id', 'name', 'slug', 'normalized_name', 'nuc_name', 'nuc_normalized_name', 'canonical_institution_id')
            ->chunkById(self::BATCH, function ($chunk) use (&$rows, &$seenKey, $normalizer) {
                foreach ($chunk as $r) {
                    $r = (array) $r;
                    $isDuplicate = $r['canonical_institution_id'] !== null;
                    $key = $r['normalized_name'] ?: $normalizer->name((string) $r['name']);

                    $row = [
                        'id' => (int) $r['id'],
                        'name' => $r['name'],
                        'normalized' => $r['normalized_name'],
                        'slug' => $r['slug'],
                        'nuc_name' => $r['nuc_name'],
                        'nuc_normalized' => $r['nuc_normalized_name'],
                        'is_duplicate' => $isDuplicate,
                        'canonical_id' => $r['canonical_institution_id'] === null ? (int) $r['id'] : (int) $r['canonical_institution_id'],
                    ];

                    // Duplicates take no part in matching: they are a
                    // transitional state whose references are repointed and
                    // which is then removed. They are collected separately so
                    // the removal pass can see them.
                    if ($isDuplicate) {
                        $this->duplicateRows[] = [
                            'id' => (int) $r['id'],
                            'name' => $r['name'],
                            'canonical_id' => (int) $r['canonical_institution_id'],
                        ];

                        continue;
                    }

                    if (! isset($seenKey[$key])) {
                        $seenKey[$key] = true;
                        $rows[$key] = $row;
                    }
                }
            }, 'id');

        return $rows;
    }

    // -------------------------------------------------------------- helpers

    private static function dice(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        return (2 * count(array_intersect($a, $b))) / (count($a) + count($b));
    }

    private static function isTokenPrefix(array $a, array $b): bool
    {
        $shorter = count($a) <= count($b) ? $a : $b;
        $longer = count($a) <= count($b) ? $b : $a;

        if (count($shorter) < self::MIN_PREFIX_TOKENS) {
            return false;
        }

        foreach ($shorter as $i => $token) {
            if (($longer[$i] ?? null) !== $token) {
                return false;
            }
        }

        return true;
    }

    private function rows(): string
    {
        return (string) DB::table('institutions')->count();
    }

    private function canonicalCount(): string
    {
        return (string) DB::table('institutions')->whereNull('canonical_institution_id')->count();
    }

    private function duplicateCount(): string
    {
        return (string) DB::table('institutions')->whereNotNull('canonical_institution_id')->count();
    }
}
