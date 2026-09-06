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
            ->get(route('dashboard'))
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

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No courses yet');
    }
}
