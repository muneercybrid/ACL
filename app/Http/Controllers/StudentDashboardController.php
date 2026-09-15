<?php

namespace App\Http\Controllers;

use App\Models\Curriculum\CurriculumCourse;
use App\Services\StudentDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StudentDashboardController extends Controller
{
    public function __construct(protected StudentDashboardService $dashboard) {}

    /**
     * Student dashboard home - categorized profile sections with a
     * "My Courses" section showing enrolled courses and programme
     * curriculum courses.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $student = $user->student;

        $enrollments = collect();
        $programmeCourses = collect();
        $enrolledOfferingIds = [];

        if ($student) {
            $enrollments = $this->dashboard->activeEnrollments($user);
            $programmeCourses = $this->dashboard->programmeCourses($student);
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
            'enrolledOfferingIds' => $enrolledOfferingIds,
        ]);
    }

    /**
     * Dedicated profile page with categorized sections.
     */
    public function profile(Request $request): View
    {
        $user = Auth::user();
        $student = $user->student;

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
        $student = $user->student;

        $enrollments = $this->dashboard->activeEnrollments($user);
        $programmeCourses = collect();
        $enrolledOfferingIds = [];

        if ($student) {
            $programmeCourses = $this->dashboard->programmeCourses($student);
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
            'activeEnrollments' => $activeEnrollments,
            'availableCourses' => $availableCourses,
            'enrolledOfferingIds' => $enrolledOfferingIds,
        ]);
    }

    /**
     * Single course detail with chapters and outline. The course must be
     * part of the student's own programme (server-side scope check).
     */
    public function showCourse(Request $request, int $curriculumCourseId): View
    {
        $user = Auth::user();
        $student = $user->student;

        $curriculumCourse = CurriculumCourse::with([
            'course.chapters',
            'course.outlines',
            'curriculumVersion.programme',
        ])->findOrFail($curriculumCourseId);

        if (! $student) {
            abort(403, 'This course is only available to registered students.');
        }

        // Server-side scope: the course version must belong to the
        // student's curriculum programme.
        $programme = $this->dashboard->curriculumProgramme($student);
        if (! $programme
            || $curriculumCourse->curriculumVersion->programme_id !== $programme->id) {
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
        ]);
    }
}