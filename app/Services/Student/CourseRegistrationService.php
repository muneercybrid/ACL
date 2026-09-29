<?php

namespace App\Services\Student;

use App\Services\Curriculum\CurriculumPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The student-facing course registration flow.
 *
 * The owner's requirement, in order:
 *
 *   1. a student sees the courses for their programme and level
 *   2. they choose FIRST semester courses, then SECOND semester, in that
 *      order, so the two can never be mixed up
 *   3. if a coordinator or superadmin has already published the list for that
 *      programme and level, the student skips selection entirely
 *
 * The ordering is enforced here rather than in the view. A hidden next button
 * is a request to the client; a server that refuses semester 2 before
 * semester 1 is a rule. Both exist, but only one of them holds when someone
 * posts the form directly.
 */
class CourseRegistrationService
{
    public function __construct(private readonly CurriculumPublisher $publisher) {}

    /**
     * Where this student stands in the flow.
     *
     * @return array{
     *   stage: string,
     *   programme: object|null,
     *   level: int,
     *   has_published_list: bool,
     *   semester1_done: bool,
     *   semester2_done: bool
     * }
     */
    public function status(object $student): array
    {
        $programme = $this->offeringFor($student);
        $level = (int) ($student->level ?? 100);

        if (! $programme) {
            return [
                'stage' => 'unassigned',
                'programme' => null,
                'level' => $level,
                'has_published_list' => false,
                'semester1_done' => false,
                'semester2_done' => false,
            ];
        }

        $hasPublished = $this->publisher->hasPublishedList($programme, $level, $this->sessionId());
        $sessionId = $this->sessionId();

        $s1 = $this->countRegistered($student, $sessionId, 1);
        $s2 = $this->countRegistered($student, $sessionId, 2);

        $stage = match (true) {
            $hasPublished && $s1 === 0 => 'auto_enroll',
            $s1 === 0 => 'select_semester_1',
            $s2 === 0 => 'select_semester_2',
            default => 'complete',
        };

        return [
            'stage' => $stage,
            'programme' => $programme,
            'level' => $level,
            'has_published_list' => $hasPublished,
            'semester1_done' => $s1 > 0,
            'semester2_done' => $s2 > 0,
        ];
    }

    /**
     * The courses a student may choose from for one semester.
     *
     * Two sources, in priority order:
     *
     *   1. the coordinator's published list for this programme and level
     *   2. the programme's own CCMAS course catalogue, when nothing has been
     *      published
     *
     * The second source is what makes the selection path reachable at all.
     * Without it, `hasPublishedList` is false precisely when there is nothing
     * to select, so a student would see an empty screen and a flow that can
     * only ever auto-enrol. The catalogue exists precisely so the student has
     * a sane default to choose from until a coordinator curates it.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function coursesForSemester(object $student, int $semester)
    {
        $programme = $this->offeringFor($student);

        if (! $programme) {
            return collect();
        }

        $level = (int) ($student->level ?? 100);

        $published = $this->publisher
            ->listFor($programme, $level, $this->sessionId())
            ->where('semester', $semester)
            ->values();

        if ($published->isNotEmpty()) {
            return $published;
        }

        return $this->catalogueCoursesFor($programme, $level, $semester);
    }

    /**
     * The programme's CCMAS courses for a level and semester.
     *
     * Mandatory is deliberately false here. Nothing in CCMAS says a course is
     * compulsory for a particular student, and failing a student for omitting
     * a course the system guessed at would be worse than letting them choose.
     * Once a coordinator publishes a list, mandatory becomes meaningful and is
     * enforced.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function catalogueCoursesFor(object $programme, int $level, int $semester)
    {
        $disciplineId = DB::table('programmes')
            ->where('id', $programme->nuc_programme_id)
            ->value('nuc_discipline_id');

        if (! $disciplineId) {
            return collect();
        }

        $levelDigit = intdiv($level, 100);

        if ($levelDigit < 1 || $levelDigit > 9) {
            return collect();
        }

        return DB::table('courses')
            ->where('nuc_discipline_id', $disciplineId)
            // The hundreds digit of a NUC course code is the level it is
            // taught at; the final digit alternates 1,2 across semesters.
            ->whereRaw('UPPER(code) REGEXP ?', ['^[A-Z]{2,4}' . $levelDigit . '0?' . ($semester === 1 ? '1' : '2') . '$'])
            ->orderBy('code')
            ->get(['id', 'code', 'title'])
            ->map(fn ($course) => (object) [
                'curriculum_course_id' => null,
                'course_id' => $course->id,
                'semester' => $semester,
                'course_type' => 'core',
                'credit_units' => 3,
                'is_mandatory' => false,
                'code' => $course->code,
                'title' => $course->title,
            ]);
    }

    /**
     * Records a student's choice for one semester.
     *
     * Semester 2 is refused until semester 1 is recorded. That refusal is the
     * mechanism the owner's requirement actually needs: the order is enforced
     * by the server, not merely suggested by a disabled button.
     *
     * @param  array<int, int>  $courseIds
     * @return array{registered: int, credits: int}
     */
    /**
     * registration_source is a fixed enum, not free text:
     *   curriculum     the student chose from the programme catalogue
     *   crf            the coordinator published the list; the student is
     *                   registered from it rather than choosing
     *
     * Passing an arbitrary string here truncates the value into a warning
     * rather than an error under MySQL, which means a wrong source would be
     * written silently. Validating it up front keeps the audit trail honest.
     */
    private const SOURCES = ['curriculum', 'crf', 'manual', 'carry_over', 'elective'];

