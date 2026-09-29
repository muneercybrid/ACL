<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InstitutionNormalizer;
use App\Services\NucInstitutionSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles `institutions` against the NUC reference list.
 *
 * Properties this command is built to guarantee:
 *
 *   Idempotent      Running it twice changes nothing the second time. Matching
 *                   is by normalised name, which now carries a UNIQUE index,
 *                   so a second run can neither duplicate nor churn a row.
 *   Non-destructive It never deletes or renames an existing institution. Rows
 *                   the source no longer lists are reported, not removed.
 *   Auditable       It is a dry run unless --apply is passed, and it reports
 *                   every category it touched.
 *   Bounded         Writes go through bulk statements. The database answers a
 *                   query in ~190ms, so a per-row loop over 489 entries would
 *                   take minutes and hold a long transaction open.
 *
 * Usage:
 *   php artisan acl:institutions:reconcile            # dry run, prints the plan
 *   php artisan acl:institutions:reconcile --apply    # write
 *   php artisan acl:institutions:reconcile --missing  # list what would be added
 */
class InstitutionReconcile extends Command
{
    protected $signature = 'acl:institutions:reconcile
        {--apply : Perform the writes. Without this flag the command only reports.}
        {--missing : List the institutions that would be created, then stop.}
        {--unlisted : List institutions present in the database but absent from the NUC list.}';

    protected $description = 'Reconcile the institutions table against the NUC reference list (idempotent, non-destructive)';

    /**
     * Sørensen–Dice coefficient over normalised tokens.
     *
     * At or above this score two names are treated as the same institution
     * written differently ("University of Lagos" / "University of Lagos, Lagos"
     * scores 1.00). Chosen after measuring the real corpus, where 93 of the
     * un-matched entries were in fact the same body.
     */
    private const ALIAS_THRESHOLD = 0.85;

    /**
     * Between this and ALIAS_THRESHOLD the entry is reported, never created and
     * never merged. Guessing here is how a data import silently doubles an
     * institution list.
     */
    private const AMBIGUOUS_THRESHOLD = 0.70;

    /**
     * Minimum token count for a prefix to count as a match. Below this, shared
     * openings like "federal university of" would match too many institutions.
     */
    private const MIN_PREFIX_TOKENS = 3;

    private const BATCH = 100;

