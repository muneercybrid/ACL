<?php

namespace App\Console\Commands;

use App\Services\ACLi\CourseContentGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Generates course chapters and lesson text with ACLi, from the NUC CCMAS
 * content for each course.
 *
 * Everything is written as DRAFT. Nothing is published: AGENTS.md section 7
 * requires human review between generation and publication, and this command
 * deliberately implements only the first step.
 */
class GenerateCourseContent extends Command
{
    protected $signature = 'acl:acli:generate-content
        {--apply : Write chapters. Without it, only report.}
        {--course= : A single course code, e.g. ACC101}
        {--limit=5 : Maximum courses to process}
        {--chapters=6 : Chapters per course}
        {--offset=0 : Skip this many courses, for running in slices}';

    protected $description = 'Generate course chapters and lesson text from CCMAS using ACLi';

    public function handle(CourseContentGenerator $generator): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(1, (int) $this->option('limit'));
        $chapters = max(1, (int) $this->option('chapters'));
        $offset = max(0, (int) $this->option('offset'));

        $this->line($apply
            ? '<fg=green>APPLYING</> — chapters will be written as draft'
            : '<fg=yellow>DRY RUN</> — pass --apply to write');

        $query = DB::table('courses')->orderBy('id');

        if ($this->option('course')) {
            $query->where('code', strtoupper((string) $this->option('course')));
        } else {
            $query->skip($offset)->take($limit);
        }

        $courses = $query->get();

        if ($courses->isEmpty()) {
            $this->error('no courses matched');

            return self::FAILURE;
        }

        $this->line(sprintf('Processing %d course(s).', $courses->count()));

        $total = 0;
        $failed = 0;
        $start = microtime(true);

        foreach ($courses as $course) {
            $existing = DB::table('course_chapters')
                ->where('course_id', $course->id)
                ->where('status', 'draft')
                ->count();

            $result = $generator->generateForCourse($course->id, $chapters, $apply);

            $total += $result['generated'];
            $failed += count($result['errors']);

            $this->line(sprintf(
                '  %-8s %-45s +%d (skipped %d, %d err)%s',
                $course->code,
                mb_strimwidth((string) $course->title, 0, 45, '…'),
                $result['generated'],
                $result['skipped'],
                count($result['errors']),
                $existing > 0 ? ' [resumes existing ' . $existing . ' draft(s)]' : ''
            ));

            foreach (array_slice($result['errors'], 0, 1) as $error) {
                $this->warn('      ' . $error);
            }
        }

        $this->newLine();
        $this->info('chapters generated : ' . $total);
        $this->info('errors             : ' . $failed);
        $this->info('elapsed            : ' . round(microtime(true) - $start) . 's');
        $this->info('status             : draft (nothing is published)');

        return $failed > 0 && $total === 0 ? self::FAILURE : self::SUCCESS;
    }
}
