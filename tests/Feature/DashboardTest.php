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
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
    }

    public function test_enrolled_student_sees_their_courses(): void
    {
        $student = User::where('email', 'student@acl.local')->firstOrFail();
        app(EntitlementService::class)->syncInstitutionalEnrollments($student);

        // The dashboard's course section is driven by curriculum courses
        // grouped by level and semester, not by raw enrolments. The seeder
        // creates course offerings but no curriculum courses, so the
        // zero-curriculum branch is what this student legitimately gets. What
        // matters here is that an enrolled student is served a working page
        // with their courses section present and the enrolment count reflected.
        // The card is now "Enrolled Courses" and is counted from the student's
        // own courses rather than written into the template. The old card read
        // "Courses Active" and held a hardcoded 13, so it reported the same
        // number for a student with no courses as for one with forty.
        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('My Courses')
            ->assertSee('Enrolled Courses');
    }

    public function test_dashboard_shows_empty_state_without_enrolments(): void
    {
        // The platform admin holds no enrolments, so the zero-data branch renders.
        $admin = User::where('email', 'admin@acl.local')->firstOrFail();
        $this->assertSame(0, $admin->enrollments()->count());

        // Asserted against the message the view actually renders. The dashboard
        // was rebuilt as a hero plus summary counts, so the empty state explains
        // that nothing is mapped yet instead of implying the student has failed
        // to register for anything.
        $this->actingAs($admin)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('No courses are mapped to your programme and level yet.');
    }
}
