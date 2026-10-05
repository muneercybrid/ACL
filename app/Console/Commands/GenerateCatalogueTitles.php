<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ACLi\ProviderManager;
use App\Services\ACLi\DTO\AIRequest;
use Illuminate\Support\Str;

/**
 * Phase 1: real chapter titles for every course that still has only
 * generic placeholder chapters.
 *
 * The seeded placeholder rows are scaffolding: 28 distinct titles
 * ("Core Concepts and Definitions", "Worked Examples") repeated
 * verbatim across thousands of courses. They are not chapters. This
 * replaces them with titles derived from each course's own CCMAS
 * statement -- one provider call per course, which is what makes
 * the whole catalogue reachable in a day.
 *
 * Bodies are phase 2 (CourseContentGenerator::writeChapter). This
 * command deliberately does not do that.
 *
 * Usage:
 *   php artisan courses:generate-titles --concurrency=6 --limit=0
 *
 * Resumable: a course that already has 20+ non-placeholder chapters
 * is skipped, so the run can be restarted freely.
 */
class GenerateCatalogueTitles extends Command
{
    protected $signature = 'courses:generate-titles
        {--concurrency=6 : Parallel worker processes}
        {--limit=200 : Courses per batch, 0 = all}
        {--start-from= : Resume from this normalized_code}';

    protected $description = 'Replace generic placeholder chapters with real CCMAS-derived chapter titles';

    private const QUEUE_DIR = '/tmp/acl-gen-queue';
    private const LOG_DIR = '/tmp/acl-gen-log';

