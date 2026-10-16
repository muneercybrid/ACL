<?php

namespace App\Http\Controllers;

use App\Models\Curriculum\CurriculumCourse;
use App\Models\ProgrammeLevelCourse;
use App\Services\Courses\ProgrammeCourseResolver;
use App\Services\StudentDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StudentDashboardController extends Controller
{
    public function __construct(protected StudentDashboardService $dashboard,
        protected ProgrammeCourseResolver $courseResolver,
    ) {}

    /**
     * Student dashboard home - categorized profile sections with a
     * "My Courses" section showing enrolled courses and programme
     * curriculum courses.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $student = $user?->student;

        $enrollments = collect();
        $programmeCourses = collect();
        $enrolledOfferingIds = [];

        if ($student) {
            $enrollments = $this->dashboard->activeEnrollments($user);
            $programmeCourses = $this->courseResolver->forStudent($student);
            $enrolledOfferingIds = $this->dashboard->enrolledCourseIds($user);
        } else {
            $enrollments = $this->dashboard->activeEnrollments($user);
        }

        return view('student.dashboard', [
            'user' => $user,
            'student' => $student,
            'verification' => $student ? $this->dashboard->latestVerification($student) : null,
            'academicProgramme' => $student ? $this->dashboard->academicProgramme($student) : null,
            'institutionRecord' => $student ? $this->dashboard->institutionRecord($student) : null,
            'enrollments' => $enrollments,
            'programmeCourses' => $programmeCourses,
            'programmeCoursesBySemester' => $student ? $this->courseResolver->bySemester($student) : collect(),
            'enrolledOfferingIds' => $enrolledOfferingIds,
            'dashboardCounts' => $this->dashboardCounts($programmeCourses, $enrolledOfferingIds),
        ]);
    }

    /**
     * The figures the dashboard cards show.
     *
     * These are counted from the courses the student actually has, rather than
     * written into the template. The "Courses Active" card used to hold a
     * literal 13, which stayed 13 for a student with no courses at all and for
     * one with forty -- a number on screen that meant nothing and could not be
     * wrong in any way the page would admit to.
     */
    private function dashboardCounts($programmeCourses, array $enrolledOfferingIds): array
    {
        $enrolled = $programmeCourses->filter(
            fn ($entry) => $entry->current_offering
                && in_array($entry->current_offering->id, $enrolledOfferingIds)
        );

        return [
            'available' => $programmeCourses->count(),
            'enrolled' => $enrolled->count(),
            'institution' => $programmeCourses->filter(fn ($e) => ! $e->isNational())->count(),
            'national' => $programmeCourses->filter(fn ($e) => $e->isNational())->count(),
        ];
    }

    /**
     * Dedicated profile page with categorized sections.
     */
    public function profile(Request $request): View
    {
        $user = Auth::user();
        $student = $user?->student;

        return view('student.profile', [
            'user' => $user,
            'student' => $student,
            'verification' => $student ? $this->dashboard->latestVerification($student) : null,
            'academicProgramme' => $student ? $this->dashboard->academicProgramme($student) : null,
            'institutionRecord' => $student ? $this->dashboard->institutionRecord($student) : null,
            'enrollments' => $this->dashboard->activeEnrollments($user),
        ]);
    }

    /**
     * My Courses page - programme curriculum split into enrolled and
     * available courses. Enrolled courses (ground truth) are shown even
     * when the programme curriculum is not yet mapped.
     */
    public function myCourses(Request $request): View
    {
        $user = Auth::user();
        $student = $user?->student;

        $enrollments = $this->dashboard->activeEnrollments($user);
        $programmeCourses = collect();
        $enrolledOfferingIds = [];

        if ($student) {
            $programmeCourses = $this->courseResolver->forStudent($student);
            $enrolledOfferingIds = $this->dashboard->enrolledCourseIds($user);
        }

        $activeEnrollments = $programmeCourses->filter(
            fn ($cc) => $cc->current_offering && in_array($cc->current_offering->id, $enrolledOfferingIds)
        );
        $availableCourses = $programmeCourses->reject(
            fn ($cc) => $cc->current_offering && in_array($cc->current_offering->id, $enrolledOfferingIds)
        );

        return view('student.my-courses', [
            'user' => $user,
            'student' => $student,
            'enrollments' => $enrollments,
            'academicProgramme' => $student ? $this->dashboard->academicProgramme($student) : null,
            'programmeCourses' => $programmeCourses,
            'programmeCoursesBySemester' => $student ? $this->courseResolver->bySemester($student) : collect(),
            'activeEnrollments' => $activeEnrollments,
            'availableCourses' => $availableCourses,
            'enrolledOfferingIds' => $enrolledOfferingIds,
        ]);
    }

    /**
     * Single course detail with chapters and outline. The course must be
     * part of the student's own programme (server-side scope check).
     */
    public function showCourse(Request $request, string $ref): View
    {
        $user = Auth::user();
        $student = $user?->student;

        if (! $student) {
            abort(403, 'This course is only available to registered students.');
        }

        // Courses reach this page from two layers whose id sequences are
        // unrelated: curriculum_courses holds the national CCMAS placements,
        // programme_level_courses holds what a school added for itself. The
        // link carries an explicit prefix -- "c12" or "p34" -- so a placement
        // and an added course can never be confused for one another, and a
        // crafted id cannot walk from one table into the other.
        $kind = substr($ref, 0, 1);
        $id = (int) substr($ref, 1);

        if ($id < 1 || ! in_array($kind, ['c', 'p'], true)) {
            abort(404);
        }

        if ($kind === 'p') {
            return $this->showInstitutionCourse($student, $id, $user, $ref);
        }

        $curriculumCourse = CurriculumCourse::with([
            'course.chapters',
            'course.outlines',
            'curriculumVersion.programme',
        ])->findOrFail($id);

        // Server-side scope: the course version must belong to the
        // student's curriculum programme.
        $programme = $this->dashboard->curriculumProgramme($student);
        $inProgramme = $programme
            && $curriculumCourse->curriculumVersion->programme_id === $programme->id;

        // A shared course has one curriculum_course row per programme, so the
        // same course id carries a different ref depending on who opens it. A
        // ref belonging to another programme must not be honoured on its own --
        // but a student who is genuinely enrolled in the course has already
        // been granted access, and rejecting them on a ref they cannot control
        // left the page blank. Enrollment is the entitlement; the ref is only
        // how they arrived.
        $enrolledHere = DB::table('enrollments')
            ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.course_offering_id')
            ->where('enrollments.user_id', $request->user()->id)
            ->where('enrollments.status', 'active')
            ->where('course_offerings.course_id', $curriculumCourse->course_id)
            ->exists();

        if (! $inProgramme && ! $enrolledHere) {
            abort(403, 'This course is not part of your programme.');
        }

        // First active offering (for semester/session context).
        $offering = $curriculumCourse->course->offerings()
            ->where('is_active', true)
            ->with(['semester', 'semester.academicSession'])
            ->first();

        $enrollment = $offering
            ? $user->enrollments()
                ->where('course_offering_id', $offering->id)
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->first()
            : null;

        return view('student.course-detail', [
            'user' => $user,
            'student' => $student,
            'curriculumCourse' => $curriculumCourse,
            'currentOffering' => $offering,
            'enrollment' => $enrollment,
            'courseRef' => $ref,
        ]);
    }

    /**
     * Enrolls the student in a course they opened.
     *
     * The same scope checks as showCourse apply and are not bypassed: the
     * route alone must not let a student enroll in a course belonging to
     * another institution, or at a level they are not sitting. The course is
     * re-resolved through exactly the same ownership check the GET used, and
     * anything else is a 403.
     */
    public function enrollInCourse(Request $request, string $ref)
    {
        $student = $request->user()?->student;

        if (! $student) {
            abort(403, 'This course is only available to registered students.');
        }

        $courseId = $this->resolveCourseIdForStudent($ref, $student);

        $result = app(\App\Services\Courses\CourseEnrollmentService::class)
            ->enroll($request->user()->id, $courseId);

        return back()->with($result['enrollment'] ? 'status' : 'error', $result['message']);
    }

    /**
     * The full text of one chapter, on request.
     *
     * Held back from the course page so that page carries only the short
     * previews. Serving it here keeps the access rules identical to the page it
     * came from: the student must be enrolled in the course that owns the
     * chapter, so this cannot become a way to read any chapter by guessing an id.
     */
    public function chapterContent(Request $request, int $chapterId)
    {
        $student = $request->user()?->student;

        if (! $student) {
            abort(403, 'This course is only available to registered students.');
        }

        $chapter = DB::table('course_chapters')->where('id', $chapterId)->first();

        if ($chapter === null) {
            abort(404);
        }

        $enrolled = DB::table('enrollments')
            ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.course_offering_id')
            ->where('enrollments.user_id', $request->user()->id)
            ->where('enrollments.status', 'active')
            ->where('course_offerings.course_id', $chapter->course_id)
            ->exists();

        if (! $enrolled) {
            abort(403, 'Enroll in this course before opening its chapters.');
        }

        // A generated chapter may have a real title but no written body yet
        // (the pipeline writes titles first, content in Phase 2). The student
        // must still be able to open, navigate, and assess the chapter.
        $intro = (string) ($chapter->introduction ?? '');
        $summary = (string) ($chapter->summary ?? '');
        $keyTakeaways = (string) ($chapter->key_takeaways ?? '');

        if ($intro === '' && $summary === '' && $keyTakeaways === '') {
            // No written explanation yet — provide the chapter name and a
            // brief note so the student can study from the title and see
            // that the chapter exists, rather than a broken experience.
            $intro = $chapter->title . ". This chapter covers the concepts outlined in the course plan. Detailed content will be available as it is developed.";
        }

        return response()->json([
            'introduction' => $intro,
            'summary' => $summary,
            'key_takeaways' => $keyTakeaways,
            'chapter_title' => $chapter->title,
            'chapter_id' => $chapter->id,
        ]);
    }

    /**
     * A student's own assessment for a chapter, generated on first request.
     *
     * The chapter must belong to a course the student is actually enrolled
     * in. Without that check a student could request an assessment for any
     * chapter id, including another institution's material.
     */
    public function chapterAssessment(Request $request, int $chapterId)
    {
        $student = $request->user()?->student;

        if (! $student) {
            abort(403, 'This course is only available to registered students.');
        }

        $chapter = DB::table('course_chapters')->where('id', $chapterId)->first();

        if ($chapter === null) {
            abort(404);
        }

        $enrolledCourseIds = DB::table('enrollments')
            ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.course_offering_id')
            ->where('enrollments.user_id', $request->user()->id)
            ->where('enrollments.status', 'active')
            ->pluck('course_offerings.course_id');

        if (! $enrolledCourseIds->contains($chapter->course_id)) {
            abort(403, 'Enroll in this course before opening its assessment.');
        }

        $result = app(\App\Services\Courses\OnDemandAssessmentService::class)
            ->forStudent($student->id, $chapterId);

        return view('student.chapter-assessment', [
            'student' => $student,
            'chapter' => $chapter,
            'questions' => $result['questions'],
            'reused' => $result['reused'],
        ]);
    }

    /**
     * Resolves a course ref to a course id for this student, applying the same
     * scope rules the course page applies.
     *
     * Enrollment is a POST and carries its own route, so a route-scoped check
     * is not enough on its own: a student could otherwise post to the enroll
     * endpoint with an id belonging to another institution or to a level they
     * are not sitting. Both layers are re-checked here exactly as showCourse
     * checks them.
     */
    private function resolveCourseIdForStudent(string $ref, $student): int
    {
        $kind = substr($ref, 0, 1);
        $id = (int) substr($ref, 1);

        if ($id < 1 || ! in_array($kind, ['c', 'p'], true)) {
            abort(404);
        }

        if ($kind === 'c') {
            $curriculumCourse = CurriculumCourse::with('curriculumVersion')->findOrFail($id);
            $programme = $this->dashboard->curriculumProgramme($student);

            if (! $programme
                || $curriculumCourse->curriculumVersion->programme_id !== $programme->id) {
                abort(403, 'This course is not part of your programme.');
            }

            return (int) $curriculumCourse->course_id;
        }

        $levelCourse = ProgrammeLevelCourse::with('academicProgram')->findOrFail($id);
        $programme = $this->dashboard->academicProgramme($student);

        if (! $programme
            || (int) $levelCourse->academic_program_id !== (int) $programme->id
            || (int) $levelCourse->level !== (int) ($student->level ?? 100)) {
            abort(403, 'This course is not part of your programme.');
        }

        return (int) $levelCourse->course_id;
    }

    /**
     * A course the student's own school added, shown through the same detail
     * page as a national one.
     *
     * The scope check is deliberately stricter than the national branch: the
     * row must belong to the exact programme the student is enrolled in and the
     * level they are studying. Anything else is another school's course, or a
     * level they are not sitting, and neither may be reachable by guessing an
     * id.
     */
    private function showInstitutionCourse($student, int $programmeLevelCourseId, $user, string $ref): View
    {
        $programme = $this->dashboard->academicProgramme($student);

        if ($programme === null) {
            abort(403, 'This course is not part of your programme.');
        }

        $level = $student->level ? (int) $student->level : 100;

        $plc = ProgrammeLevelCourse::with([
            'course.outlines',
        ])
            ->where('academic_program_id', $programme->id)
            ->where('level', $level)
            ->findOrFail($programmeLevelCourseId);

        // The shared course carries the content; the school row carries the
        // placement. Build a CurriculumCourse-shaped view model so the detail
        // template stays layer-agnostic.
        $placement = new CurriculumCourse;
        $placement->id = $plc->id;
        $placement->course_id = $plc->course_id;
        $placement->level = $plc->level;
        $placement->semester = $plc->semester;
        $placement->credit_units = $plc->credit_units;
        $placement->course_type = $plc->course_type;
        $placement->setRelation('course', $plc->course);

        $offering = $plc->course->offerings()
            ->where('is_active', true)
            ->with(['semester', 'semester.academicSession'])
            ->first();

        $enrollment = $offering
            ? $user->enrollments()
                ->where('course_offering_id', $offering->id)
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->first()
            : null;

        return view('student.course-detail', [
            'user' => $user,
            'student' => $student,
            'curriculumCourse' => $placement,
            'currentOffering' => $offering,
            'enrollment' => $enrollment,
            'courseRef' => $ref,
        ]);
    }
}