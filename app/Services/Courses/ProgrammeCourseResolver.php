<?php

namespace App\Services\Courses;

use App\Models\Curriculum\CurriculumCourse;
use App\Models\ProgrammeLevelCourse;
use App\Models\Student;
use App\Services\StudentDashboardService;
use Illuminate\Support\Collection;

/**
 * Presents a student's programme courses as one list drawn from two layers.
 *
 * The owner set the split. A course on the CCMAS document is the same course for
 * every programme that carries it and every organization, so it is published once
 * nationally and reaches every student. Anything a school adds on top is that
 * school's alone -- isolated to its organization, and to the level it was added
 * for -- so it lives in programme_level_courses instead.
 *
 * Both layers have to reach the same "My Courses" screen, and they are keyed by
 * different tables with unrelated id sequences. Merging them naively would hand
 * a curriculum_courses id and a programme_level_courses id to the same link and
 * resolve the wrong course, or another school's course. So every entry carries
 * an explicit route_ref -- "c12" for a curriculum placement, "p34" for a
 * programme-level one -- and links are built from that rather than from a bare
 * id. The two id spaces stay apart instead of relying on them never colliding.
 *
 * Deduplication matters here too. When a school has also recorded a CCMAS
 * course in its own layer, the school row wins, because it carries that school's
 * semester placement and units; the national row is dropped rather than showing
 * the same course twice in one semester.
 */
class ProgrammeCourseResolver
{
    public function __construct(protected StudentDashboardService $dashboard) {}

    /**
     * One entry per course for the student's programme and level, both layers.
     */
    public function forStudent(Student $student): Collection
    {
        $national = $this->dashboard->programmeCourses($student)
            ->map(fn (CurriculumCourse $cc) => ProgrammeCourseEntry::fromCurriculumCourse($cc));

        $institution = $this->institutionCourses($student)
            ->map(fn (ProgrammeLevelCourse $plc) => ProgrammeCourseEntry::fromProgrammeLevelCourse($plc));

        // When a coordinator completes a programme, the school layer records the
        // whole list it runs -- the CCMAS courses and the ones it added. Only
        // the second kind is an addition.
        //
        // A school row sourced from CCMAS is a restatement of a course that is
        // already published nationally, so the national entry is kept and the
        // duplicate dropped. Letting it win would quietly demote a shared
        // course into a school-private one and hand that school the last word on
        // its units -- STA111 is 2 units nationally and 3 on one school's CRF,
        // and the national figure is the correct one. A school row sourced from
        // the institution is a genuinely added course, and that is the one that
        // survives.
        $nationalCodes = $national->map(fn ($e) => (string) $e->code)->flip()->all();

        // A school row is only ever displaced by a national one that actually
        // exists. MTH103 is the case that makes this necessary: NWU runs it at
        // Level 100, it is marked as coming from CCMAS, and no national
        // curriculum publishes it, so there is nothing to defer to. Treating
        // "sourced from CCMAS" as "therefore national" dropped a course the
        // school genuinely teaches.
        //
        // So the school row wins when it is an added course, or when the
        // national layer has nothing under that code. It loses only to an
        // existing national entry, which then supplies the semester and units.
        $wins = $institution
            ->filter(fn (ProgrammeCourseEntry $e) => $e->source === 'institution'
                || ! array_key_exists((string) $e->code, $nationalCodes))
            ->keyBy(fn ($e) => (string) $e->code);

        $merged = $national
            ->reject(fn (ProgrammeCourseEntry $e) => $wins->has((string) $e->code))
            ->concat($wins->values())
            ->sortBy([
                fn ($a, $b) => $a->level <=> $b->level,
                fn ($a, $b) => $a->semester <=> $b->semester,
                fn ($a, $b) => strnatcasecmp((string) $a->code, (string) $b->code),
            ])
            ->values();

        return $this->attachOfferings($merged);
    }
    /**
     * Grouped by level then semester, matching the dashboard's existing shape.
     */
    public function bySemester(Student $student): Collection
    {
        return $this->forStudent($student)
            ->groupBy('level')
            ->map(fn (Collection $level) => $level->groupBy('semester'));
    }

    /**
     * Courses this school's own layer adds for the student's programme and level.
     *
     * Scoped to the student's own programme, which academicProgramme() only ever
     * returns from the organization the student is actually attached to. The
     * level is matched too, so a course a school added for Level 400 does not
     * appear on a Level 100 student's list.
     */
    protected function institutionCourses(Student $student): Collection
    {
        $programme = $this->dashboard->academicProgramme($student);

        if ($programme === null) {
            return collect();
        }

        return ProgrammeLevelCourse::query()
            ->with('course')
            ->where('academic_program_id', $programme->id)
            ->where('level', $this->levelFor($student))
            ->get();
    }

    /**
     * The level the student is studying.
     */
    public function levelFor(Student $student): int
    {
        $level = $student->level;

        return $level ? (int) $level : 100;
    }

    /**
     * Give every entry its current offering.
     *
     * National entries already carry one from the dashboard service; institution
     * entries need one looked up against the same shared course, so an added
     * course with published content is reachable in exactly the same way.
     */
    protected function attachOfferings(Collection $entries): Collection
    {
        $courseIds = $entries->pluck('course')
            ->filter()
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        if ($courseIds->isEmpty()) {
            return $entries->each(fn ($e) => $e->current_offering = null);
        }

        $offerings = \App\Models\CourseOffering::whereIn('course_id', $courseIds)
            ->where('is_active', true)
            ->with(['semester', 'semester.academicSession'])
            ->get()
            ->groupBy('course_id')
            ->map(fn ($rows) => $rows->sortByDesc(fn ($o) => $o->semester?->academicSession?->year ?? 0)->first());

        return $entries->map(function (ProgrammeCourseEntry $entry) use ($offerings) {
            if ($entry->current_offering !== null) {
                return $entry;
            }

            $entry->current_offering = $entry->course?->id
                ? $offerings->get($entry->course->id)
                : null;

            return $entry;
        });
    }
}
