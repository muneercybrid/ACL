<?php

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\Curriculum\Programme;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An institution administrator appointing a coordinator from the dashboard.
 *
 * The one that matters here is the scope test: an administrator must not be
 * able to appoint a coordinator at somebody else's university by putting
 * another school's id in the form. Every other organisation in the system is
 * reachable by id, so this is the difference between a scoped feature and a
 * global one.
 */
class InstitutionAppointsCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    private function adminFor(Organization $school, string $slug = RoleHomeResolver::ROLE_INSTITUTION_ADMIN): User
    {
        $admin = User::factory()->create();

        RoleAssignment::create([
            'user_id' => $admin->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'entity_type' => Organization::class,
            'entity_id' => $school->id,
        ]);

        return $admin->fresh();
    }

    private function school(string $name): Organization
    {
        return Organization::create([
            'name' => $name, 'slug' => str($name)->slug(), 'is_active' => true,
        ]);
    }

    /**
     * A programme, plus the offering row that says a school actually runs it.
     *
     * The offering is not incidental: an appointment is only accepted for a
     * programme the school offers, so a fixture without one would create a
     * coordinator that could never reach its courses page.
     */
    private function programme(?Organization $school = null): Programme
    {
        $programme = Programme::create([
            'name' => 'B.Sc Cybersecurity', 'code' => 'CYB', 'duration_years' => 4,
        ]);

        if ($school) {
            $faculty = \App\Models\Faculty::create([
                'organization_id' => $school->id,
                'name' => 'Faculty of Science',
                'slug' => str($school->name.'-science')->slug(),
                'is_active' => true,
            ]);

            $department = \App\Models\Department::create([
                'faculty_id' => $faculty->id,
                'name' => 'Department of Computer Science',
                'slug' => str($school->name.'-cs')->slug(),
                'is_active' => true,
            ]);

            AcademicProgram::create([
                'name' => $programme->name,
                'slug' => str($school->name.'-cybersecurity')->slug(),
                'organization_id' => $school->id,
                'nuc_programme_id' => $programme->id,
                'department_id' => $department->id,
                'is_active' => true,
            ]);
        }

        return $programme;
    }

    public function test_an_institution_admin_can_open_the_appointment_page(): void
    {
        $admin = $this->adminFor($this->school('Bayero University Kano'));

        $this->actingAs($admin)->get(route('institution.coordinators'))
            ->assertOk()
            ->assertSee('Appoint a coordinator');
    }

    public function test_an_institution_admin_can_appoint_a_coordinator(): void
    {
        $school = $this->school('Bayero University Kano');
        $admin = $this->adminFor($school);
        $programme = $this->programme($school);

        $this->actingAs($admin)->post(route('institution.coordinators.store'), [
            'organization_id' => $school->id,
            'programme_id' => $programme->id,
            'level' => 100,
            'name' => 'Fatima Umar',
        ])->assertRedirect();

        $this->assertDatabaseHas('level_coordinators', [
            'organization_id' => $school->id,
            'programme_id' => $programme->id,
            'level' => 100,
        ]);
    }

    public function test_the_activation_link_is_shown_once_on_the_redirect(): void
    {
        $school = $this->school('Bayero University Kano');
        $admin = $this->adminFor($school);
        $programme = $this->programme($school);

        $response = $this->actingAs($admin)->post(route('institution.coordinators.store'), [
            'organization_id' => $school->id,
            'programme_id' => $programme->id,
            'level' => 100,
            'name' => 'Fatima Umar',
        ]);

        $response->assertSessionHas('activation');
        $this->assertStringContainsString(
            '/coordinator/activate/',
            session('activation')['url']
        );
    }

    public function test_an_admin_cannot_appoint_at_another_institution(): void
    {
        // The scope control. Every organization id in the system is a valid
        // primary key, so without this check this form would appoint at any of
        // the 481 schools.
        $mine = $this->school('Bayero University Kano');
        $theirs = $this->school('University of Lagos');
        $admin = $this->adminFor($mine);

        $this->actingAs($admin)->post(route('institution.coordinators.store'), [
            'organization_id' => $theirs->id,
            'programme_id' => $this->programme()->id,
            'level' => 100,
            'name' => 'Intruder',
        ])->assertSessionHasErrors('organization_id');

        $this->assertDatabaseMissing('level_coordinators', [
            'organization_id' => $theirs->id,
        ]);
        $this->assertSame(0, User::where('email', 'like', '%lvlcoord%')->count());
    }

    public function test_a_student_cannot_reach_the_appointment_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('institution.coordinators'))
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('institution.coordinators'))->assertRedirect(route('login'));
    }

    public function test_an_unsupported_level_is_rejected(): void
    {
        $school = $this->school('Bayero University Kano');
        $admin = $this->adminFor($school);
        $this->programme($school);

        $this->actingAs($admin)->post(route('institution.coordinators.store'), [
            'organization_id' => $school->id,
            'programme_id' => \App\Models\Curriculum\Programme::value('id'),
            'level' => 450,
            'name' => 'Nobody',
        ])->assertSessionHasErrors('level');
    }

    public function test_a_duplicate_appointment_is_refused_with_a_reason(): void
    {
        $school = $this->school('Bayero University Kano');
        $admin = $this->adminFor($school);
        $programme = $this->programme($school);

        $payload = [
            'organization_id' => $school->id,
            'programme_id' => $programme->id,
            'level' => 100,
            'name' => 'Fatima Umar',
        ];

        $this->actingAs($admin)->post(route('institution.coordinators.store'), $payload);

        $this->actingAs($admin)->post(route('institution.coordinators.store'), $payload)
            ->assertSessionHasErrors('level');

        $this->assertSame(1, \DB::table('level_coordinators')->count());
    }

    public function test_the_page_lists_the_appointments_it_made(): void
    {
        $school = $this->school('Bayero University Kano');
        $admin = $this->adminFor($school);
        $programme = $this->programme($school);

        $this->actingAs($admin)->post(route('institution.coordinators.store'), [
            'organization_id' => $school->id,
            'programme_id' => $programme->id,
            'level' => 300,
            'name' => 'Fatima Umar',
        ]);

        $this->actingAs($admin)->get(route('institution.coordinators', ['organization' => $school->id]))
            ->assertOk()
            ->assertSee('B.Sc Cybersecurity')
            ->assertSee('Fatima Umar')
            ->assertSee('Awaiting activation');
    }

    public function test_a_school_cannot_appoint_for_a_programme_it_does_not_offer(): void
    {
        // Without this, the appointment is created, the coordinator onboards
        // successfully, and then lands on a courses page showing nothing —
        // because offeringsFor() reads academic_programs, which has no row for
        // a programme the school does not run. A valid account with no work to
        // do and no indication why.
        $school = $this->school('Bayero University Kano');
        $admin = $this->adminFor($school);

        // Deliberately created with no offering row for this school: the
        // programme exists, but this institution is not recorded as running it.
        $programme = $this->programme();

        $this->actingAs($admin)->post(route('institution.coordinators.store'), [
            'organization_id' => $school->id,
            'programme_id' => $programme->id,
            'level' => 100,
            'name' => 'Fatima Umar',
        ])->assertSessionHasErrors('programme_id');

        $this->assertDatabaseMissing('level_coordinators', [
            'programme_id' => $programme->id,
        ]);
    }

    public function test_the_form_only_offers_programmes_the_school_runs(): void
    {
        $school = $this->school('Bayero University Kano');
        $admin = $this->adminFor($school);

        $this->actingAs($admin)->get(route('institution.coordinators'))
            ->assertOk()
            ->assertSee('This institution has no recorded programmes yet');
    }
}
