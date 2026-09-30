<?php

namespace Tests\Feature;

use App\Models\Curriculum\Programme;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Auth\LevelCoordinatorAppointer;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Appointing a level coordinator, and the onboarding that follows.
 *
 * The behaviours pinned here were all found by exercising the flow, not by
 * reading it. Two of them were real defects: the generated address is
 * predictable, and the account is created with an unusable password, so the
 * activation link is the only way in and has to keep working after the holder
 * has already used it once.
 */
class LevelCoordinatorAppointmentTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $name = 'Northwest University Kano', string $state = 'Kano'): Organization
    {
        return Organization::create([
            'name' => $name, 'slug' => str($name)->slug(), 'state' => $state, 'is_active' => true,
        ]);
    }

    /**
     * A programme plus the offering row that says this school runs it.
     *
     * The offering is required, not decorative: an appointment for a programme
     * a school does not offer is refused, because such a coordinator could
     * never be matched to anything to manage.
     */
    private function programme(
        string $name = 'B.Sc Cybersecurity',
        string $code = 'CYB',
        ?Organization $school = null,
    ): Programme {
        $programme = Programme::create(['name' => $name, 'code' => $code, 'duration_years' => 4]);

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

            \App\Models\AcademicProgram::create([
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

    private function appointer(): LevelCoordinatorAppointer
    {
        return app(LevelCoordinatorAppointer::class);
    }

    public function test_the_generated_address_follows_the_agreed_scheme(): void
    {
        $email = $this->appointer()->emailFor($this->school(), $this->programme(), 100);

        // "Northwest University" contributes N, W and U — the school abbreviates
        // itself NWU, treating the compound as North + West — and the trailing
        // "Kano" is a place, not part of the name.
        $this->assertSame('nwucyblvl100lvlcoord@aclacademy.me', $email);
    }

    public function test_an_explicit_school_abbreviation_wins(): void
    {
        $school = $this->school();
        $school->update(['short_name' => 'UNILAG']);

        $email = $this->appointer()->emailFor($school, $this->programme(), 200);

        // A school that publishes an abbreviation is using it on its own
        // letterhead; deriving one instead would not match what people call it.
        $this->assertStringStartsWith('unilag', $email);
    }

    public function test_appointing_creates_the_appointment_tied_to_school_programme_and_level(): void
    {
        $school = $this->school();
        $programme = $this->programme(school: $school);

        $result = $this->appointer()->appoint($school, $programme, 100, ['name' => 'Fatima Umar']);

        $this->assertDatabaseHas('level_coordinators', [
            'organization_id' => $school->id,
            'programme_id' => $programme->id,
            'level' => 100,
            'user_id' => $result['user']->id,
            'status' => 'active',
        ]);
    }

    public function test_the_account_is_gated_until_the_person_onboards(): void
    {
        $result = $this->appointer()->appoint($school = $this->school(), $this->programme(school: $school), 100, ['name' => 'Fatima Umar']);

        $user = $result['user'];
        $this->assertTrue($user->must_complete_onboarding);
        $this->assertTrue($user->force_password_change);
    }

    public function test_the_account_gets_no_guessable_password(): void
    {
        $result = $this->appointer()->appoint($school = $this->school(), $this->programme(school: $school), 100, ['name' => 'Fatima Umar']);

        // The scheme-generated address is derivable by anyone who knows the
        // school, programme and level, so a shared default password would be a
        // published login. The account must not be openable without the link.
        $this->assertFalse(
            auth()->validate(['email' => $result['user']->email, 'password' => 'levelcoordinator'])
        );
        $this->assertFalse(
            auth()->validate(['email' => $result['user']->email, 'password' => 'password'])
        );
    }

    public function test_the_appointment_carries_the_coordinator_role_scoped_to_the_school(): void
    {
        $school = $this->school();
        $result = $this->appointer()->appoint($school, $this->programme(school: $school), 100, ['name' => 'Fatima Umar']);

        $this->assertDatabaseHas('role_assignments', [
            'user_id' => $result['user']->id,
            'role_id' => Role::where('slug', RoleHomeResolver::ROLE_LEVEL_COORDINATOR)->value('id'),
            'entity_type' => Organization::class,
            'entity_id' => $school->id,
        ]);
    }

    public function test_two_schools_may_each_appoint_for_the_same_programme_and_level(): void
    {
        // The uniqueness rule includes organization_id precisely so this is
        // possible. Before it existed, the second school was silently refused.
        $bayo = $this->school('Bayero University Kano');
        $lagos = $this->school('University of Lagos');
        $programme = $this->programme(school: $bayo);

        $a = $this->appointer()->appoint($bayo, $programme, 100, ['name' => 'A']);

        // The second school needs its own offering row; sharing the first
        // school's would be exactly the dead-end appointment now refused.
        \App\Models\AcademicProgram::create([
            'name' => $programme->name,
            'slug' => 'lagos-cybersecurity',
            'organization_id' => $lagos->id,
            'nuc_programme_id' => $programme->id,
            'department_id' => \App\Models\AcademicProgram::where('organization_id', $bayo->id)->value('department_id'),
            'is_active' => true,
        ]);

        $b = $this->appointer()->appoint($lagos, $programme, 100, ['name' => 'B']);

        $this->assertNotSame($a['user']->id, $b['user']->id);
    }

    public function test_one_school_cannot_appoint_twice_for_the_same_programme_and_level(): void
    {
        $school = $this->school();
        $programme = $this->programme(school: $school);

        $this->appointer()->appoint($school, $programme, 100, ['name' => 'First']);

        $this->expectException(\RuntimeException::class);
        $this->appointer()->appoint($school, $programme, 100, ['name' => 'Second']);
    }

    public function test_levels_beyond_the_tinyint_range_can_be_appointed(): void
    {
        // Levels 300-800 raised SQLSTATE 22003 "Numeric value out of range"
        // before the column was widened from tinyint to smallint. Each of
        // these is a real appointment, not an assertion about rows that were
        // never written.
        $school = $this->school();
        $programme = $this->programme(school: $school);

        foreach ([300, 400, 500, 600, 800] as $level) {
            $this->appointer()->appoint($school, $programme, $level, ['name' => "Coordinator {$level}"]);

            $this->assertDatabaseHas('level_coordinators', [
                'organization_id' => $school->id,
                'level' => $level,
            ]);
        }
    }

    public function test_a_level_outside_the_acl_range_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->appointer()->appoint($school = $this->school(), $this->programme(school: $school), 450, ['name' => 'Nobody']);
    }

    public function test_the_activation_link_reaches_the_onboarding_form(): void
    {
        $result = $this->appointer()->appoint($school = $this->school(), $this->programme(school: $school), 100, ['name' => 'Fatima Umar']);

        $this->get($result['activation_url'])
            ->assertOk()
            ->assertSee('Finish setup');
    }

    public function test_an_unsigned_activation_url_is_refused(): void
    {
        $result = $this->appointer()->appoint($school = $this->school(), $this->programme(school: $school), 100, ['name' => 'Fatima Umar']);

        $this->get("/coordinator/activate/{$result['user']->id}")->assertForbidden();
    }

    public function test_a_signed_in_stranger_cannot_open_another_coordinators_onboarding(): void
    {
        $result = $this->appointer()->appoint($school = $this->school(), $this->programme(school: $school), 100, ['name' => 'Fatima Umar']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get("/coordinator/activate/{$result['user']->id}")
            ->assertForbidden();
    }

    public function test_onboarding_replaces_the_generated_identity(): void
    {
        $result = $this->appointer()->appoint($school = $this->school(), $this->programme(school: $school), 100, ['name' => 'Fatima Umar']);
        $generated = $result['user']->email;

        // Arrive the way a real person does: the signed link signs them in, and
        // the form is submitted from that session. A bare POST with neither a
        // signature nor a session is correctly refused.
        $this->actingAs($result['user']);

        $this->post("/coordinator/activate/{$result['user']->id}", [
            'name' => 'Fatima Umar',
            'phone' => '08031234567',
            'email' => 'fatima.umar@northwest.edu.ng',
            'password' => 'CyberCoordinator2026',
            'password_confirmation' => 'CyberCoordinator2026',
        ])->assertRedirect(route('coordinator.dashboard'));

        $user = $result['user']->fresh();
        $this->assertSame('fatima.umar@northwest.edu.ng', $user->email);
        $this->assertSame('08031234567', $user->phone);
        $this->assertFalse($user->must_complete_onboarding);
        $this->assertFalse($user->force_password_change);
        $this->assertNotNull($user->onboarding_completed_at);

        // The generated address is gone entirely, so it cannot be guessed into.
        $this->assertSame(0, User::where('email', $generated)->count());
    }

    public function test_a_coordinator_may_not_keep_the_generated_address(): void
    {
        $result = $this->appointer()->appoint($school = $this->school(), $this->programme(school: $school), 100, ['name' => 'Fatima Umar']);

        // Accepting it would make the retirement a no-op and leave a login that
        // anyone can derive from the school, programme and level.
        // Arrive the way a real person does: the signed link signs them in, and
        // the form is submitted from that session. A bare POST with neither a
        // signature nor a session is correctly refused.
        $this->actingAs($result['user']);

        $this->post("/coordinator/activate/{$result['user']->id}", [
            'name' => 'Fatima Umar',
            'email' => $result['user']->email,
            'password' => 'CyberCoordinator2026',
            'password_confirmation' => 'CyberCoordinator2026',
        ])->assertSessionHasErrors('email');

        $this->assertTrue($result['user']->fresh()->must_complete_onboarding);
    }

    public function test_the_new_password_actually_works_after_onboarding(): void
    {
        $result = $this->appointer()->appoint($school = $this->school(), $this->programme(school: $school), 100, ['name' => 'Fatima Umar']);

        // Arrive the way a real person does: the signed link signs them in, and
        // the form is submitted from that session. A bare POST with neither a
        // signature nor a session is correctly refused.
        $this->actingAs($result['user']);

        $this->post("/coordinator/activate/{$result['user']->id}", [
            'name' => 'Fatima Umar',
            'email' => 'fatima.umar@northwest.edu.ng',
            'password' => 'CyberCoordinator2026',
            'password_confirmation' => 'CyberCoordinator2026',
        ]);

        $this->assertTrue(auth()->validate([
            'email' => 'fatima.umar@northwest.edu.ng',
            'password' => 'CyberCoordinator2026',
        ]));
    }

    public function test_mismatched_passwords_are_reported(): void
    {
        $result = $this->appointer()->appoint($school = $this->school(), $this->programme(school: $school), 100, ['name' => 'Fatima Umar']);

        // Arrive the way a real person does: the signed link signs them in, and
        // the form is submitted from that session. A bare POST with neither a
        // signature nor a session is correctly refused.
        $this->actingAs($result['user']);

        $this->post("/coordinator/activate/{$result['user']->id}", [
            'name' => 'Fatima Umar',
            'email' => 'fatima.umar@northwest.edu.ng',
            'password' => 'CyberCoordinator2026',
            'password_confirmation' => 'SomethingElse2026',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_completed_account_cannot_be_onboarded_again(): void
    {
        $result = $this->appointer()->appoint($school = $this->school(), $this->programme(school: $school), 100, ['name' => 'Fatima Umar']);

        // Arrive the way a real person does: the signed link signs them in, and
        // the form is submitted from that session. A bare POST with neither a
        // signature nor a session is correctly refused.
        $this->actingAs($result['user']);

        $this->post("/coordinator/activate/{$result['user']->id}", [
            'name' => 'Fatima Umar',
            'email' => 'fatima.umar@northwest.edu.ng',
            'password' => 'CyberCoordinator2026',
            'password_confirmation' => 'CyberCoordinator2026',
        ]);

        // A stale link must not overwrite the details the person just set.
        // Arrive the way a real person does: the signed link signs them in, and
        // the form is submitted from that session. A bare POST with neither a
        // signature nor a session is correctly refused.
        $this->actingAs($result['user']);

        $this->post("/coordinator/activate/{$result['user']->id}", [
            'name' => 'Someone Else',
            'email' => 'attacker@example.test',
            'password' => 'AnotherPassword2026',
            'password_confirmation' => 'AnotherPassword2026',
        ])->assertSessionHasErrors('email');

        $this->assertSame('Fatima Umar', $result['user']->fresh()->name);
    }

    public function test_a_coordinator_cannot_reach_anything_before_onboarding(): void
    {
        $result = $this->appointer()->appoint($school = $this->school(), $this->programme(school: $school), 100, ['name' => 'Fatima Umar']);

        // Signed in by the activation link, but still gated.
        $this->actingAs($result['user'])
            ->get(route('coordinator.dashboard'))
            ->assertRedirect("/coordinator/activate/{$result['user']->id}");
    }
}
