<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Curriculum\CcmasCourse;
use App\Services\CcmasCourseExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Loads the NUC CCMAS 2023 course catalogue into `ccmas_courses`.
 *
 * The table is new and empty, so a first run only adds rows. Re-running is a
 * no-op: the natural key is the same composite unique index the migration
 * declares, and a row whose payload is unchanged is not rewritten.
 *
 * Dry run is the default, matching InstitutionRebuild. Coverage is reported
 * per document — including what could NOT be read — because "we imported some
 * courses" is not a statement anyone can act on.
 */
class ImportCcmasCourses extends Command
{
    protected $signature = 'acl:import-ccmas-courses
        {--dry-run : Report only; write nothing}
        {--document= : Import a single document slug, e.g. computing}
        {--limit=0 : Stop after this many courses across all documents. 0 means no limit}
        {--chunk=200 : Rows per insert batch}
        {--rejected-limit=5 : Unparsed candidate lines to print per document}';

    protected $description = 'Import the NUC CCMAS 2023 course catalogue from storage/app/nuc-ccmas';

    /**
     * Corpus filename stem => nuc_disciplines.code. The 17 documents and the
     * 17 discipline rows line up exactly; a slug missing here is reported
     * rather than guessed at.
     *
     * @var array<string, string>
     */
    private const DISCIPLINE_CODES = [
        'admin-mgmt' => 'ADM',
        'agriculture' => 'AGR',
        'allied-health' => 'AHS',
        'architecture' => 'ARC',
        'arts' => 'ART',
        'basic-medical' => 'BMS',
        'comm-media' => 'CMS',
        'computing' => 'CMP',
        'education' => 'EDU',
        'engineering' => 'ENG',
        'env-sciences' => 'ENV',
        'law' => 'LAW',
        'medicine' => 'MED',
        'pharmacy' => 'PHA',
        'sciences' => 'SCI',
        'social-sciences' => 'SOC',
        'veterinary' => 'VET',
    ];

    public function handle(CcmasCourseExtractor $extractor): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = $this->option('document');
        $limit = max(0, (int) $this->option('limit'));
        $chunk = max(1, (int) $this->option('chunk'));
        $rejectedLimit = max(0, (int) $this->option('rejected-limit'));

        $paths = $this->documents($only);

        if ($paths === []) {
            $this->error('No CCMAS documents matched under storage/app/nuc-ccmas.');

            return self::FAILURE;
        }

        $disciplineIds = DB::table('nuc_disciplines')->pluck('id', 'code');

        $tableExists = Schema::hasTable('ccmas_courses');

        if (! $tableExists && ! $dryRun) {
            $this->error('ccmas_courses does not exist. Run php artisan migrate first.');

            return self::FAILURE;
        }

        $this->line($dryRun
            ? '<fg=yellow>DRY RUN</> — nothing will be written'
            : '<fg=green>APPLYING</> — rows will be written');
        $this->line($tableExists
            ? 'Existing rows in ccmas_courses: '.CcmasCourse::query()->count()
            : 'ccmas_courses does not exist yet (run php artisan migrate)');
        $this->newLine();

        $rows = [];
        $perDocument = [];
        $unknownSlugs = [];
        $total = 0;

