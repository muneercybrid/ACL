<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Reset your password');
    }

    public function test_forgot_password_request_has_same_response_for_existing_and_unknown_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'student@example.com',
        ]);

        $existing = $this->from('/forgot-password')
            ->post('/forgot-password', [
                'email' => $user->email,
            ]);

        $unknown = $this->from('/forgot-password')
            ->post('/forgot-password', [
                'email' => 'unknown@example.com',
            ]);

        $existing->assertRedirect('/forgot-password')
            ->assertSessionHas(
                'status',
                'If an account exists for that email address, a password reset link has been sent.'
            );

        $unknown->assertRedirect('/forgot-password')
            ->assertSessionHas(
                'status',
                'If an account exists for that email address, a password reset link has been sent.'
            );

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_can_be_used_to_reset_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $this->post('/forgot-password', [
            'email' => $user->email,
        ])->assertRedirect('/forgot-password');

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user): bool {
                $token = $notification->token;

                $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))
                    ->assertOk()
                    ->assertSee('Choose a new password');

                $response = $this->post('/reset-password', [
                    'token' => $token,
                    'email' => $user->email,
                    'password' => 'new-password',
                    'password_confirmation' => 'new-password',
                ]);

                $response->assertRedirect('/login')
                    ->assertSessionHas(
                        'status',
                        'Your password has been reset. You can now sign in.'
                    );

                $user->refresh();

                $this->assertTrue(Hash::check('new-password', $user->password));
                $this->assertFalse(Hash::check('old-password', $user->password));

                return true;
            }
        );
    }

    public function test_reset_token_is_invalid_after_successful_password_reset(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'student@example.com',
        ]);

        $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user): bool {
                $token = $notification->token;

                $this->post('/reset-password', [
                    'token' => $token,
                    'email' => $user->email,
                    'password' => 'new-password',
                    'password_confirmation' => 'new-password',
                ])->assertRedirect('/login');

                $this->assertDatabaseMissing('password_reset_tokens', [
                    'email' => $user->email,
                ]);

                $secondAttempt = $this->from('/reset-password/'.$token)
                    ->post('/reset-password', [
                        'token' => $token,
                        'email' => $user->email,
                        'password' => 'another-password',
                        'password_confirmation' => 'another-password',
                    ]);

                $secondAttempt->assertSessionHasErrors('email');

                return true;
            }
        );
    }

    public function test_reset_requires_matching_password_confirmation(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'student@example.com',
        ]);

        $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user): bool {
                $response = $this->post('/reset-password', [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'new-password',
                    'password_confirmation' => 'different-password',
                ]);

                $response->assertSessionHasErrors('password');

                return true;
            }
        );
    }

    public function test_invalid_reset_token_cannot_change_password(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('email');

        $user->refresh();

        $this->assertTrue(Hash::check('old-password', $user->password));
    }
}