    public function registerSemester(object $student, int $semester, array $courseIds, string $source = 'curriculum'): array
    {
        if (! in_array($source, self::SOURCES, true)) {
            throw new \InvalidArgumentException(
                "registration_source must be one of: ".implode(', ', self::SOURCES)."; got '{$source}'."
            );
        }

        if (! in_array($semester, [1, 2], true)) {
            throw new \InvalidArgumentException('Semester must be 1 or 2.');
        }

        $sessionId = $this->sessionId();

        if ($semester === 2 && $this->countRegistered($student, $sessionId, 1) === 0) {
            throw new \LogicException('Select your first semester courses before your second semester.');
        }

        $programme = $this->offeringFor($student);

        if (! $programme) {
            throw new \LogicException('No programme is linked to this student.');
        }

        $level = (int) ($student->level ?? 100);
        $available = $this->coursesForSemester($student, $semester)->keyBy('course_id');

        $published = $this->publisher->listFor($programme, $level, $sessionId)
            ->where('semester', $semester)
            ->keyBy('course_id');

        $unknown = array_diff($courseIds, array_keys($available->all()));

        if ($unknown !== []) {
            throw new \InvalidArgumentException(
                'Some of those courses are not on the published list for this semester: '
                .implode(', ', $unknown)
            );
        }

        $mandatory = array_keys($available->where('is_mandatory', true)->all());
        $missing = array_diff($mandatory, $courseIds);

        if ($missing !== []) {
            throw new \InvalidArgumentException(
                'These courses are mandatory and must be selected: ' . implode(', ', $missing)
            );
        }

        $rows = [];
        $credits = 0;

        foreach ($courseIds as $courseId) {
            $entry = $available[$courseId] ?? null;

            if ($entry === null) {
                continue;
            }

            $credits += (int) $entry->credit_units;

            $rows[] = [
                'student_id' => $student->id,
                'course_id' => $courseId,
                'semester' => $semester,
                'semester_id' => $this->semesterIds($sessionId)[$semester],
                'academic_session_id' => $sessionId,
                'level' => $level,
                'credit_units' => (int) $entry->credit_units,
                'registration_source' => $source,
                'status' => 'registered',
                'registered_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Replace rather than accumulate: re-submitting a semester corrects
        // it, and a stale row from an earlier attempt would otherwise leave a
        // course registered that the student has since deselected.
        DB::transaction(function () use ($student, $sessionId, $semester, $rows) {
            DB::table('student_course_registrations')
                ->where('student_id', $student->id)
                ->where('academic_session_id', $sessionId)
                ->where('semester', $semester)
                ->delete();

            foreach (array_chunk($rows, 100) as $batch) {
                DB::table('student_course_registrations')->insert($batch);
            }
        });

        return ['registered' => count($rows), 'credits' => $credits];
    }

    /**
     * Enrols a student automatically when a list has been published.
     *
     * This is the "they don't need to do that at all" case. It enrols the
     * published list rather than nothing: an empty registration would leave
     * the student with a dashboard showing no courses, which is not the same
     * as being correctly registered.
     */
    public function autoEnrol(object $student): array
    {
        $programme = $this->offeringFor($student);

        if (! $programme) {
            return ['registered' => 0, 'credits' => 0];
        }

        $level = (int) ($student->level ?? 100);
        $total = ['registered' => 0, 'credits' => 0];

        foreach ([1, 2] as $semester) {
            $courses = $this->publisher->listFor($programme, $level, $this->sessionId())
                ->where('semester', $semester);

            if ($courses->isEmpty()) {
                continue;
            }

            $result = $this->registerSemester($student, $semester, $courses->pluck('course_id')->all(), 'crf');
            $total['registered'] += $result['registered'];
            $total['credits'] += $result['credits'];
        }

        return $total;
    }

    /**
     * The semester rows for the current session, created on first use.
     *
     * `student_course_registrations.semester_id` is NOT NULL with no default
     * and foreign-keys to `semesters`, which was empty. The denormalised
     * `semester` column added alongside it is what the flow branches on; this
     * satisfies the existing constraint rather than working around it.
     *
     * @return array<int, int> 1 => id, 2 => id
     */
    private function semesterIds(?int $sessionId): array
    {
        if ($sessionId === null) {
            return [1 => 0, 2 => 0];
        }

        $names = [1 => 'Semester 1', 2 => 'Semester 2'];
        $out = [];

        foreach ($names as $number => $name) {
            $id = DB::table('semesters')
                ->where('academic_session_id', $sessionId)
                ->where('name', $name)
                ->value('id');

            if (! $id) {
                $id = DB::table('semesters')->insertGetId([
                    'academic_session_id' => $sessionId,
                    'name' => $name,
                    'slug' => 'semester-' . $number,
                    'number' => $number,
                    'start_date' => now()->startOfYear()->addMonths($number === 1 ? 0 : 5)->toDateString(),
                    'end_date' => now()->startOfYear()->addMonths($number === 1 ? 5 : 11)->toDateString(),
                    'is_active' => true,
                    'is_current' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $out[$number] = (int) $id;
        }

        return $out;
    }

    private function countRegistered(object $student, ?int $sessionId, int $semester): int
    {
        $query = DB::table('student_course_registrations')
            ->where('student_id', $student->id)
            ->where('status', 'registered');

        if ($sessionId !== null) {
            $query->where('academic_session_id', $sessionId);
        }

        $query->where('semester', $semester);

        return (int) $query->count();
    }

    private function sessionId(): ?int
    {
        return DB::table('academic_sessions')->where('is_current', true)->orderBy('id')->value('id');
    }

    /**
     * The programme offering this student belongs to.
     *
     * `organization_memberships` carries `academic_program_id` directly, so
     * this is one indexed lookup. An earlier draft joined through
     * `curriculum_versions` on a non-existent `entity_id` column, which would
     * have thrown at runtime rather than returning nothing -- a loud failure,
     * but only once a student actually opened the page.
     */
    private function offeringFor(object $student)
    {
        $userId = $student->user_id ?? null;

        if (! $userId) {
            return null;
        }

        $programmeId = DB::table('organization_memberships')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNotNull('academic_program_id')
            ->orderBy('id')
            ->value('academic_program_id');

        return $programmeId ? \App\Models\AcademicProgram::find($programmeId) : null;
    }
}
