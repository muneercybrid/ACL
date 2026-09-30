<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_login_page_never_exposes_credentials(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('pw: password', false)
            ->assertDontSee('student@acl.local', false)
            ->assertDontSee('admin@acl.local', false);
    }

    public function test_users_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        // An account holding no staff role is treated as a student. That is where
        // /dashboard used to forward everyone anyway — it is now decided from
        // the account's own roles instead of by a stub that redirected to the
        // student page for administrators and coordinators too.
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_dashboard_requires_authentication(): void
    {
        // /dashboard is a compatibility alias that forwards to the student
        // dashboard, but the auth group runs before the controller, so a guest
        // is turned away with a redirect to login and never reaches either.
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
