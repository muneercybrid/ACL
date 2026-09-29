<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Institution;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the forced password change.
 *
 * Institution administrators are provisioned with a generated credential and
 * flagged `force_password_change`. Two defects made that flag unusable and are
 * guarded against here:
 *
 *  1. There was no route at which a signed-in user could set a password, so
 *     the flag was unactionable and every provisioned administrator was stuck
 *     with a credential nobody, including them, had seen.
 *  2. The guard asked `hasRole('institution.admin')` with no entity, which
 *     requires an unscoped role assignment. An institution administrator's
 *     role is always scoped to their institution, so the check reported false
 *     for every real administrator and the guard never fired.
 */
class SetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function adminAccount(): User
    {
        $institution = Institution::create([
            'name' => 'Probe University, Testville',
            'normalized_name' => 'probe university testville',
            'slug' => 'probe-university',
            'ownership' => 'Private',
            'institution_status' => 'ACTIVE',
            'onboarding_status' => 'NOT_ONBOARDED',
        ]);

        // The restructure migration already defines institution.admin, so
        // creating it outright collides with the roles_slug_unique index.
        $role = Role::firstOrCreate(
            ['slug' => 'institution.admin'],
            ['name' => 'Institution Admin'],
        );

        $user = User::create([
            'name' => 'Probe Administrator',
            'email' => 'probe.admin@acl.test',
            'password' => bcrypt('generated-credential'),
            'institution_id' => $institution->id,
            'force_password_change' => true,
        ]);

        $user->roleAssignments()->create([
            'role_id' => $role->id,
            'entity_type' => Institution::class,
            'entity_id' => $institution->id,
        ]);

        return $user->fresh();
    }

    public function test_set_password_page_renders_for_an_authenticated_user(): void
    {
        $this->actingAs($this->adminAccount())
            ->get('/set-password')
            ->assertOk()
            ->assertSee('Set your password', escape: false);
    }

    public function test_set_password_page_requires_authentication(): void
    {
        $this->get('/set-password')->assertRedirect('/login');
    }

    public function test_user_awaiting_a_password_change_is_confined_to_it(): void
    {
        $user = $this->adminAccount();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('password.set'));
    }

    public function test_user_awaiting_a_password_change_can_still_sign_out(): void
    {
        $this->actingAs($this->adminAccount())
            ->from(route('password.set'))
            ->post('/logout')
            ->assertRedirect();
    }

    public function test_user_who_has_set_a_password_is_released(): void
    {
        $user = $this->adminAccount();
        $user->update(['force_password_change' => false]);

        // Asserted against the student dashboard itself. /dashboard is a
        // compatibility alias that answers with a redirect, so it can never
        // return 200 and a confinement redirect is indistinguishable from it.
        $this->actingAs($user)
            ->get(route('student.dashboard'))
            ->assertOk();
    }

    public function test_a_strong_password_is_accepted_and_clears_the_flag(): void
    {
        $user = $this->adminAccount();

        $this->actingAs($user)
            ->post('/set-password', [
                'password' => 'C0rrectHorse!Battery',
                'password_confirmation' => 'C0rrectHorse!Battery',
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertFalse($user->force_password_change, 'the flag must clear once a password is set');
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('C0rrectHorse!Battery', $user->password),
            'the new password must be the one stored'
        );
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $user = $this->adminAccount();
        $before = $user->password;

        $this->actingAs($user)
            ->post('/set-password', [
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $this->assertSame($before, $user->fresh()->password, 'a rejected password must not be stored');
        $this->assertTrue((bool) $user->fresh()->force_password_change);
    }

    public function test_a_mismatched_confirmation_is_rejected(): void
    {
        $user = $this->adminAccount();
        $before = $user->password;

        $this->actingAs($user)
            ->post('/set-password', [
                'password' => 'C0rrectHorse!Battery',
                'password_confirmation' => 'SomethingElse123',
            ]);

        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_changing_a_password_again_requires_the_current_one(): void
    {
        $user = $this->adminAccount();
        $user->update(['force_password_change' => false]);

        // Without the current password.
        $this->actingAs($user)
            ->post('/set-password', [
                'password' => 'An0therGoodPass!',
                'password_confirmation' => 'An0therGoodPass!',
            ]);

        $this->assertFalse(
            \Illuminate\Support\Facades\Hash::check('An0therGoodPass!', $user->fresh()->password),
            'a change must not succeed without the current password'
        );
    }

    public function test_a_wrong_current_password_is_refused(): void
    {
        $user = $this->adminAccount();
        $user->update(['force_password_change' => false]);

        $this->actingAs($user)
            ->post('/set-password', [
                'current_password' => 'NotTheRightOne123',
                'password' => 'Th1rdChange!Pass',
                'password_confirmation' => 'Th1rdChange!Pass',
            ]);

        $this->assertFalse(
            \Illuminate\Support\Facades\Hash::check('Th1rdChange!Pass', $user->fresh()->password)
        );
    }

    public function test_a_user_without_an_institution_is_never_confined(): void
    {
        $user = User::create([
            'name' => 'Plain Student',
            'email' => 'plain.student@acl.test',
            'password' => bcrypt('whatever'),
            'institution_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('student.dashboard'))
            ->assertOk();
    }
}
