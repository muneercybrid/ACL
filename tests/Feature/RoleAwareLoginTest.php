<?php

namespace Tests\Feature;

use App\Models\LevelCoordinator;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Signing in, and where each role lands afterwards.
 *
 * The defect these pin is the one that made institution administrators and
 * level coordinators unusable: the redirect after login asked only "is this a
 * student?", so everyone else was sent to the student dashboard.
 */
class RoleAwareLoginTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['password' => bcrypt('secret1234')]);

        RoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'entity_type' => null,
            'entity_id' => null,
        ]);

        return $user->fresh();
    }

    public function test_the_student_sign_in_offers_no_role_selector(): void
    {
        // The role picker was removed as an enumeration surface: a form offering
        // "Level Coordinator" confirms the role exists and invites an attempt at
        // it. Staff now sign in through their own organization's door.
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('name="role"', false)
            ->assertDontSee('Institution Admin')
            ->assertDontSee('Level Coordinator');
    }

    public function test_the_organization_door_offers_no_sign_up(): void
    {
        $this->get(route('organizations.login'))
            ->assertOk()
            ->assertDontSee(route('register'), false);
    }

    public function test_an_institution_admin_signs_in_through_their_own_organization(): void
    {
        $organization = Organization::create([
            'name' => 'Bayero University Kano', 'slug' => 'buk', 'is_active' => true,
        ]);

        $admin = $this->userWithRole(RoleHomeResolver::ROLE_INSTITUTION_ADMIN);
        RoleAssignment::create([
            'user_id' => $admin->id,
            'role_id' => Role::where('slug', RoleHomeResolver::ROLE_INSTITUTION_ADMIN)->value('id'),
            'entity_type' => Organization::class,
            'entity_id' => $organization->id,
        ]);

        $this->post(route('organizations.login.store', $organization), [
            'email' => $admin->email,
            'password' => 'secret1234',
        ])->assertRedirect(route('institution.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_valid_account_signing_in_through_the_wrong_organization_is_refused(): void
    {
        $mine = Organization::create(['name' => 'Mine', 'slug' => 'mine', 'is_active' => true]);
        $theirs = Organization::create(['name' => 'Theirs', 'slug' => 'theirs', 'is_active' => true]);

        $admin = $this->userWithRole(RoleHomeResolver::ROLE_INSTITUTION_ADMIN);
        RoleAssignment::create([
            'user_id' => $admin->id,
            'role_id' => Role::where('slug', RoleHomeResolver::ROLE_INSTITUTION_ADMIN)->value('id'),
            'entity_type' => Organization::class,
            'entity_id' => $mine->id,
        ]);

        $this->post(route('organizations.login.store', $theirs), [
            'email' => $admin->email,
            'password' => 'secret1234',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_every_refusal_reports_identically(): void
    {
        // The point of the organization door is that it cannot be used to find
        // out who works where. A wrong password and a valid-but-not-staff
        // account must say the same thing, or the wording itself leaks which
        // addresses exist and where they are scoped.
        $organization = Organization::create(['name' => 'Mine', 'slug' => 'mine', 'is_active' => true]);

        $student = $this->userWithRole(RoleHomeResolver::ROLE_STUDENT);

        $expected = 'Those sign-in details were not recognised for this institution.';

        $this->post(route('organizations.login.store', $organization), [
            'email' => $student->email, 'password' => 'wrong-password',
        ])
            ->assertSessionHasErrors('email', $expected)
            ->assertSessionMissing('errors.password');

        // A correct password on an account that is not staff here must be
        // refused in exactly the same words.
        $this->post(route('organizations.login.store', $organization), [
            'email' => $student->email, 'password' => 'secret1234',
        ])
            ->assertSessionHasErrors('email', $expected)
            ->assertSessionMissing('errors.password');

        $this->assertGuest();
    }

    public function test_a_non_existing_address_is_refused_the_same_way(): void
    {
        $organization = Organization::create(['name' => 'Mine', 'slug' => 'mine', 'is_active' => true]);

        $expected = 'Those sign-in details were not recognised for this institution.';

        // An address that does not exist must be indistinguishable from one that
        // does, or the door answers "who works here" for any address asked.
        $this->post(route('organizations.login.store', $organization), [
            'email' => 'nobody-at-all@example.test', 'password' => 'whatever',
        ])->assertSessionHasErrors('email', $expected);

        $this->assertGuest();
    }

    public function test_the_organization_door_is_throttled(): void
    {
        $organization = Organization::create(['name' => 'Mine', 'slug' => 'mine', 'is_active' => true]);

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('organizations.login.store', $organization), [
                'email' => 'nobody@example.test', 'password' => 'guess'.$i,
            ]);
        }

        // Keyed on address AND organization AND ip, so repeated guessing from
        // one place cannot continue indefinitely.
        $this->post(route('organizations.login.store', $organization), [
            'email' => 'nobody@example.test', 'password' => 'guess7',
        ])->assertSessionHasErrors('email');
    }

    public function test_an_inactive_organization_has_no_door(): void
    {
        $organization = Organization::create([
            'name' => 'Closed', 'slug' => 'closed', 'is_active' => false,
        ]);

        $this->get(route('organizations.login.show', $organization))->assertNotFound();
    }

    public function test_an_institution_admin_lands_on_the_institution_area(): void
    {
        $admin = $this->userWithRole(RoleHomeResolver::ROLE_INSTITUTION_ADMIN);

        $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'secret1234',
            'role' => RoleHomeResolver::ROLE_INSTITUTION_ADMIN,
        ])->assertRedirect(route('institution.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_level_coordinator_lands_on_the_coordinator_area(): void
    {
        $coordinator = $this->userWithRole(RoleHomeResolver::ROLE_LEVEL_COORDINATOR);

        $this->post(route('login'), [
            'email' => $coordinator->email,
            'password' => 'secret1234',
            'role' => RoleHomeResolver::ROLE_LEVEL_COORDINATOR,
        ])->assertRedirect(route('coordinator.dashboard'));
    }

    public function test_a_student_lands_on_the_student_dashboard(): void
    {
        $student = $this->userWithRole(RoleHomeResolver::ROLE_STUDENT);

        $this->post(route('login'), [
            'email' => $student->email,
            'password' => 'secret1234',
            'role' => RoleHomeResolver::ROLE_STUDENT,
        ])->assertRedirect(route('student.dashboard'));
    }

    public function test_an_institution_admin_with_no_role_picked_still_lands_correctly(): void
    {
        // The role selector is a convenience; omitting it must not send an
        // administrator to the student dashboard.
        $admin = $this->userWithRole(RoleHomeResolver::ROLE_INSTITUTION_ADMIN);

        $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'secret1234',
        ])->assertRedirect(route('institution.dashboard'));
    }

    public function test_choosing_a_role_the_account_does_not_hold_is_refused(): void
    {
        $student = $this->userWithRole(RoleHomeResolver::ROLE_STUDENT);

        $response = $this->post(route('login'), [
            'email' => $student->email,
            'password' => 'secret1234',
            'role' => RoleHomeResolver::ROLE_LEVEL_COORDINATOR,
        ]);

        $response->assertSessionHasErrors('role');

        // The credentials were genuine, so the refusal has to actually end the
        // session. Otherwise the message is cosmetic and the student is still
        // signed in.
        $this->assertGuest();
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $admin = $this->userWithRole(RoleHomeResolver::ROLE_INSTITUTION_ADMIN);

        $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'secret1234',
            'role' => 'superadmin',
        ])->assertSessionHasErrors('role');

        $this->assertGuest();
    }

    public function test_a_student_cannot_open_the_coordinator_area(): void
    {
        $student = $this->userWithRole(RoleHomeResolver::ROLE_STUDENT);

        $this->actingAs($student)->get(route('coordinator.dashboard'))->assertForbidden();
    }

    public function test_a_student_cannot_open_the_institution_area(): void
    {
        $student = $this->userWithRole(RoleHomeResolver::ROLE_STUDENT);

        $this->actingAs($student)->get(route('institution.dashboard'))->assertForbidden();
    }

    public function test_a_guest_is_redirected_away_from_the_coordinator_area(): void
    {
        $this->get(route('coordinator.dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_coordinator_sees_only_their_own_appointments(): void
    {
        $coordinator = $this->userWithRole(RoleHomeResolver::ROLE_LEVEL_COORDINATOR);
        $other = $this->userWithRole(RoleHomeResolver::ROLE_LEVEL_COORDINATOR);

        // The appointment must hang off a real programme row; the column is a
        // foreign key, so it cannot be faked with an arbitrary id.
        $programme = \App\Models\Curriculum\Programme::create([
            'name' => 'B.Sc. Cyber Security',
            'code' => 'CYB',
            'duration_years' => 4,
        ]);

        $mine = LevelCoordinator::create([
            'programme_id' => $programme->id, 'level' => 100, 'user_id' => $coordinator->id, 'status' => 'active',
        ]);
        $theirs = LevelCoordinator::create([
            'programme_id' => $programme->id, 'level' => 200, 'user_id' => $other->id, 'status' => 'active',
        ]);

        $response = $this->actingAs($coordinator)->get(route('coordinator.dashboard'));

        $response->assertOk();
        // Their own level is listed; the other coordinator's is not.
        $response->assertSee('B.Sc. Cyber Security');
        $response->assertSee('>100<', false);
        $response->assertDontSee('>200<', false);

        $this->assertDatabaseHas('level_coordinators', ['id' => $mine->id]);
        $this->assertDatabaseHas('level_coordinators', ['id' => $theirs->id]);
    }
}
