<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\CourseRegistrationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The student's course registration flow.
 *
 * The order is enforced on the server, not suggested by the interface. A
 * hidden or disabled button is a request to the client; refusing semester 2
 * before semester 1 is a rule, and it still holds when the form is posted
 * directly.
 */
class CourseRegistrationController extends Controller
{
    public function __construct(private readonly CourseRegistrationService $registration) {}

    /**
     * Where the student currently stands, and the screen for that stage.
     */
    public function index(Request $request)
    {
        $student = $this->student($request);
        $status = $this->registration->status($student);

        if ($status['stage'] === 'unassigned') {
            throw new NotFoundHttpException(
                'No programme is linked to this account yet, so there is nothing to register for.'
            );
        }

        // Published list: enrol the student and send them to their courses
        // rather than asking them to choose. This is the case the owner asked
        // for — the coordinator has already decided.
        if ($status['stage'] === 'auto_enroll') {
            $result = $this->registration->autoEnrol($student);

            return redirect()->route('student.dashboard')->with(
                'success',
                "Your courses were published by your programme coordinator — "
                ."{$result['registered']} courses across {$result['credits']} credit units are already registered for you."
            );
        }

        if ($status['stage'] === 'complete') {
            return redirect()->route('student.dashboard')
                ->with('success', 'You have completed registration for this session.');
        }

        $semester = $status['stage'] === 'select_semester_1' ? 1 : 2;

        return view('student.course-registration', [
            'student' => $student,
            'programme' => $status['programme'],
            'level' => $status['level'],
            'semester' => $semester,
            'courses' => $this->registration->coursesForSemester($student, $semester),
        ]);
    }

    /**
     * Records the student's selection for one semester.
     */
    public function store(Request $request)
    {
        $student = $this->student($request);

        $validated = $request->validate([
            'semester' => ['required', 'integer', 'in:1,2'],
            'courses' => ['required', 'array', 'min:1'],
            'courses.*' => ['integer'],
        ]);

        $semester = (int) $validated['semester'];

        try {
            $result = $this->registration->registerSemester(
                $student,
                $semester,
                array_map('intval', $validated['courses'])
            );
        } catch (\LogicException $e) {
            // Out-of-order attempt. The message says what to do, not just
            // that something was refused.
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $message = "Semester {$semester} saved — {$result['registered']} courses, "
            ."{$result['credits']} credit units.";

        if ($semester === 1) {
            $message .= ' Now choose your second semester courses.';

            return redirect()->route('student.course.register')
                ->with('success', $message);
        }

        return redirect()->route('student.dashboard')
            ->with('success', $message . ' You are fully registered.');
    }

    /**
     * The student is always resolved from the session, never from input.
     *
     * Taking a student id from the request would let one student read or
     * overwrite another's registration by changing a single value.
     */
    private function student(Request $request): object
    {
        $student = $request->user()?->student;

        if (! $student) {
            throw new AccessDeniedHttpException('A student record is required for this page.');
        }

        return $student;
    }
}
