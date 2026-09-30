<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Services\Auth\LevelCoordinatorAppointer;
use App\Services\Auth\LevelCoordinatorScope;
use App\Services\Auth\RoleHomeResolver;
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

        $results = $this->scope->searchCcmas(
            $validated['q'] ?? null,
            isset($validated['level']) ? (int) $validated['level'] : null,
        );

        return response()->json([
            'results' => $results->map(fn ($course) => [
                'id' => $course->id,
                'course_code' => $course->course_code,
                'title' => $course->title,
                'credit_units' => $course->credit_units,
                'level' => $course->level,
            ])->values(),
        ]);
    }

    /**
     * Add a course taken from the CCMAS list.
     */
    public function storeFromCcmas(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeCoordinator($user);

        $validated = $request->validate([
            'academic_program_id' => ['required', 'integer'],
            'level' => ['required', 'integer', 'in:'.implode(',', LevelCoordinatorAppointer::LEVELS)],
            'ccmas_course_id' => ['required', 'integer', 'exists:ccmas_courses,id'],
            'semester' => ['nullable', 'string', 'max:16'],
        ]);

        $course = \App\Models\Curriculum\CcmasCourse::findOrFail($validated['ccmas_course_id']);

        try {
            $this->scope->addCourse(
                $user,
                (int) $validated['academic_program_id'],
                (int) $validated['level'],
                $course->id,
                $course->course_code,
                $course->title,
                $course->credit_units !== null ? (float) $course->credit_units : null,
                $validated['semester'] ?? null,
                'ccmas',
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['course' => $e->getMessage()])->withInput();
        }

        return back()->with('success', "{$course->course_code} added.");
    }

    /**
     * Add a course that the CCMAS list does not carry.
     */
    public function storeManual(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeCoordinator($user);

        $validated = $request->validate([
            'academic_program_id' => ['required', 'integer'],
            'level' => ['required', 'integer', 'in:'.implode(',', LevelCoordinatorAppointer::LEVELS)],
            'course_code' => ['required', 'string', 'max:32'],
            'title' => ['required', 'string', 'max:255'],
            'credit_units' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'semester' => ['nullable', 'string', 'max:16'],
        ], [
            'credit_units.numeric' => 'Credit units must be a number, for example 3 or 4.5.',
        ]);

        try {
            $this->scope->addCourse(
                $user,
                (int) $validated['academic_program_id'],
                (int) $validated['level'],
                null,
                $validated['course_code'],
                $validated['title'],
                isset($validated['credit_units']) ? (float) $validated['credit_units'] : null,
                $validated['semester'] ?? null,
                'manual',
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['course' => $e->getMessage()])->withInput();
        }

        return back()->with('success', strtoupper($validated['course_code']).' added.');
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
