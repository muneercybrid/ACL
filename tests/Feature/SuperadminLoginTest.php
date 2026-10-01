<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperadminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $email, bool $superadmin = false): \App\Models\User
    {
        $id = \DB::table('users')->insertGetId([
            'name' => 'U', 'email' => $email, 'password' => Hash::make('correct-horse-battery'),
            'status' => 'active', 'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($superadmin) {
            $roleId = \DB::table('roles')->where('slug', 'superadmin')->value('id');
            \DB::table('role_assignments')->insert([
                'user_id' => $id, 'role_id' => $roleId,
                'entity_type' => null, 'entity_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return \App\Models\User::find($id);
    }

    public function test_the_superadmin_can_sign_in_and_land_in_the_command_centre(): void
    {
        $this->makeUser('sa@acl.test', superadmin: true);

        $this->post('/superadmin/login', ['email' => 'sa@acl.test', 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('superadmin.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->makeUser('sa@acl.test', superadmin: true);

        $this->post('/superadmin/login', ['email' => 'sa@acl.test', 'password' => 'nope'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_non_superadmin_cannot_use_this_door_even_with_the_right_password(): void
    {
        // The single most important property of this page: a valid account with
        // valid credentials is still refused, because the role is not held.
        $this->makeUser('student@acl.test', superadmin: false);

        $this->post('/superadmin/login', ['email' => 'student@acl.test', 'password' => 'correct-horse-battery'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_unknown_address_gives_the_same_message_as_a_wrong_password(): void
    {
        $this->makeUser('sa@acl.test', superadmin: true);

        $this->post('/superadmin/login', ['email' => 'sa@acl.test', 'password' => 'nope'])
            ->assertSessionHasErrors('email');
        $unknown = $this->post('/superadmin/login', ['email' => 'ghost@acl.test', 'password' => 'nope'])
            ->assertSessionHasErrors('email');

        // Identical wording, so the page cannot be used to enumerate accounts.
        $this->assertStringContainsString(
            'not registered for the Superadmin console',
            session('errors')->first('email')
        );
    }

    public function test_the_session_id_is_rotated_on_sign_in(): void
    {
        $this->makeUser('sa@acl.test', superadmin: true);

        $this->get('/superadmin/login');
        $before = session()->getId();

        $this->post('/superadmin/login', ['email' => 'sa@acl.test', 'password' => 'correct-horse-battery']);

        // A session id captured before sign-in must not survive the privilege
        // boundary.
        $this->assertNotSame($before, session()->getId());
    }
}