        foreach ($paths as $path) {
            $slug = pathinfo($path, PATHINFO_FILENAME);

            if (! array_key_exists($slug, self::DISCIPLINE_CODES)) {
                $unknownSlugs[] = $slug;
            }

            $extracted = $extractor->extract((string) File::get($path), $slug);
            $courses = $extracted['courses'];

            if ($limit > 0) {
                $remaining = $limit - $total;

                if ($remaining <= 0) {
                    $courses = [];
                } elseif (count($courses) > $remaining) {
                    $courses = array_slice($courses, 0, $remaining);
                }
            }

            $withUnits = 0;
            $withoutLevel = 0;
            $withoutProgramme = 0;

            foreach ($courses as $course) {
                $course['source_file'] = basename($path);
                $course['discipline_code'] = self::DISCIPLINE_CODES[$slug] ?? null;
                $course['nuc_discipline_id'] = $course['discipline_code'] !== null
                    ? ($disciplineIds[$course['discipline_code']] ?? null)
                    : null;

                $rows[] = $course;

                $course['credit_units'] !== null ? $withUnits++ : null;
                $course['level'] === null ? $withoutLevel++ : null;
                $course['programme_title'] === null ? $withoutProgramme++ : null;
            }

            $total += count($courses);

            $perDocument[] = [
                'document' => $slug,
                'courses' => count($courses),
                'units' => $withUnits,
                'no_units' => count($courses) - $withUnits,
                'no_level' => $withoutLevel,
                'no_programme' => $withoutProgramme,
                'programmes' => count(array_unique(array_filter(array_column($courses, 'programme_title')))),
                'rejected' => count($extracted['rejected']),
                'unconfirmed' => count($extracted['unconfirmed']),
            ];

            if (! $dryRun && $extracted['rejected'] !== []) {
                $this->reportRejected($slug, $extracted['rejected'], $rejectedLimit);
            }
        }

        $this->renderTable($perDocument, $rows, $unknownSlugs);

        if ($dryRun) {
            $this->newLine();
            $this->line('Dry run complete. Re-run without <info>--dry-run</info> to write.');

            return self::SUCCESS;
        }

        $written = $this->persist($rows, $chunk);

        $this->newLine();
        $this->info("inserted : {$written['inserted']}");
        $this->info("updated  : {$written['updated']}");
        $this->info("unchanged: {$written['unchanged']}");
        $this->info('total rows in ccmas_courses: ' . CcmasCourse::query()->count());

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function documents(?string $only): array
    {
        $root = storage_path('app/nuc-ccmas');

        if (! is_dir($root)) {
            return [];
        }

        $paths = glob($root.'/*.txt') ?: [];
        sort($paths);

        if ($only === null || $only === '') {
            return $paths;
        }

        $wanted = mb_strtolower(trim($only));
        $matched = array_values(array_filter(
            $paths,
            fn (string $path): bool => pathinfo($path, PATHINFO_FILENAME) === $wanted
        ));

        if ($matched === []) {
            $this->warn("No document matches slug '{$wanted}'. Known slugs: ");
            $this->line('  '.implode(', ', array_map(
                fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
                $paths
            )));
        }

        return $matched;
    }

    /**
     * @param  list<array<string, mixed>>  $perDocument
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $unknownSlugs
     */
    private function renderTable(array $perDocument, array $rows, array $unknownSlugs): void
    {
        $this->table(
            ['document', 'courses', 'with units', 'no units', 'no level', 'no programme', 'programmes', 'rejected'],
            array_map(
                fn (array $d): array => [
                    (string) $d['document'],
                    (string) $d['courses'],
                    (string) $d['units'],
                    (string) $d['no_units'],
                    (string) $d['no_level'],
                    (string) $d['no_programme'],
                    (string) $d['programmes'],
                    (string) $d['rejected'],
                ],
                $perDocument
            )
        );

        $total = count($rows);
        $withUnits = count(array_filter($rows, fn (array $r): bool => $r['credit_units'] !== null));
        $withLevel = count(array_filter($rows, fn (array $r): bool => $r['level'] !== null));
        $withProgramme = count(array_filter($rows, fn (array $r): bool => $r['programme_title'] !== null));
        $rejected = array_sum(array_column($perDocument, 'rejected'));
        $unconfirmed = array_sum(array_column($perDocument, 'unconfirmed'));

        $this->newLine();
        $this->line(sprintf(
            'TOTAL courses %d  |  with credit units %d (%.1f%%)  |  missing units %d (%.1f%%)',
            $total,
            $withUnits,
            $total > 0 ? ($withUnits / $total) * 100 : 0.0,
            $total - $withUnits,
            $total > 0 ? (($total - $withUnits) / $total) * 100 : 0.0,
        ));
        $this->line(sprintf(
            'with explicit level %d (%.1f%%)  |  with programme title %d (%.1f%%)',
            $withLevel,
            $total > 0 ? ($withLevel / $total) * 100 : 0.0,
            $withProgramme,
            $total > 0 ? ($withProgramme / $total) * 100 : 0.0,
        ));
        $this->line(sprintf(
            'not parsed: %d code-shaped lines rejected, %d bare code headings left unconfirmed',
            $rejected,
            $unconfirmed,
        ));

        if ($unknownSlugs !== []) {
            $this->warn('No discipline code mapped for: '.implode(', ', $unknownSlugs));
        }
    }

