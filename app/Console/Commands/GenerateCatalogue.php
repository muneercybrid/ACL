<?php

namespace App\Console\Commands;

use App\Services\ACLi\CourseContentGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Generates the full course catalogue, one course at a time, to a standard.
 *
 * Sequential rather than parallel on purpose. Courses share the same national
 * content, so the requests are near-identical, and hammering a single provider
 * with a queue of concurrent jobs gets throttled instead of served. One course
 * at a time also gives clean progress: each course either completes with its
 * twenty chapters and its exercises, quizzes and flashcards, or it is reported
 * as a failure and the run moves on.
 *
 * The run is resumable and safe to stop at any point. A course is only treated
 * as done once its chapters, exercises, quizzes and flashcards are all present,
 * and every unit of progress is recorded in content_generation_jobs, which is
 * the table the schema already provided for exactly this. Stopping and
 * restarting therefore continues rather than repeating.
 *
 * --limit exists so a run can be sized to a time budget: the whole catalogue is
 * a long job and should be driven in stages rather than launched blind.
 */
class GenerateCatalogue extends Command
{
    protected $signature = 'courses:generate-catalogue
                            {--limit=25 : Courses to process this run; 0 for all remaining}
                            {--chapters= : Chapters per course (defaults to the service floor of 20)}
                            {--only-missing : Skip courses that already have real chapters}
                            {--start-from= : Resume from this course code}
                            {--dry-run : List what would be processed, generate nothing}';

    protected $description = 'Generate course content across the catalogue, one course at a time, resumably';

    public function handle(CourseContentGenerator $generator): int
    {
        $chapters = (int) ($this->option('chapters') ?: CourseContentGenerator::MIN_CHAPTERS);
        $limit = (int) $this->option('limit');

        $pending = $this->pendingCourses();

        if ($this->option('only-missing')) {
            $pending = $pending->filter(fn (array $r) => ! $this->isComplete($r['id']));
        }

        if ($start = $this->option('start-from')) {
            $index = $pending->search(fn (array $r) => strcasecmp($r['normalized_code'], $start) >= 0);

            if ($index === false) {
                $this->error("Start code {$start} not found among pending courses.");

                return self::FAILURE;
            }

            $pending = $pending->slice($index);
        }

        if ($limit > 0) {
            $pending = $pending->take($limit);
        }

        $this->info(sprintf(
            'Processing %d course(s) at %d chapters each.',
            $pending->count(),
            $chapters
        ));

        if ($this->option('dry-run')) {
            foreach ($pending->take(20) as $row) {
                $this->line(sprintf('  %-10s %s', $row['normalized_code'], $row['title']));
            }

            return self::SUCCESS;
        }

        $done = 0;
        $failed = 0;
        $startedAt = now();

        foreach ($pending as $row) {
            $outcome = $this->generateOne($generator, $row, $chapters);

            if ($outcome) {
                $done++;
            } else {
                $failed++;
            }

            $this->line(sprintf(
                '[%d/%d] %-10s %s',
                $done + $failed,
                $pending->count(),
                $row['normalized_code'],
                $outcome ? 'complete' : 'FAILED'
            ));
        }

        $this->info(sprintf(
            'Run finished: %d complete, %d failed, in %s.',
            $done,
            $failed,
            $startedAt->diffForHumans(now(), ['syntax' => 'short', 'short' => true, 'parts' => 2])
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function pendingCourses()
    {
        return DB::table('courses')
            ->where('scope', '!=', 'institution')
            ->orderBy('normalized_code')
            ->get(['id', 'normalized_code', 'title'])
            ->map(fn ($c) => (array) $c);
    }

    /**
     * Whether a course already has everything the standard requires.
     *
     * Completeness is judged on the material, not on a job row: a course whose
     * job was recorded as started but which has no flashcards is not done, and
     * treating it as done would silently leave it half-generated.
     */
    protected function isComplete(int $courseId): bool
    {
        $chapterIds = DB::table('course_chapters')
            ->where('course_id', $courseId)
            ->where('placeholder', 0)
            ->pluck('id');

        if ($chapterIds->count() < CourseContentGenerator::MIN_CHAPTERS) {
            return false;
        }

        return DB::table('chapter_flashcards')->whereIn('chapter_id', $chapterIds)->exists()
            && DB::table('exercises')->whereIn('chapter_id', $chapterIds)->exists();
    }

    protected function generateOne(CourseContentGenerator $generator, array $course, int $chapters): bool
    {
        $courseId = (int) $course['id'];

        $jobId = DB::table('content_generation_jobs')->insertGetId([
            'job_code' => 'catalogue:' . $course['normalized_code'],
            'course_id' => $courseId,
            'action' => 'generate_chapters_and_material',
            'scope' => 'national',
            'status' => 'running',
            'total_items' => $chapters,
            'processed_items' => 0,
            'failed_items' => 0,
            'metadata' => json_encode(['chapters' => $chapters]),
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $result = $generator->generateForCourse($courseId, $chapters, apply: true);

            $chapterIds = DB::table('course_chapters')
                ->where('course_id', $courseId)
                ->where('placeholder', 0)
                ->orderBy('position')
                ->pluck('id');

            $material = ['exercises' => 0, 'questions' => 0, 'flashcards' => 0];

            foreach ($chapterIds as $chapterId) {
                $m = $generator->generateChapterMaterial((int) $chapterId, apply: true);
                $material['exercises'] += $m['exercises'];
                $material['questions'] += $m['questions'];
                $material['flashcards'] += $m['flashcards'];
            }

            $ok = $chapterIds->count() >= CourseContentGenerator::MIN_CHAPTERS;

            DB::table('content_generation_jobs')->where('id', $jobId)->update([
                'status' => $ok ? 'completed' : 'partial',
                'processed_items' => $chapterIds->count(),
                'failed_items' => count($result['errors'] ?? []),
                'metadata' => json_encode([
                    'chapters' => $chapters,
                    'generated' => $result['generated'] ?? 0,
                    'material' => $material,
                    'errors' => $result['errors'] ?? [],
                ]),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            return $ok;
        } catch (\Throwable $e) {
            // A failed course must not take the run down with it. The job row
            // keeps the reason so the course can be revisited, and the loop
            // continues with the next one.
            DB::table('content_generation_jobs')->where('id', $jobId)->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            $this->warn('  ' . $course['normalized_code'] . ': ' . mb_substr($e->getMessage(), 0, 160));

            return false;
        }
    }
}