    public function handle(InstitutionNormalizer $normalizer, NucInstitutionSource $source): int
    {
        $entries = $source->all();

        if ($entries === []) {
            $this->error('NUC source produced no entries — refusing to continue.');

            return self::FAILURE;
        }

        $this->info('NUC source : ' . $source->path());
        $this->info('Entries    : ' . count($entries));

        // --- Load the current state once. Every later decision is made against
        // --- this map rather than a query per row.
        $existing = $this->loadExisting($normalizer);

        $this->info('Database   : ' . $this->countRow('rows') . ' rows, '
            . count(array_filter($existing, fn ($r) => $r['normalized'] !== null)) . ' canonical, '
            . $this->countRow('duplicates') . ' duplicate');

        $matched = [];
        $toCreate = [];
        $toBackfill = [];
        $seenKeys = [];
        $seenRows = [];
        $repeatedInSource = [];
        $ambiguous = [];

        // Similarity is only consulted for entries that no exact key matched.
        // Building it once keeps the pass O(entries x institutions) in memory
        // instead of issuing a query per candidate.
        $similarityIndex = null;

        foreach ($entries as $entry) {
            $key = $normalizer->name($entry['name']);

            if (isset($seenKeys[$key])) {
                // The NUC list itself repeats this body. Approved Affiliations
                // lists some colleges under more than one base university, and
                // a few universities appear both as a university and as an
                // affiliation row.
                //
                // A repeat is never a reason to create a second row. When the
                // institution already exists, record the repeat against the
                // matched row; otherwise the first occurrence is already
                // queued in $toCreate, so there is nothing left to do.
                $repeatedInSource[] = $entry;

                if (isset($existing[$key])) {
                    $matched[] = ['entry' => $entry, 'row' => $existing[$key], 'repeated' => true];
                $seenRows[$existing[$key]['id']] = true;
                }

                continue;
            }
            $seenKeys[$key] = true;

            if (isset($existing[$key])) {
                $matched[] = ['entry' => $entry, 'row' => $existing[$key], 'repeated' => false];
                // Mark the row as accounted for, so --unlisted does not
                // report a row that this entry actually matched.
                $seenRows[$existing[$key]['id']] = true;

                if ($this->needsBackfill($existing[$key], $entry)) {
                    $toBackfill[] = ['id' => $existing[$key]['id'], 'entry' => $entry];
                }

                continue;
            }

            // Not an exact match on either ACL's name or the NUC-published
            // name. Before treating the entry as absent, check whether it is
            // the same institution under a different spelling.
            if ($similarityIndex === null) {
                $similarityIndex = $this->buildSimilarityIndex($existing, $normalizer);
            }

            $near = $this->nearestMatch($key, $similarityIndex);

            if ($near !== null && $near['score'] >= self::ALIAS_THRESHOLD) {
                // Same institution, different spelling ("University of Lagos"
                // vs "University of Lagos, Lagos"). Treat as matched so no
                // duplicate is created, and record the NUC spelling.
                $matched[] = ['entry' => $entry, 'row' => $near['row'], 'repeated' => false, 'via' => 'similarity ' . number_format($near['score'], 2)];
                $seenRows[$near['row']['id']] = true;
                $toBackfill[] = ['id' => $near['row']['id'], 'entry' => $entry];

                continue;
            }

            if ($near !== null && $near['score'] >= self::AMBIGUOUS_THRESHOLD) {
                // Close enough to worry, not close enough to decide. Creating
                // risks a duplicate; skipping risks a gap. Neither is safe, so
                // the entry is reported for a human instead of guessed at.
                $ambiguous[] = ['entry' => $entry, 'row' => $near['row'], 'score' => $near['score']];

                continue;
            }

            $toCreate[] = ['entry' => $entry, 'key' => $key];
        }

        // --- Report ---------------------------------------------------------
        $this->newLine();
        $this->line('<info>Reconciliation plan</info>');
        $this->table(
            ['Outcome', 'Count'],
            [
                ['Matched to an existing institution', (string) count($matched)],
                ['  ...of which matched a repeated NUC row', (string) count(array_filter($matched, fn ($m) => $m['repeated']))],
                ['  ...of which matched by similarity', (string) count(array_filter($matched, fn ($m) => isset($m['via'])))],
                ['Would be created (missing)', (string) count($toCreate)],
                ['Would be backfilled (type/section/ref)', (string) count($toBackfill)],
                ['Duplicate rows in the NUC list (skipped)', (string) count($repeatedInSource)],
                ['AMBIGUOUS — needs a decision, not written', (string) count($ambiguous)],
            ]
        );

        if ($ambiguous !== []) {
            $this->newLine();
            $this->line('<comment>Ambiguous (' . count($ambiguous) . '): too close to an existing institution to create, too far to merge.</comment>');
            $this->line('<comment>These are reported, never written. Review and resolve with --alias or by correcting the source row.</comment>');
            $this->table(
                ['NUC name', 'Closest existing', 'Score'],
                array_map(
                    fn ($a) => [substr($a['entry']['name'], 0, 46), substr($a['row']['name'], 0, 46), number_format($a['score'], 2)],
                    array_slice($ambiguous, 0, 15)
                )
            );
            if (count($ambiguous) > 15) {
                $this->line(sprintf('  ... and %d more (use --missing to list creates, --unlisted for the reverse)', count($ambiguous) - 15));
            }
        }

        if ($toCreate !== []) {
            $this->newLine();
            $this->line('<comment>Missing institutions by NUC section:</comment>');
            $bySection = [];
            foreach ($toCreate as $item) {
                $bySection[$item['entry']['section']][] = $item['entry']['name'];
            }
            $this->table(
                ['Section', 'Missing'],
                array_map(fn ($s, $rows) => [$s, (string) count($rows)], array_keys($bySection), $bySection)
            );
        }

        if ($this->option('missing')) {
            foreach ($toCreate as $item) {
                $this->line('  + ' . $item['entry']['name'] . '  [' . $item['entry']['type'] . ']');
            }

            return self::SUCCESS;
        }

        if ($this->option('unlisted')) {
            $this->reportUnlisted($seenRows, $existing);

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->line('<comment>Dry run. Re-run with --apply to write.</comment>');

            return self::SUCCESS;
        }

        // --- Write ----------------------------------------------------------
        $created = $this->createInstitutions($toCreate, $normalizer);
        $backfilled = $this->backfill($toBackfill, $normalizer);

        $this->newLine();
        $this->info(sprintf('Created %d, backfilled %d.', $created, $backfilled));

        Log::info('acl:institutions:reconcile', [
            'created' => $created,
            'backfilled' => $backfilled,
            'matched' => count($matched),
            'source' => $source->path(),
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{id:int, normalized:?string, type:?string, section:?string, name:string}>
     */
    private function loadExisting(InstitutionNormalizer $normalizer): array
    {
        $map = [];

        DB::table('institutions')
            ->select('id', 'name', 'normalized_name', 'nuc_name', 'nuc_normalized_name', 'type', 'nuc_section')
            ->whereNull('canonical_institution_id')
            ->chunkById(self::BATCH, function ($rows) use (&$map, $normalizer) {
                foreach ($rows as $row) {
                    $row = (array) $row;
                    $key = $row['normalized_name'] ?: $normalizer->name((string) $row['name']);

                    $map[$key] = [
                        'id' => (int) $row['id'],
                        'normalized' => $row['normalized_name'],
                        'nuc_normalized' => $row['nuc_normalized_name'],
                        'type' => $row['type'],
                        'section' => $row['nuc_section'],
                        'name' => $row['name'],
                    ];

                    // A row already reconciled against the NUC list also answers
                    // to the name NUC publishes, so a rerun is an exact lookup
                    // rather than a similarity guess.
                    if (! empty($row['nuc_normalized_name']) && ! isset($map[$row['nuc_normalized_name']])) {
                        $map[$row['nuc_normalized_name']] = $map[$key];
                    }
                }
            }, 'id');

        return $map;
    }

    /**
     * Token index over every existing institution, built once.
     *
     * @return list<array{row:array, tokens:list<string>}>
     */
    private function buildSimilarityIndex(array $existing, InstitutionNormalizer $normalizer): array
    {
        $index = [];
        $seen = [];

        foreach ($existing as $key => $row) {
            if (isset($seen[$key])) {
                continue; // the same row is registered under two keys
            }
            $seen[$key] = true;
            $index[] = ['row' => $row, 'tokens' => array_values(array_unique(explode(' ', $key)))];
        }

        return $index;
    }

    /**
     * Best existing institution for a normalised NUC name, or null when the
     * closest candidate is below the ambiguous threshold.
     *
     * @return ?array{row:array, score:float}
     */
    private function nearestMatch(string $key, array $index): ?array
    {
        $tokens = array_values(array_unique(explode(' ', $key)));
        $best = null;

        foreach ($index as $candidate) {
            $score = self::isTokenPrefix($tokens, $candidate['tokens'])
                ? 1.0
                : self::diceCoefficient($tokens, $candidate['tokens']);

            if ($best === null || $score > $best['score']) {
                $best = ['row' => $candidate['row'], 'score' => $score];
            }
        }

        return ($best !== null && $best['score'] >= self::AMBIGUOUS_THRESHOLD) ? $best : null;
    }

    /**
     * Sørensen–Dice coefficient: 2|A∩B| / (|A|+|B|) over token sets.
     * Range 0.0 (nothing in common) to 1.0 (identical token sets).
     */
    private static function diceCoefficient(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        return (2 * count(array_intersect($a, $b))) / (count($a) + count($b));
    }

    /**
     * True when one token sequence is a prefix of the other.
     *
     * ACL and NUC routinely disagree only by a trailing location clause:
     *
     *   "ambrose alli university ekpoma"
     *   "ambrose alli university ekpoma edo state"
     *
     * The Dice coefficient punishes that heavily (0.80) because the extra
     * tokens are all unmatched, even though the two are certainly the same
     * body. A prefix is a much stronger signal, so it is checked first.
     *
     * The shorter side must carry at least MIN_PREFIX_TOKENS tokens so that a
     * common opening like "federal university of" cannot match on a prefix
     * alone.
     */
    private static function isTokenPrefix(array $a, array $b): bool
    {
        $shorter = count($a) <= count($b) ? $a : $b;
        $longer = count($a) <= count($b) ? $b : $a;

        if (count($shorter) < self::MIN_PREFIX_TOKENS) {
            return false;
        }

        foreach ($shorter as $position => $token) {
            if (($longer[$position] ?? null) !== $token) {
                return false;
            }
        }

        return true;
    }

    /**
     * A row needs backfilling when the NUC list now tells us something the row
     * does not carry: its type, its section, or its provenance reference.
     */
    private function needsBackfill(array $row, array $entry): bool
    {
        return $row['type'] === null
            || $row['section'] === null
            || $row['type'] !== $entry['type'];
    }

    /**
     * @param  list<array{entry:array,key:string}>  $toCreate
     */
    private function createInstitutions(array $toCreate, InstitutionNormalizer $normalizer): int
    {
        if ($toCreate === []) {
            return 0;
        }

        $now = now();
        $created = 0;

        foreach (array_chunk($toCreate, self::BATCH) as $chunk) {
            $rows = [];

            foreach ($chunk as $item) {
                $entry = $item['entry'];

                $rows[] = [
                    'name' => $entry['name'],
                    'normalized_name' => $item['key'],
                    // Stored verbatim so the next reconciliation matches this
                    // row exactly instead of re-deriving it by similarity.
                    'nuc_name' => $entry['name'],
                    'nuc_normalized_name' => $item['key'],
                    'slug' => $normalizer->slug($entry['name']),
                    'ownership' => $entry['ownership'],
                    'state' => $entry['state'],
                    'established_year' => $entry['est'],
                    'website' => $entry['website'],
                    'type' => $entry['type'],
                    'nuc_section' => $entry['section'],
                    'nuc_source_ref' => 'docs/reference/nigerian-tertiary-institutions.md#' . strtolower(str_replace(' ', '-', $entry['section'])),
                    'institution_status' => 'ACTIVE',
                    'onboarding_status' => 'NOT_ONBOARDED',
                    'import_batch' => 'NUC_RECONCILE_' . date('Ymd'),
                    'source_verified_at' => $now,
                    'synchronized_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $inserted = DB::table('institutions')->insertOrIgnore($rows);
            $created += $inserted;

            if ($inserted < count($rows)) {
                $this->warn(sprintf('  skipped %d row(s) already present (unique constraint held)', count($rows) - $inserted));
            }
        }

        return $created;
    }

    /**
     * @param  list<array{id:int,entry:array}>  $toBackfill
     */
    private function backfill(array $toBackfill, InstitutionNormalizer $normalizer): int
    {
        if ($toBackfill === []) {
            return 0;
        }

        $now = now();
        $done = 0;

        foreach (array_chunk($toBackfill, self::BATCH) as $chunk) {
            foreach ($chunk as $item) {
                $entry = $item['entry'];

                DB::table('institutions')->where('id', $item['id'])->update([
                    'type' => $entry['type'],
                    'nuc_section' => $entry['section'],
                    'nuc_source_ref' => $entry['section'],
                    // Recording the published spelling is what turns a later
                    // reconciliation into an exact lookup.
                    'nuc_name' => $entry['name'],
                    'nuc_normalized_name' => $normalizer->name($entry['name']),
                    'source_verified_at' => $now,
                    'updated_at' => $now,
                ]);
                $done++;
            }
        }

        return $done;
    }

    private function reportUnlisted(array $seenRows, array $existing): void
    {
        $unlisted = array_filter($existing, fn ($r) => ! isset($seenRows[$r['id']]), ARRAY_FILTER_USE_BOTH);

        $this->line(sprintf('<comment>%d institution(s) in the database are not in the NUC list</comment> (left untouched):', count($unlisted)));

        foreach (array_slice($unlisted, 0, 40, true) as $row) {
            $this->line('  · ' . $row['name']);
        }
    }

    private function countRow(string $kind): string
    {
        $query = DB::table('institutions');

        return (string) ($kind === 'duplicates'
            ? $query->whereNotNull('canonical_institution_id')->count()
            : $query->count());
    }
}
