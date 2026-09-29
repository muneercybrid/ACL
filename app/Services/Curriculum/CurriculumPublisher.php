<?php

namespace App\Services\Curriculum;

use App\Models\AcademicProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The coordinator's side of course registration: publishing which courses a
 * programme is running, at which level, in which semester.
 *
 * This is the foundation the student flow rests on. A student can only select
 * from a published list, and can skip selection entirely when a list already
 * exists, so until a coordinator publishes, the student-facing screens have
 * nothing real to show.
 *
 * A curriculum version is the unit. It belongs to one offering (a university's
 * version of a programme) and one academic session, and its rows are
 * `curriculum_courses` carrying level, semester, course_type, credit_units and
 * whether the course is mandatory. That is what makes the flow standardised:
 * every university publishes the same shape of record, so the student screen
 * is one implementation rather than one per institution.
 *
 * Publishing is append-only by version. Re-publishing a session creates the
 * next version rather than overwriting, so a student who registered last term
 * still maps to the list that was in force when they registered. Changing a
 * published list must never silently move a student.
 */
class CurriculumPublisher
{
    public const SEMESTERS = [1, 2];

    /**
     * Creates (or reuses) the academic session a list is published into.
     */
    public function ensureSession(string $name, string $startsOn, string $endsOn): int
    {
        $existing = DB::table('academic_sessions')->where('name', $name)->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return DB::table('academic_sessions')->insertGetId([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'start_date' => $startsOn,
            'end_date' => $endsOn,
            'is_current' => true,
            'is_active' => true,
            'status' => 'active',
            'start_year' => (int) substr($name, 0, 4),
            'end_year' => (int) substr($name, 5, 4),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Publishes a list of courses for one offering and one session.
     *
     * @param  array<int, array{course_id: int, level: int, semester: int, course_type?: string, credit_units?: int, is_mandatory?: bool}>  $courses
     * @return array{version_id: int, version: int, published: int}
     */
    public function publish(
        AcademicProgram $offering,
        int $sessionId,
        array $courses,
        ?string $label = null,
    ): array {
        if ($courses === []) {
            throw new \InvalidArgumentException('Refusing to publish an empty course list.');
        }

        foreach ($courses as $course) {
            if (! in_array((int) $course['semester'], self::SEMESTERS, true)) {
                throw new \InvalidArgumentException(
                    "Semester must be 1 or 2; got '{$course['semester']}'. "
                    .'The student flow runs first semester then second, so an unnumbered '
                    .'semester would break that ordering.'
                );
            }
        }

        return DB::transaction(function () use ($offering, $sessionId, $courses, $label) {
            // curriculum_versions has no numeric `version` column, so the
            // sequence is derived from the rows already present rather than
            // read off a column that does not exist.
            $version = DB::table('curriculum_versions')
                ->where('programme_id', $offering->id)
                ->where('academic_session_id', $sessionId)
                ->count() + 1;

            $versionId = DB::table('curriculum_versions')->insertGetId([
                'programme_id' => $offering->id,
                'academic_session_id' => $sessionId,
                'version_label' => $label ?? ('Version ' . $version),
                'slug' => \Illuminate\Support\Str::slug(($offering->slug ?: 'programme').'-s'.$sessionId.'-v'.$version),
                'scope' => 'national',
                'verification_status' => 'verified',
                'source_type' => 'coordinator_published',
                'is_active' => true,
                'effective_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $rows = [];

            foreach ($courses as $course) {
                $rows[] = [
                    'curriculum_version_id' => $versionId,
                    'course_id' => $course['course_id'],
                    'level' => (int) $course['level'],
                    'semester' => (int) $course['semester'],
                    'course_type' => $course['course_type'] ?? 'core',
                    'credit_units' => (int) ($course['credit_units'] ?? 3),
                    'is_mandatory' => $course['is_mandatory'] ?? true,
                    'status' => 'active',
                    'delivery_mode' => 'blended',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 100) as $batch) {
                DB::table('curriculum_courses')->insert($batch);
            }

            return ['version_id' => $versionId, 'version' => $version, 'published' => count($rows)];
        });
    }

    /**
     * Whether a list already exists for this offering and level.
     *
     * This is the flag the student flow reads to decide whether to skip
     * selection entirely. It is deliberately scoped to the level: a level 100
     * list being published does not mean a level 200 student has one.
     */
    public function hasPublishedList(AcademicProgram $offering, int $level, ?int $sessionId = null): bool
    {
        $query = DB::table('curriculum_courses as cc')
            ->join('curriculum_versions as cv', 'cv.id', '=', 'cc.curriculum_version_id')
            ->where('cv.programme_id', $offering->id)
            ->where('cv.is_active', true)
            ->where('cc.level', $level)
            ->where('cc.status', 'active');

        if ($sessionId !== null) {
            $query->where('cv.academic_session_id', $sessionId);
        }

        return $query->exists();
    }

    /**
     * The published list for a student to select from.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function listFor(AcademicProgram $offering, int $level, ?int $sessionId = null)
    {
        $query = DB::table('curriculum_courses as cc')
            ->join('curriculum_versions as cv', 'cv.id', '=', 'cc.curriculum_version_id')
            ->join('courses as c', 'c.id', '=', 'cc.course_id')
            ->where('cv.programme_id', $offering->id)
            ->where('cv.is_active', true)
            ->where('cc.level', $level)
            ->where('cc.status', 'active')
            ->select(
                'cc.id as curriculum_course_id',
                'cc.course_id',
                'cc.semester',
                'cc.course_type',
                'cc.credit_units',
                'cc.is_mandatory',
                'c.code',
                'c.title'
            )
            ->orderBy('cc.semester')
            ->orderBy('c.code');

        if ($sessionId !== null) {
            $query->where('cv.academic_session_id', $sessionId);
        }

        return $query->get();
    }
}
