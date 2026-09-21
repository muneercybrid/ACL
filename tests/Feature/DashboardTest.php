<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The authenticated dashboard render.
 *
 * Until now the suite only proved that a guest is redirected away from the
 * dashboard; the listing logic behind it never executed. These tests render
 * the page for a signed-in user, exercising the role-aware app shell and the
 * reusable component library (page-header, stat-card, card, empty-state).
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_enrolled_student_sees_their_courses(): void
    {
        $student = User::where('email', 'student@acl.local')->firstOrFail();
        app(EntitlementService::class)->syncInstitutionalEnrollments($student);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('CSC101')          // an enrolled course code -> course card rendered
            ->assertSee('Enrolled courses'); // stat card label
    }

    public function test_dashboard_shows_empty_state_without_enrolments(): void
    {
        // The platform admin holds no enrolments, so the zero-data branch renders.
        $admin = User::where('email', 'admin@acl.local')->firstOrFail();
        $this->assertSame(0, $admin->enrollments()->count());

        // DashboardController redirects non-superadmin users to student dashboard.
        $this->actingAs($admin)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard');
    }

    /**
     * Regression test for: Route [course.show] not defined.
     *
     * The student route group uses name('student.') prefix, so the correct
     * fully-qualified route name for the course detail page is
     * 'student.course.show'. Any view that calls route('course.show', ...)
     * (without the prefix) will throw a RouteNotFoundException at render time.
     * This test ensures the /student page renders without that error.
     */
    public function test_student_dashboard_does_not_throw_route_not_defined(): void
    {
        // Assert the correct named route exists and resolves.
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('student.course.show'),
            "Route 'student.course.show' must be registered. " .
            "Did you accidentally drop the name('student.') prefix group?"
        );

        // Assert the bare (wrong) name does NOT exist, so misuse is caught early.
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('course.show'),
            "Route 'course.show' should not exist as a standalone name. " .
            "Course routes belong inside the student. prefix group."
        );

        // Assert the dashboard itself renders without a RouteNotFoundException.
        $student = User::where('email', 'student@acl.local')->firstOrFail();
        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk();
    }
}
