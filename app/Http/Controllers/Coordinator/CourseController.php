<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\AcademicProgram;
use App\Models\Course;
use App\Models\Organization;
use App\Services\Auth\LevelCoordinatorAppointer;
use App\Services\Auth\LevelCoordinatorScope;
use App\Services\Auth\RoleHomeResolver;
use App\Services\Courses\CentralCourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The courses a level coordinator runs for their programme at their level.
 *
 * Two ways in, both scoped to the signed-in coordinator's own appointment:
 * search the NUC CCMAS list and add what they find, or type in a course the
 * list does not carry. The manual fields are not a fallback bolted on — the
 * corpus genuinely has gaps, and a coordinator blocked from recording a real
 * course their programme requires would be worse than a tidy list.
 */
class CourseController extends Controller
{
    public function __construct(
        private readonly LevelCoordinatorScope $scope,
        private readonly RoleHomeResolver $roles,
        private readonly CentralCourseService $central,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeCoordinator($user);

        $offerings = $this->scope->offeringsFor($user);

        $offering = null;
        if ($offerings->isNotEmpty()) {
            $requested = (int) $request->query('offering');
            $offering = $offerings->firstWhere('id', $requested) ?? $offerings->first();
        }

        $level = null;
        $courses = collect();

        if ($offering) {
            // Only levels this coordinator is actually appointed to are
            // offered, so the page cannot be steered onto a level they do not
            // hold.
            $levels = $offering->levels;
            $requestedLevel = (int) $request->query('level');

            $level = $levels->contains($requestedLevel) ? $requestedLevel : $levels->first();

            if ($level) {
                $courses = $this->scope->coursesFor($offering->id, $level);
            }
        }

        return view('coordinator.courses', [
            'offerings' => $offerings,
            'offering' => $offering,
            'level' => $level,
            'courses' => $courses,
            'search' => $request->query('q'),
        ]);
    }

    /**
     * Search the CCMAS list, as JSON for the search box.
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeCoordinator($user);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'level' => ['nullable', 'integer', 'in:'.implode(',', LevelCoordinatorAppointer::LEVELS)],
        ]);

        $results = $this->central->search(
            $validated['q'] ?? null,
            $this->schoolId($request),
        );

        return response()->json([
            'results' => $results->map(fn ($course) => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'credit_units' => $course->credit_units,
                // The school's own code for it, when one is registered, so the
                // coordinator recognises a course another school already runs.
                'local_code' => $course->local_code,
                'shared_with' => $course->usage_count,
                'from_ccmas' => (bool) $course->ccmas_course_id,
            ])->values(),
        ]);
    }

    /**
     * Add a course the coordinator picked from the central catalogue.
     *
     * The course is shared, so adding it is a matter of naming the central
     * course and giving it this school's code. The content comes with it.
     */
    public function storeShared(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeCoordinator($user);

        $validated = $request->validate([
            'academic_program_id' => ['required', 'integer'],
            'level' => ['required', 'integer', 'in:'.implode(',', LevelCoordinatorAppointer::LEVELS)],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'course_code' => ['required', 'string', 'max:64'],
            'semester' => ['nullable', 'string', 'max:16'],
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $organization = $this->schoolFor($request, (int) $validated['academic_program_id']);

        // The school's code is registered against the shared course, so another
        // programme at the same school can reuse it and the mapping is visible
        // to anyone who looks the course up.
        try {
            if ($organization) {
                $this->central->registerLocalCode($organization, $course, $validated['course_code']);
            }

            $this->scope->addCourse(
                $user,
                (int) $validated['academic_program_id'],
                (int) $validated['level'],
                $course,
                $validated['course_code'],
                $validated['semester'] ?? null,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['course' => $e->getMessage()])->withInput();
        }

        return back()->with('success', "{$course->title} added.");
    }

    /**
     * Add a course the central catalogue does not carry.
     *
     * The NUC list has real gaps, and a coordinator blocked from recording a
     * course their programme genuinely requires would be worse than a tidy
     * catalogue. The course is created centrally so the next school to want it
     * finds it rather than writing it again.
     */
    public function storeManual(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeCoordinator($user);

        $validated = $request->validate([
            'academic_program_id' => ['required', 'integer'],
            'level' => ['required', 'integer', 'in:'.implode(',', LevelCoordinatorAppointer::LEVELS)],
            'course_code' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'credit_units' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'semester' => ['nullable', 'string', 'max:16'],
        ], [
            'credit_units.numeric' => 'Credit units must be a number, for example 3 or 4.5.',
        ]);

        $organization = $this->schoolFor($request, (int) $validated['academic_program_id']);

        try {
            $course = $this->central->createCentral(
                $validated['title'],
                $validated['course_code'],
                isset($validated['credit_units']) ? (float) $validated['credit_units'] : null,
                $organization?->id,
            );

            if ($organization) {
                $this->central->registerLocalCode($organization, $course, $validated['course_code']);
            }

            $this->scope->addCourse(
                $user,
                (int) $validated['academic_program_id'],
                (int) $validated['level'],
                $course,
                $validated['course_code'],
                $validated['semester'] ?? null,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['course' => $e->getMessage()])->withInput();
        }

        return back()->with('success', "{$course->title} added as a new shared course.");
    }

    public function destroy(Request $request, int $course): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeCoordinator($user);

        try {
            $this->scope->removeCourse($user, $course);
        } catch (\Throwable $e) {
            return back()->withErrors(['course' => $e->getMessage()]);
        }

        return back()->with('success', 'Course removed.');
    }

    /**
     * The school an offering belongs to, resolved from the coordinator's own
     * appointment rather than from the submitted id.
     */
    private function schoolFor(Request $request, int $academicProgramId): ?Organization
    {
        $appointments = $this->scope->appointmentsFor($request->user());

        $academicProgram = AcademicProgram::find($academicProgramId);
        if (! $academicProgram || ! $appointments->isNotEmpty()) {
            return null;
        }

        $appointment = $appointments->first(
            fn ($a) => (int) $a->programme_id === (int) $academicProgram->nuc_programme_id
                && (int) $a->organization_id === (int) $academicProgram->organization_id
        );

        return $appointment?->organization;
    }

    private function schoolId(Request $request): ?int
    {
        return $this->scope->appointmentsFor($request->user())->first()?->organization_id;
    }

    /**
     * A coordinator with no appointment has nothing to do here.
     *
     * Named rather than 'authorize' so it does not collide with the public
     * authorize() on the base Controller, which Laravel's routing reflects on.
     *
     * Checked before any query, because the page itself describes the
     * appointment feature and should not be readable by an account that has
     * none.
     */
    private function authorizeCoordinator(\App\Models\User $user): void
    {
        if (! $this->roles->holdsRole($user, RoleHomeResolver::ROLE_LEVEL_COORDINATOR)
            && ! $this->roles->holdsRole($user, RoleHomeResolver::ROLE_SUPERADMIN)) {
            abort(403, 'This account is not a level coordinator.');
        }
    }
}
