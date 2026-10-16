<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Google OAuth callback.
 *
 * Pins the defect where first-time Google sign-ins inserted a NULL password
 * into the NOT NULL users.password column and every new registration failed.
 */
class GoogleCallbackTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(string $email, string $name = 'Ada Lovelace', string $id = 'google-123'): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token']),
            'www.googleapis.com/oauth2/v2/userinfo' => Http::response([
                'id' => $id,
                'email' => $email,
                'name' => $name,
            ]),
        ]);
    }

    public function test_a_first_time_google_user_is_created_and_signed_in(): void
    {
        $this->fakeGoogle('new.student@example.com');

        $response = $this->get(route('google.callback', ['code' => 'auth-code']));

        $response->assertRedirect(route('register.student'));

        $user = User::where('email', 'new.student@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('google', $user->provider);
        $this->assertSame('google-123', $user->provider_id);
        $this->assertNotNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_existing_user_is_linked_rather_than_duplicated(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.com']);

        $this->fakeGoogle('existing@example.com', id: 'google-456');

        $this->get(route('google.callback', ['code' => 'auth-code']));

        $this->assertSame(1, User::where('email', 'existing@example.com')->count());

        $existing->refresh();
        $this->assertSame('google', $existing->provider);
        $this->assertSame('google-456', $existing->provider_id);
        $this->assertAuthenticatedAs($existing);
    }
}
