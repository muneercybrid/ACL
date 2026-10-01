<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A dedicated sign-in for the platform Superadmin.
 *
 * The general login sends a privileged account wherever it was last bounced
 * from, which for an administrator trying to reach the Command Center is the
 * student area. It also offers a role selector meant for institutional staff,
 * which has no bearing on a platform administrator and only adds a way to fail.
 *
 * This route is deliberately narrow: it will only authenticate an account that
 * actually holds the superadmin role. Anyone else is refused with the same
 * message whether the account exists, the password is wrong, or the account is
 * a perfectly valid student -- so this page cannot be used to discover which
 * email addresses exist or to tell a wrong password from a wrong role.
 */
class SuperadminLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.superadmin-login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'superadmin-login|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $user = \App\Models\User::where('email', $credentials['email'])->first();

        // One branch for every failure. Verifying the password even when the
        // account is absent keeps the timing comparable, so this page does not
        // become a way to enumerate valid addresses.
        $passwordMatches = $user
            ? \Illuminate\Support\Facades\Hash::check($credentials['password'], $user->password)
            : \Illuminate\Support\Facades\Hash::check($credentials['password'], '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');

        if (! $user || ! $passwordMatches || ! $user->isSuperadmin()) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Those sign-in details are not registered for the Superadmin console.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // A fresh session id at the privilege boundary, so a session id captured
        // before sign-in cannot be reused after it.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('superadmin.dashboard');
    }
}
