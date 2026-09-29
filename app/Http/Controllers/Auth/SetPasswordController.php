<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Lets an authenticated user set a password of their own choosing.
 *
 * Why this exists
 * ---------------
 * Institution administrators are provisioned with a generated password and are
 * flagged `force_password_change`. Until this route existed there was nowhere
 * for such a user to act on that flag: the only password flow in ACL was
 * `forgot-password`, which requires a working mailer and a token round trip
 * through a mailbox the platform does not control. Every provisioned
 * administrator was therefore unable to replace the generated credential.
 *
 * The generated password is never displayed and never emailed, so this screen
 * is the only way it can be replaced.
 */
class SetPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.set-password', [
            // A minimum the user can realistically satisfy, plus the
            // confirmation the interface asks for.
            'minimum' => 12,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()->mixedCase()],
            'current_password' => ['nullable', 'string'],
        ], [
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        // Only enforce the current password when there is something to enforce
        // it against. A freshly provisioned account has a generated credential
        // the user was never shown, so requiring it would lock them out; every
        // account that has set its own password must supply it.
        if ($user->force_password_change === false && ! empty($validated['current_password'] ?? null)) {
            if (! Hash::check($validated['current_password'], (string) $user->password)) {
                return back()
                    ->withErrors(['current_password' => 'That is not your current password.'])
                    ->withInput($request->only('current_password'));
            }
        } elseif ($user->force_password_change === false) {
            return back()->withErrors([
                'current_password' => 'Please confirm your current password.',
            ]);
        }

        $user->force_password_change = false;
        $user->password = Hash::make($validated['password']);
        $user->save();

        // The session id is rotated so a credential that was exposed with the
        // generated password cannot keep the old session alive.
        $request->session()->regenerate();

        Log::info('password set by user', [
            'user_id' => $user->id,
            'institution_id' => $user->institution_id,
        ]);

        return redirect()
            ->intended(route('superadmin.dashboard'))
            ->with('status', 'Your password has been set.');
    }
}
