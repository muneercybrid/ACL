<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\View\View;

class CourseRegistrationController extends Controller
{
    public function index(Request $request): View
    {
        // The student is resolved from the authenticated session, never from a
        // route parameter — otherwise one student could read another's
        // programme, institution and course list by changing an ID.
        $student = $request->user()?->student;

        if (! $student) {
            throw new AccessDeniedHttpException('A student record is required for this page.');
        }

        $service = app(\App\Services\StudentDashboardService::class);
        $programme = $service->curriculumProgramme($student);
        $level = $student->level ?? 100;
        $institution = $service->institutionRecord($student);

        // Semester 1 / Semester 2 tabs — only valid programme-bound courses
        $s1 = $service->programmeCourses($student)->filter(fn ($c) => $c->semester === 1 || $c->semester === 'Semester 1')->take(20);
        $s2 = $service->programmeCourses($student)->filter(fn ($c) => $c->semester === 2 || $c->semester === 'Semester 2')->take(20);

        return view('student.course-registration', [
            'student' => $student,
            'programme' => $programme,
            'institution' => $institution,
            'semester1' => $s1,
            'semester2' => $s2,
        ]);
    }
}
