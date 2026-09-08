<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /**
     * Display the password reset request form.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a password reset link.
     *
     * The response intentionally does not reveal whether the submitted
     * address belongs to an ACL account.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $key = 'password-reset:'.Str::lower($request->string('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Too many reset requests. Please try again in '.ceil($seconds / 60).' minute(s).',
                ]);
        }

        RateLimiter::hit($key, 60);

        Password::sendResetLink([
            'email' => $request->string('email'),
        ]);

        return back()->with(
            'status',
            'If an account exists for that email address, a password reset link has been sent.'
        );
    }

    /**
     * Display the password reset form.
     */
    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                'confirmed',
                PasswordRule::min(8),
            ],
        ]);

        $resetRequestKey = 'password-reset:'.Str::lower($request->string('email')).'|'.$request->ip();

        $status = Password::reset(
            [
                'email' => $request->string('email'),
                'password' => $request->string('password'),
                'password_confirmation' => $request->string('password_confirmation'),
                'token' => $request->string('token'),
            ],
            function ($user, $password) use ($resetRequestKey): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                RateLimiter::clear($resetRequestKey);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('status', 'Your password has been reset. You can now sign in.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => match ($status) {
                    Password::INVALID_TOKEN => 'This password reset link is invalid or has expired.',
                    Password::INVALID_USER => 'We could not process this password reset request.',
                    default => 'We could not reset your password. Please request a new reset link.',
                },
            ]);
    }
}