    /**
     * @param  list<array{line: int, text: string}>  $rejected
     */
    private function reportRejected(string $slug, array $rejected, int $limit): void
    {
        if ($limit === 0) {
            return;
        }

        $this->newLine();
        $this->line("<comment>{$slug}: ".count($rejected).' code-shaped line(s) not read as courses</comment>');

        foreach (array_slice($rejected, 0, $limit) as $row) {
            $this->line(sprintf('   line %-7d %s', $row['line'], mb_substr($row['text'], 0, 100)));
        }
    }

    /**
     * Insert what is new, update what moved, and leave untouched what did not.
     *
     * A per-row exists() check would be two round trips for each of roughly
     * 9,100 rows; against TiDB that is hours. The natural key is read once and
     * everything is decided in memory.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{inserted: int, updated: int, unchanged: int}
     */
    private function persist(array $rows, int $chunk): array
    {
        $existing = DB::table('ccmas_courses')
            ->get(['discipline_code', 'programme_title', 'course_code', 'title', 'credit_units', 'level', 'status', 'is_active', 'source_document', 'source_file', 'source_line'])
            ->mapWithKeys(fn (object $row): array => [$this->key((array) $row) => (array) $row]);

        $inserts = [];
        $updates = [];
        $unchanged = 0;
        $now = now();

        foreach ($rows as $row) {
            $key = $this->key($row);
            $current = $existing[$key] ?? null;

            if ($current === null) {
                $inserts[] = $row + ['created_at' => $now, 'updated_at' => $now];

                continue;
            }

            if ($this->samePayload($current, $row)) {
                $unchanged++;

                continue;
            }

            $updates[] = [
                'values' => [
                    'credit_units' => $row['credit_units'],
                    'level' => $row['level'],
                    'status' => $row['status'],
                    'is_active' => $row['is_active'],
                    'source_document' => $row['source_document'],
                    'source_file' => $row['source_file'],
                    'source_line' => $row['source_line'],
                    'updated_at' => $now,
                ],
                'key' => [
                    'discipline_code' => $row['discipline_code'],
                    'programme_title' => $row['programme_title'],
                    'course_code' => $row['course_code'],
                    'title' => $row['title'],
                ],
            ];
        }

        foreach (array_chunk($inserts, $chunk) as $batch) {
            DB::table('ccmas_courses')->insert($batch);
        }

        foreach ($updates as $update) {
            DB::table('ccmas_courses')
                ->where($update['key'])
                ->update($update['values']);
        }

        return ['inserted' => count($inserts), 'updated' => count($updates), 'unchanged' => $unchanged];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function key(array $row): string
    {
        return implode('|', [
            (string) ($row['discipline_code'] ?? ''),
            (string) ($row['programme_title'] ?? ''),
            (string) ($row['course_code'] ?? ''),
            (string) ($row['title'] ?? ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     */
    private function samePayload(array $current, array $incoming): bool
    {
        foreach (['credit_units', 'level', 'status', 'is_active', 'source_document', 'source_file', 'source_line'] as $column) {
            $a = $current[$column] ?? null;
            $b = $incoming[$column] ?? null;

            if ($column === 'credit_units' || $column === 'level' || $column === 'source_line') {
                if ($a !== null && $b !== null && (string) $a !== (string) $b) {
                    return false;
                }

                if (($a === null) !== ($b === null)) {
                    return false;
                }

                continue;
            }

            if ((string) ($a ?? '') !== (string) ($b ?? '')) {
                return false;
            }
        }

        return true;
    }
}
