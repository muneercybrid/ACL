<?php

namespace Tests\Feature;

use App\Models\LevelCoordinator;
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

    public function test_the_sign_in_screen_offers_the_three_roles(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Institution Admin')
            ->assertSee('Level Coordinator')
            ->assertSee('Student')
            ->assertSee('name="role"', false);
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
