<?php

namespace App\Console\Commands;

use App\Services\ACLi\CourseContentGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the generic placeholder chapters with real, course-specific ones.
 *
 * The placeholder scaffolder gave all 5,839 courses the same 24 structural
 * headings -- "Core Concepts and Definitions", "Theoretical Foundations" and so
 * on. That was a reasonable way to give every course a chapter *structure*
 * while the content was pending, but it was never meant to be the finished
 * article, and a student opening two different courses sees identical lists.
 *
 * This command writes the real thing. It deletes the placeholders for a course
 * first, because leaving them would show a student 24 generic chapters
 * followed by the genuine ones, which is worse than either alone.
 *
 * Everything is written as draft. A generated chapter is unreviewed material,
 * and AGENTS.md section 7 requires Draft -> Human Review -> Approval -> Publish
 * before a student is taught from it.
 */
class RegenerateCourseChapters extends Command
{
    protected $signature = 'courses:regenerate-chapters
                            {--programme= : Academic programme id, e.g. 30001}
                            {--level= : Programme level, e.g. 100}
                            {--code= : A single course code, e.g. COS101}
                            {--chapters=6 : Chapters per course}
                            {--dry-run : Report what would be generated, write nothing}
                            {--material : Also generate exercises, quiz and flashcards per chapter}';

    protected $description = 'Generate real course-specific chapters, replacing the generic placeholders';

    public function handle(CourseContentGenerator $generator): int
    {
        $courseIds = $this->resolveCourses();

        if ($courseIds === []) {
            $this->error('No courses matched. Give --programme and --level, or --code.');

            return self::FAILURE;
        }

        $generator = app(CourseContentGenerator::class);
        $dryRun = (bool) $this->option('dry-run');
        $chapters = (int) $this->option('chapters');
        $failures = 0;

        foreach ($courseIds as $courseId) {
            $course = DB::table('courses')->where('id', $courseId)->first();

            if (! $course) {
                continue;
            }

            $stale = DB::table('course_chapters')
                ->where('course_id', $courseId)
                ->where('placeholder', 1)
                ->count();

            $this->line("{$course->code}  {$course->title}");
            $this->line("   placeholders to replace: {$stale}");

            if ($dryRun) {
                continue;
            }

            // Remove the placeholders before generating, not after: if
            // generation then fails, the course is left with whatever real
            // chapters exist rather than a half-deleted state.
            $this->purgePlaceholders($courseId);

            try {
                $result = $generator->generateForCourse($courseId, $chapters, apply: true);
            } catch (\Throwable $e) {
                $failures++;
                $this->error("   generation threw: {$e->getMessage()}");
                continue;
            }

            $this->line("   chapters generated: " . ($result['generated'] ?? 0));

            foreach ($result['errors'] ?? [] as $error) {
                $this->warn("   {$error}");
                $failures++;
            }

            if (! $this->option('material')) {
                continue;
            }

            $ids = DB::table('course_chapters')
                ->where('course_id', $courseId)
                ->where('placeholder', 0)
                ->orderBy('position')
                ->pluck('id');

            foreach ($ids as $chapterId) {
                $material = $generator->generateChapterMaterial((int) $chapterId, apply: true);
                $this->line(sprintf(
                    '   chapter %d: %d exercises, %d questions, %d flashcards',
                    $chapterId,
                    $material['exercises'],
                    $material['questions'],
                    $material['flashcards']
                ));
            }
        }

        if ($failures > 0) {
            $this->warn("{$failures} failure(s).");
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<int, int>
     */
    protected function resolveCourses(): array
    {
        if ($code = $this->option('code')) {
            return DB::table('courses')
                ->where('normalized_code', strtoupper($code))
                ->pluck('id')
                ->all();
        }

        $programme = (int) $this->option('programme');
        $level = (int) $this->option('level');

        if (! $programme) {
            return [];
        }

        // Only the national layer. The school's own courses keep the chapters
        // their coordinator supplies, so this must never touch them.
        return DB::table('programme_level_courses')
            ->where('academic_program_id', $programme)
            ->where('level', $level ?: 100)
            ->where('source', 'ccmas')
            ->pluck('course_id')
            ->all();
    }

    /**
     * Deletes placeholder chapters and everything hanging off them.
     *
     * Generated material is removed with them. Leaving an exercise pointing at
     * a chapter that no longer exists would leave a quiz that renders with no
     * context and cannot be reached from any course page.
     */
    protected function purgePlaceholders(int $courseId): void
    {
        $chapterIds = DB::table('course_chapters')
            ->where('course_id', $courseId)
            ->where('placeholder', 1)
            ->pluck('id')
            ->all();

        if ($chapterIds === []) {
            return;
        }

        $assessmentIds = DB::table('assessments')
            ->whereIn('chapter_id', $chapterIds)
            ->pluck('id')
            ->all();

        if ($assessmentIds !== []) {
            DB::table('assessment_questions')->whereIn('assessment_id', $assessmentIds)->delete();
            DB::table('assessments')->whereIn('id', $assessmentIds)->delete();
        }

        DB::table('exercises')->whereIn('chapter_id', $chapterIds)->delete();
        DB::table('chapter_flashcards')->whereIn('chapter_id', $chapterIds)->delete();
        DB::table('course_chapters')->whereIn('id', $chapterIds)->delete();
    }
}