<?php

namespace App\Console\Commands;

use App\Services\ChapterScaffolder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scaffolds chapter placeholders across the whole course catalogue.
 *
 * Every course needs enough chapters to be taught from first principles
 * through to its end goal, which is dozens per course and therefore tens of
 * thousands in total. Writing those with an AI call each would take days and
 * cost a great deal for material that is mostly structure.
 *
 * So the structure is created empty and the content is filled in on demand: a
 * coordinator opens a chapter and generates it with ACLi, pastes it, or
 * uploads a resource. All three paths already work.
 *
 * Placeholders are written as drafts with no body. Nothing here publishes, and
 * a placeholder is visibly a placeholder rather than passing as finished
 * material.
 */
class ScaffoldChapterPlaceholders extends Command
{
    protected $signature = 'acl:scaffold:chapters
        {--apply : Write placeholders. Without it, only report.}
        {--chapters=24 : Chapters per course}
        {--limit=0 : Maximum courses; 0 means all}
        {--offset=0 : Skip this many courses, for running in slices}
        {--chunk=50 : Courses per batch}';

    protected $description = 'Scaffold empty chapter placeholders across the course catalogue';

    public function handle(ChapterScaffolder $scaffolder): int
    {
        $apply = (bool) $this->option('apply');
        $perCourse = max(1, (int) $this->option('chapters'));
        $limit = max(0, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));
        $chunk = max(1, (int) $this->option('chunk'));

        $this->line($apply
            ? '<fg=green>APPLYING</> -- placeholders will be created'
            : '<fg=yellow>DRY RUN</> -- pass --apply to write');

        $total = DB::table('courses')->count();
        $existing = (int) DB::table('course_chapters')->where('placeholder', true)->count();

        $this->table(
            ['courses', 'chapters per course', 'placeholders to create', 'already scaffolded'],
            [[
                (string) $total,
                (string) $perCourse,
                (string) (($total * $perCourse) - $existing),
                (string) $existing,
            ]]
        );

        if (! $apply) {
            return self::SUCCESS;
        }

        $result = $scaffolder->scaffold($perCourse, $limit, $offset, $chunk);

        $this->newLine();
        $this->info('courses scaffolded : ' . $result['courses']);
        $this->info('chapters created   : ' . $result['created']);
        $this->info('already present    : ' . $result['skipped']);
        $this->info('status             : draft, no content (nothing is published)');

        return self::SUCCESS;
    }
}
