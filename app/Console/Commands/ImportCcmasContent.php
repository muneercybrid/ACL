<?php

namespace App\Console\Commands;

use App\Services\Courses\CcmasContentIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Persists the parsed CCMAS content into the database.
 *
 * The index is derived from the seventeen discipline documents, but the
 * catalogue cannot depend on files sitting in storage being present on every
 * environment. This writes each canonical entry once; re-running refreshes the
 * text without touching how many documents a code appeared in, so the sharing
 * evidence accumulates rather than being overwritten by a document that
 * happens to have been removed.
 *
 * The command is idempotent and reports what it did rather than assuming.
 */
class ImportCcmasContent extends Command
{
    protected $signature = 'ccmas:import-content {--dry-run : Report coverage, write nothing}';

    protected $description = 'Parse the CCMAS documents into ccmas_course_content';

    public function handle(CcmasContentIndex $index): int
    {
        $all = $index->all();

        if ($all === []) {
            $this->error('No courses parsed. Are the CCMAS documents present in storage/app/nuc-ccmas/?');

            return self::FAILURE;
        }

        $codes = DB::table('courses')->pluck('normalized_code')->filter()->flip();
        $matched = 0;
        $unmatched = [];

        foreach ($all as $code => $entry) {
            if (isset($codes[$code])) {
                $matched++;
            } else {
                $unmatched[] = $code;
            }
        }

        $this->info(sprintf(
            'Parsed %d course codes from %d discipline documents.',
            count($all),
            count(array_unique(array_merge(...array_column(array_values($all), 'documents'))))
        ));
        $this->line(sprintf('  %d match a course in the catalogue', $matched));
        $this->line(sprintf('  %d parsed but not in the catalogue yet', count($unmatched)));

        $shared = count(array_filter($all, fn ($e) => count($e['documents']) > 1));
        $this->line(sprintf('  %d appear in more than one discipline document', $shared));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $now = now();
        $written = 0;

        foreach (array_chunk($all, 200, true) as $chunk) {
            $rows = [];

            foreach ($chunk as $code => $entry) {
                $rows[] = [
                    'code' => $code,
                    'title' => $entry['title'],
                    'units' => $entry['units'] ?: null,
                    'learning_outcomes' => $entry['learning_outcomes'],
                    'course_contents' => $entry['course_contents'],
                    'variants' => $entry['variants'],
                    'documents' => json_encode($entry['documents']),
                    'indexed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('ccmas_course_content')->upsert($rows, ['code'], [
                'title', 'units', 'learning_outcomes', 'course_contents',
                'documents', 'indexed_at', 'updated_at',
            ]);

            $written += count($rows);
        }

        $this->info("Wrote {$written} course content rows.");
        $this->line('Chapter generation reads this table, so it is now the single source for course content.');

        return self::SUCCESS;
    }
}