<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly RoleHomeResolver $roles) {}

    public function create(): View
    {
        return view('auth.login', [
            'selectableRoles' => RoleHomeResolver::SELECTABLE,
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $requestedRole = $request->input('role');

        $request->authenticate();

        $user = auth()->user();

        // The role on the form is a claim, not a grant. If the account does not
        // actually hold the role that was picked, the sign-in is refused rather
        // than quietly redirected somewhere else — otherwise a student could
        // pick "Level Coordinator" and be told they had reached one.
        if ($requestedRole && ! $this->roles->holdsRole($user, $requestedRole)) {
            // Sign out again: the credentials were real, but the intent was not
            // one this account is entitled to, and leaving the session open
            // would mean the refusal was only cosmetic.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'role' => 'Those sign-in details are not registered for that role. '
                    .'Check the role you selected, or sign in without choosing one.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->roles->homeFor($user, $requestedRole));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
