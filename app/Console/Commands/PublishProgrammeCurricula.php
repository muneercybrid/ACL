<?php

namespace App\Console\Commands;

use App\Models\AcademicProgram;
use App\Services\Curriculum\CurriculumPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Publishes a course list for every programme offering.
 *
 * The coordinator side has to exist before the student registration flow means
 * anything: a student selects from a published list, and skips selection
 * entirely when one exists. `curriculum_courses` was empty, so every student
 * would have faced an empty screen.
 *
 * Courses are assigned to a level and semester from the CCMAS course code,
 * which is where the information already lives:
 *
 *   ACC101 -> level 100, semester 1
 *   ACC201 -> level 200, semester 1
 *   GST111 -> level 100, semester 1
 *
 * The hundred digit of a NUC course code is the level it is taught at, and
 * the final digit is its position in the year, which alternates across
 * semesters. That is a convention in the codes, not an invention here, and it
 * is what makes the mapping reproducible rather than arbitrary.
 */
class PublishProgrammeCurricula extends Command
{
    protected $signature = 'acl:curriculum:publish
        {--apply : Write. Without it, only report.}
        {--session=2025/2026 : Academic session label}
        {--limit=0 : Maximum offerings; 0 means all}
        {--offset=0 : Skip this many, for running in slices}
        {--chunk=25 : Offerings per batch}';

    protected $description = 'Publish course lists per programme offering from CCMAS course codes';

    public function handle(CurriculumPublisher $publisher): int
    {
        $apply = (bool) $this->option('apply');
        $sessionLabel = (string) $this->option('session');
        $limit = max(0, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));
        $chunk = max(1, (int) $this->option('chunk'));

        $this->line($apply
            ? '<fg=green>APPLYING</> -- course lists will be published'
            : '<fg=yellow>DRY RUN</> -- pass --apply to write');

        $offerings = DB::table('academic_programs')->count();
        $disciplines = DB::table('courses')->whereNotNull('nuc_discipline_id')
            ->pluck('nuc_discipline_id')->flip();

        $this->table(
            ['offerings', 'courses', 'disciplines', 'session'],
            [[
                (string) $offerings,
                (string) DB::table('courses')->count(),
                (string) $disciplines->count(),
                $sessionLabel,
            ]]
        );

        if (! $apply) {
            return self::SUCCESS;
        }

        $sessionId = $publisher->ensureSession($sessionLabel, now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString());
        $this->line("Academic session id: {$sessionId}");

        $published = 0;
        $skipped = 0;
        $noCourses = 0;
        $lastId = 0;

        while (true) {
            $query = DB::table('academic_programs')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunk);

            if ($limit > 0) {
                $query->limit(min($chunk, $limit - $published));
            }

            $batch = $query->get();

            if ($batch->isEmpty()) {
                break;
            }

            foreach ($batch as $row) {
                $lastId = (int) $row->id;

                $offering = AcademicProgram::find($row->id);

                if (! $offering) {
                    continue;
                }

                if ($publisher->hasPublishedList($offering, 100, $sessionId)) {
                    $skipped++;
                    continue;
                }

                $courses = $this->coursesFor($offering, $sessionId);

                if ($courses === []) {
                    $noCourses++;
                    continue;
                }

                try {
                    $publisher->publish($offering, $sessionId, $courses);
                    $published++;
                } catch (\Throwable $e) {
                    $this->warn('  offering ' . $row->id . ': ' . $e->getMessage());
                }
            }

            if ($limit > 0 && $published >= $limit) {
                break;
            }
        }

        $this->newLine();
        $this->info('offerings published : ' . $published);
        $this->info('already published  : ' . $skipped);
        $this->info('no courses found   : ' . $noCourses);
        $this->info('curriculum_courses : ' . DB::table('curriculum_courses')->count());

        return self::SUCCESS;
    }

    /**
     * The courses for one offering, grouped by level and semester.
     *
     * @return array<int, array{course_id: int, level: int, semester: int}>
     */
    private function coursesFor(AcademicProgram $offering, int $sessionId): array
    {
        $catalogueId = $offering->nuc_programme_id;

        $courseIds = DB::table('curriculum_courses')
            ->join('curriculum_versions as cv', 'cv.id', '=', 'curriculum_courses.curriculum_version_id')
            ->where('cv.programme_id', $offering->id)
            ->pluck('course_id')
            ->all();

        $existing = $courseIds === [] ? [] : array_flip($courseIds);

        // The offering's own discipline, reached through its programme:
        //   offering -> nuc_programme_id -> programmes.nuc_discipline_id
        //
        // This has to be per-offering. Taking the first discipline found
        // anywhere in the courses table would hand every one of the 1,364
        // offerings the same Accounting course list, which is wrong in a way
        // that looks fine: the counts are plausible and no constraint fires.
        $disciplineId = DB::table('programmes')
            ->where('id', $offering->nuc_programme_id)
            ->value('nuc_discipline_id');

        if (! $disciplineId) {
            return [];
        }

        $rows = [];

        foreach ($this->coursesForDiscipline($disciplineId) as $course) {
            if (isset($existing[$course->id])) {
                continue;
            }

            $placement = $this->place($course->code);

            if ($placement === null) {
                continue;
            }

            $rows[$placement['level']][$placement['semester']][] = [
                'course_id' => $course->id,
                'level' => $placement['level'],
                'semester' => $placement['semester'],
                'course_type' => Str::startsWith((string) $course->code, 'GST') ? 'general' : 'core',
                'credit_units' => 3,
                'is_mandatory' => true,
            ];
        }

        $out = [];

        foreach ($rows as $level => $semesters) {
            foreach ($semesters as $semester => $courses) {
                foreach ($courses as $course) {
                    $out[] = $course;
                }
            }
        }

        return $out;
    }

    private function coursesForDiscipline(?int $disciplineId)
    {
        if (! $disciplineId) {
            return collect();
        }

        return DB::table('courses')
            ->where('nuc_discipline_id', $disciplineId)
            ->orderBy('code')
            ->get(['id', 'code', 'title']);
    }

    /**
     * A NUC course code carries its own level and semester:
     *
     *   ACC101  -> level 100, semester 1   (hundreds digit 1, last digit 1)
     *   ACC102  -> level 100, semester 2   (hundreds digit 1, last digit 2)
     *   ACC201  -> level 200, semester 1
     *
     * The last digit cycles 1,2 per year, so odd is first semester and even
     * is second. Anything that does not fit is skipped rather than guessed
     * at, because a course filed under the wrong semester is exactly the
     * mixup the two-step student flow exists to prevent.
     *
     * @return array{level: int, semester: int}|null
     */
    private function place(string $code): ?array
    {
        if (! preg_match('/^[A-Z]{2,4}(\d)0?(\d)$/', strtoupper($code), $m)) {
            return null;
        }

        $level = ((int) $m[1]) * 100;
        $semester = ((int) $m[2]) % 2 === 1 ? 1 : 2;

        return ['level' => $level, 'semester' => $semester];
    }
}