    public function handle(): int
    {
        $concurrency = max(1, (int) $this->option('concurrency'));
        $limit = (int) $this->option('limit');
        $target = 20;

        $query = DB::table('courses')
            ->where('scope', '!=', 'institution')
            ->whereNotIn('id', function ($q) use ($target) {
                $q->select('course_id')
                    ->from('course_chapters')
                    ->where('placeholder', 0)
                    ->groupBy('course_id')
                    ->havingRaw('COUNT(*) >= ?', [$target]);
            })
            ->orderBy('normalized_code');

        if ($this->option('start-from')) {
            $query->where('normalized_code', '>=', $this->option('start-from'));
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $courses = $query->get(['id', 'code', 'title', 'normalized_code']);

        if ($courses->isEmpty()) {
            $this->info('Every course already has real chapters.');
            return self::SUCCESS;
        }

        $this->info(count($courses) . ' courses need real chapter titles. Starting ' . $concurrency . ' workers.');

        // Write the queue to disk so the child processes read from files
        // rather than inheriting an open PDO connection across the fork.
        $queueFile = self::QUEUE_DIR . '/titles-queue-' . getmypid() . '.txt';
        file_put_contents($queueFile, $courses->map(fn ($c) => $c->id)->implode("\n"));
        $codesFile = self::QUEUE_DIR . '/titles-codes-' . getmypid() . '.txt';
        file_put_contents($codesFile, $courses->map(fn ($c) => $c->code)->implode("\n"));

        $pids = [];
        for ($workerSlot = 0; $workerSlot < $concurrency; $workerSlot++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->error('fork failed');
                continue;
            }
            if ($pid === 0) {
                DB::purge('mysql');
                $slot = $workerSlot;
                $ok = 0; $fail = 0;
                $ids = file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $codes = file($codesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $provider = app(ProviderManager::class)->provider();

                foreach ($ids as $idx => $id) {
                    // Claim one row per N so the workers partition
                    // the queue instead of all starting at the top.
                    if (($idx % $concurrency) !== $slot) {
                        continue;
                    }
                    $id = (int) $id;
                    $code = $codes[$idx] ?? null;
                    try {
                        $course = DB::table('courses')->where('id', $id)->first();
                        if (! $course) { $fail++; continue; }

                        $titles = [];
                        for ($attempt = 1; $attempt <= 3; $attempt++) {
                            try {
                                $titles = $this->titlesFor($provider, $course, $target);
                            } catch (\Throwable $e) {
                                // 429 means every credential for the
                                // model is cooling down. Retrying
                                // immediately only extends the
                                // cooldown, so wait between attempts.
                                if (str_contains($e->getMessage(), '429')) {
                                    usleep(20 * 1000000); // 20s
                                    continue;
                                }
                                usleep(2 * 1000000);
                            }
                            if (count($titles) >= 5) {
                                break;
                            }
                            usleep(2 * 1000000);
                        }

                        if (count($titles) < 5) { $fail++; continue; }
                        $this->applyTitles($id, $course, $titles);
                        $ok++;
                        // Pace the requests. The chat shares
                        // these credentials, so a burst here
                        // shows up as 429s in the chat box.
                        usleep(3 * 1000000); // 3s
                        if ($ok % 20 === 0) {
                            $this->line("  [$code] $ok done, $fail failed");
                        }
                    } catch (\Throwable $e) {
                        $fail++;
                        // A 429 means every credential for the model
                        // is cooling down. Retrying the next course
                        // immediately just extends the cooldown, so
                        // pause before carrying on.
                        if (str_contains($e->getMessage(), '429')) {
                            usleep(15 * 1000000); // 15s
                        }
                        Log::warning('titles: course failed', [
                            'course_id' => $id, 'message' => $e->getMessage(),
                        ]);
                    }
                }
                file_put_contents(self::LOG_DIR . '/titles-done-' . getmypid() . '.txt', "$ok,$fail\n");
                exit(0);
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $done = 0; $failed = 0;
        foreach (glob(self::LOG_DIR . '/titles-done-*.txt') as $f) {
            [$d, $x] = explode(',', trim(file_get_contents($f)));
            $done += (int) $d; $failed += (int) $x;
            @unlink($f);
        }
        @unlink($queueFile); @unlink($codesFile);

        $this->info("Done: $done courses titled, $failed failed.");
        return self::SUCCESS;
    }

    private function titlesFor($provider, object $course, int $count): array
    {
        $source = $this->ccmasFor($course);
        $prompt = "Course: {$course->code} - {$course->title}\n"
            . ($source !== '' ? "NUC CCMAS statement:\n" . mb_substr($source, 0, 2500) . "\n\n" : '')
            . "Propose exactly {$count} chapter titles for this course, easiest first, "
            . "each naming a specific idea taught in this course (not generic placeholders). "
            . "Cover the CCMAS course contents in the order given. "
            . 'Return ONLY a JSON array of strings. No prose, no markdown.';

        $response = $provider->chat(new AIRequest(
            model: (string) config('acli.gateway.model'),
            messages: [
                ['role' => 'system', 'content' => 'You reply with JSON only. No markdown, no commentary.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            maxTokens: 4000,
        ))->content;

        return $this->parseTitles((string) $response, $count);
    }

    private function ccmasFor(object $course): string
    {
        $code = strtoupper((string) $course->normalized_code);
        if ($code === '') {
            return '';
        }
        $row = DB::table('ccmas_course_content')->where('code', $code)->first();
        if (! $row) {
            return '';
        }
        return trim((string) $row->learning_outcomes) . "\n" . trim((string) $row->course_contents);
    }

    private function parseTitles(string $response, int $count): array
    {
        $clean = trim($response);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);
        $clean = trim($clean);

        $start = strpos($clean, '[');
        $end = strrpos($clean, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $clean = substr($clean, $start, $end - $start + 1);
        }

        $decoded = json_decode($clean, true);
        if (! is_array($decoded)) {
            return [];
        }

        $titles = [];
        foreach ($decoded as $item) {
            $title = is_string($item) ? $item : (is_array($item) ? ($item['title'] ?? null) : null);
            if (! is_string($title)) {
                continue;
            }
            $title = trim(preg_replace('/^\d+[.)]\s*/', '', $title));
            // The model wraps titles in markdown emphasis and
            // sometimes leaves a closing brace behind.
            $title = trim(str_replace(['**', '*', '{', '}'], '', $title));
            $title = trim(preg_replace('/\s+/', ' ', $title));
            if ($title === '' || mb_strlen($title) > 140) {
                continue;
            }
            $key = Str::slug($title);
            if (isset($titles[$key])) {
                continue;
            }
            $titles[$key] = $title;
            if (count($titles) >= $count) {
                break;
            }
        }

        return array_values($titles);
    }

    private function applyTitles(int $courseId, object $course, array $titles): void
    {
        DB::transaction(function () use ($courseId, $course, $titles) {
            DB::table('course_chapters')
                ->where('course_id', $courseId)
                ->where('placeholder', 1)
                ->delete();

            // Renumber from 1. Continuing from max(position)
            // left the real chapters at 45-64 because the seeded
            // placeholders had already claimed 1-44.
            $position = 1;

            $rows = [];
            $now = now();
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
}
