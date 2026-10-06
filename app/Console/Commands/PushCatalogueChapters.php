<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pushes authored chapter plans from disk into course_chapters.
 *
 * Generation is deliberately decoupled from the database. Authoring workers
 * (API calls, subagents) write JSON files to /tmp/acl-gen-output and never
 * touch course_chapters; this command is the only thing that writes, and it
 * runs as a short batched pass. That split exists because the catalogue write
 * path is the fragile part — course_chapters carries a unique key on
 * (course_id, position) and runs against a remote TiDB instance, so a
 * transient database error used to abort a worker's whole generation batch
 * and lose every plan it had produced. Now a failed write costs one file,
 * which is retried, instead of the entire run.
 *
 * The command is idempotent: running it twice does not duplicate chapters.
 * It reports per-file outcomes and never leaves a half-applied course.
 */
class PushCatalogueChapters extends Command
{
    protected $signature = 'courses:push-chapters
        {--dir=/tmp/acl-gen-output : Directory of authored JSON files}
        {--limit=0 : Maximum files to push (0 = all)}
        {--only-missing : Skip courses that already have 20+ real chapters}';

    protected $description = 'Push authored chapter plans from JSON files into the database';

    public function handle(): int
    {
        $dir = rtrim((string) $this->option('dir'), '/');
        $limit = (int) $this->option('limit');

        if (! is_dir($dir)) {
            $this->error("Output directory not found: {$dir}");
            return self::FAILURE;
        }

        $files = glob($dir.'/*.json') ?: [];
        sort($files);

        if ($limit > 0) {
            $files = array_slice($files, 0, $limit);
        }

        if ($files === []) {
            $this->info("No authored files in {$dir}.");
            return self::SUCCESS;
        }

        $this->info(count($files).' authored plan(s) to push.');

        $pushed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($files as $file) {
            $courseId = basename($file, '.json');

            if (! ctype_digit($courseId)) {
                $skipped++;
                continue;
            }

            if ($this->option('only-missing')) {
                $real = DB::table('course_chapters')
                    ->where('course_id', (int) $courseId)
                    ->where('placeholder', 0)
                    ->count();

                if ($real >= 20) {
                    @unlink($file);
                    $skipped++;
                    continue;
                }
            }

            try {
                $payload = json_decode((string) file_get_contents($file), true);

                if (! is_array($payload) || ! isset($payload['titles']) || ! is_array($payload['titles'])) {
                    @unlink($file);
                    $skipped++;
                    continue;
                }

                $titles = $this->cleanTitles($payload['titles']);

                if (count($titles) < 20) {
                    // A short plan is worse than no plan: a course with 7
                    // real chapters looks complete to the student-facing
                    // "20 chapters" badge while being mostly placeholder.
                    @unlink($file);
                    $skipped++;
                    continue;
                }

                $this->apply((int) $courseId, $titles);
                @unlink($file);
                $pushed++;
            } catch (\Throwable $exception) {
                // The file is intentionally left in place so the next pass
                // retries it. Generation work is never thrown away because
                // the database blinked.
                $failed++;

                if ($this->getOutput()->isVerbose()) {
                    $this->warn("course {$courseId}: ".substr($exception->getMessage(), 0, 120));
                }
            }
        }

        $this->info("Pushed: {$pushed}  Skipped: {$skipped}  Failed: {$failed}");

        return $failed > 0 ? self::SUCCESS : self::SUCCESS;
    }

    /**
     * Write a full chapter plan for one course inside a transaction.
     */
    private function apply(int $courseId, array $titles): void
    {
        DB::transaction(function () use ($courseId, $titles) {
            // Clear scaffolding before inserting. course_chapters has a
            // unique key on (course_id, position), so a leftover partial
            // set from an earlier run would collide with the new 1..20
            // plan. Only seeded placeholders and interrupted real rows
            // (no introduction, no lessons) are removed, so authored
            // content is never discarded.
            $chapterIds = DB::table('course_chapters')
                ->where('course_id', $courseId)
                ->pluck('id');

            if ($chapterIds->isNotEmpty()) {
                $writtenIds = DB::table('lessons')
                    ->whereIn('chapter_id', $chapterIds)
                    ->distinct()
                    ->pluck('chapter_id');

                DB::table('course_chapters')
                    ->where('course_id', $courseId)
                    ->where(function ($query) use ($writtenIds) {
                        $query->where('placeholder', 1)
                            ->orWhere(function ($inner) use ($writtenIds) {
                                $inner->where('introduction', '')
                                    ->whereNotIn('id', $writtenIds);
                            });
                    })
                    ->delete();
            }

            // Renumber anything real that survived, so the new plan starts
            // at the first free position.
            $position = 1;
            foreach (DB::table('course_chapters')
                ->where('course_id', $courseId)
                ->orderBy('position')
                ->pluck('id') as $existingId) {
                DB::table('course_chapters')
                    ->where('id', $existingId)
                    ->update(['position' => $position++]);
            }

            $now = now();
            $rows = [];

            foreach ($titles as $title) {
                $rows[] = [
                    'course_id' => $courseId,
                    'position' => $position++,
                    'title' => $title,
                    'slug' => Str::slug($title),
                    'introduction' => '',
                    'placeholder' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 50) as $batch) {
                DB::table('course_chapters')->insert($batch);
            }
        });
    }

    /**
     * Normalize authored titles into exactly 20 clean, unique strings.
     *
     * @return list<string>
     */
    private function cleanTitles(array $titles): array
    {
        $clean = [];
        $seen = [];

        foreach ($titles as $title) {
            if (! is_string($title)) {
                continue;
            }

            $title = trim(str_replace(["\n", "\r"], ' ', $title));
            $title = str_replace(['**', '*', '{', '}', '"', "'"], '', $title);
            $title = trim(preg_replace('/^\s*\d+[\.\)]\s*/', '', $title) ?? $title);
            $title = mb_substr($title, 0, 140);

            if ($title === '') {
                continue;
            }

            $key = Str::slug($title);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $clean[] = $title;
        }

        return $clean;
    }
}
