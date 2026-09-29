<?php

namespace App\Console\Commands;

use App\Models\Curriculum\Programme;
use App\Models\Curriculum\NucDiscipline;
use App\Services\CcmasCatalogueParser;
use App\Services\ProgrammeCatalogue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads the NUC CCMAS catalogue into `programmes` and `courses`.
 *
 * The central catalogue is the foundation everything else rests on: an
 * organization offers programmes from it, a student registers under one, a
 * level coordinator is appointed for one. It was empty, which is why nothing
 * downstream could be built.
 *
 * Dry run by default, matching the pattern in InstitutionRebuild.
 */
class CcmasImportProgrammes extends Command
{
    protected $signature = 'acl:ccmas:import-programmes
        {--apply : Write to the database. Without it, only report.}
        {--chunk=200 : Rows per insert batch}';

    protected $description = 'Import the NUC CCMAS programme catalogue and its courses';

    public function handle(): int
    {
        $apply = $this->option('apply');
        $chunk = max(1, (int) $this->option('chunk'));

        $this->line($apply
            ? '<fg=green>APPLYING</> — rows will be written'
            : '<fg=yellow>DRY RUN</> — pass --apply to write');

        $parser = new CcmasCatalogueParser();
        $documents = $parser->availableDocuments();

        if ($documents === []) {
            $this->error('no CCMAS documents found under storage/app/nuc-ccmas');

            return self::FAILURE;
        }

        $programmes = $parser->all();
        $disciplines = NucDiscipline::pluck('id', 'code');

        $this->newLine();
        $this->table(
            ['CCMAS documents', 'programmes', 'course entries', 'disciplines matched'],
            [[
                (string) count($documents),
                (string) count($programmes),
                (string) array_sum(array_map(fn ($p) => count($p['courses'] ?? []), $programmes)),
                (string) count(array_filter(array_column($programmes, 'nuc_discipline_code'), fn ($c) => $disciplines->has($c))),
            ]]
        );

        if (! $apply) {
            $this->newLine();
            $this->line('Dry run complete. Re-run with <info>--apply</info> to write.');

            return self::SUCCESS;
        }

        $written = $this->writeProgrammes($programmes, $disciplines, $chunk);
        $courses = $this->writeCourses($programmes, $disciplines, $chunk);

        $this->newLine();
        $this->info("programmes written : {$written}");
        $this->info("courses written    : {$courses}");
        $this->newLine();
        $this->line('After:');
        $this->table(
            ['table', 'rows'],
            [
                ['programmes', (string) Programme::count()],
                ['courses', (string) DB::table('courses')->count()],
            ]
        );

        return self::SUCCESS;
    }

    private function writeProgrammes(array $programmes, $disciplines, int $chunk): int
    {
        $written = 0;

        foreach (array_chunk($programmes, $chunk) as $batch) {
            $rows = [];

            foreach ($batch as $programme) {
                $name = $this->cleanName($programme['name'] ?? '');

                if ($name === '') {
                    continue;
                }

                $normalized = ProgrammeCatalogue::normalize($name);
                if ($normalized === '') {
                    continue;
                }

                // A table of contents contains section headings as well as
                // programmes, and some of them look like a degree. "Minimum
                // Academic Standards" is not a programme of study and must not
                // become one, or students could register for it.
                if ($this->isNotAProgramme($normalized)) {
                    continue;
                }

                $rows[] = [
                    'name' => $name,
                    'normalized_name' => $normalized,
                    'code' => ProgrammeCatalogue::deriveCode($name),
                    'nuc_discipline_id' => $disciplines->get($programme['nuc_discipline_code'] ?? null),
                    'degree_type' => $programme['degree_type'] ?? null,
                    'duration_years' => (int) ($programme['duration_years'] ?? 0) ?: 3,
                    'scope' => 'national',
                    'verification_status' => 'verified',
                    'source_type' => 'nuc_ccmas',
                    'source_document' => $programme['source_document'] ?? null,
                    'date_verified' => now()->toDateString(),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows !== []) {
                // Programmes are keyed on normalized_name, which has a unique
                // index, so a straight insert would fail on the second run. The
                // in-list de-duplication below stops a single batch colliding
                // with itself; ProgrammeCatalogue::backfillCodes() then guarantees
                // code uniqueness afterwards.
                $seen = [];
                $deduped = [];
                foreach ($rows as $row) {
                    if (isset($seen[$row['normalized_name']])) {
                        continue;
                    }
                    $seen[$row['normalized_name']] = true;
                    $deduped[] = $row;
                }

                foreach ($deduped as $row) {
                    $existing = Programme::where('normalized_name', $row['normalized_name'])->first();
                    if ($existing) {
                        $existing->update($row);
                    } else {
                        Programme::create($row);
                    }
                    $written++;
                }
            }
        }

        return $written;
    }

    /**
     * Loads courses in bulk.
     *
     * The obvious implementation -- an exists() check per row, then an insert
     * per row -- is two round trips for each of roughly 11,700 courses. Against
     * TiDB at about 200 ms per statement that is hours of pure waiting; the
     * first attempt was abandoned at 1,438 rows for exactly this reason.
     *
     * Instead: read the existing codes once, then bulk insert what is missing.
     * Two queries per chunk regardless of chunk size.
     */
    private function writeCourses(array $programmes, $disciplines, int $chunk): int
    {
        $rows = [];

        foreach ($programmes as $programme) {
            $disciplineId = $disciplines->get($programme['nuc_discipline_code'] ?? null);

            foreach ($programme['courses'] ?? [] as $code => $title) {
                if (! is_string($code) || $code === '' || ! is_string($title) || trim($title) === '') {
                    continue;
                }

                $rows[] = [
                    'code' => $code,
                    'slug' => strtolower($code),
                    'normalized_code' => strtoupper($code),
                    'title' => trim($title),
                    'normalized_title' => mb_strtolower(trim($title)),
                    'nuc_discipline_id' => $disciplineId,
                    'source_type' => 'nuc_ccmas',
                    'source_document' => $programme['source_document'] ?? null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // A course code repeats across programmes -- GST111 General Studies is
        // offered by dozens of them -- so collapse to one row per code, keeping
        // the first discipline that claims it.
        $byCode = [];
        foreach ($rows as $row) {
            $byCode[$row['normalized_code']] ??= $row;
        }
        $rows = array_values($byCode);

        $existing = DB::table('courses')->pluck('normalized_code')->flip()->all();
        $toInsert = array_values(array_filter($rows, fn ($r) => ! isset($existing[$r['normalized_code']])));

        $this->line(sprintf(
            '  courses: %d unique, %d already present, %d to insert',
            count($rows),
            count($rows) - count($toInsert),
            count($toInsert)
        ));

        foreach (array_chunk($toInsert, $chunk) as $batch) {
            DB::table('courses')->insert($batch);
        }

        return count($toInsert);
    }

    /**
     * Headings that a contents page can present like a programme.
     */
    private function isNotAProgramme(string $normalized): bool
    {
        $junk = [
            'minimum academic standards',
            'core curriculum minimum academic standards',
            'general education courses',
            'introduction',
            'preface',
            'foreword',
            'acknowledgements',
            'table of contents',
            'appendix',
            'glossary',
            'references',
            'index',
        ];

        return in_array($normalized, $junk, true);
    }

    private function cleanName(string $name): string
    {
        $name = preg_replace('/\.\s*\./', ' ', $name) ?? $name;
        $name = preg_replace('/\s{2,}/', ' ', $name) ?? $name;

        return trim($name);
    }
}
