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

    /**
     * The student sign-in.
     *
     * No role selector. Staff sign in through their own institution's door
     * (see OrganizationLoginController), which is both simpler and a smaller
     * surface: a form offering "Level Coordinator" confirmed to anyone watching
     * that the role existed and invited them to try it.
     *
     * The 'role' field is still accepted and still checked, so a stale cached
     * form cannot be used to claim a role this account does not hold.
     */
    public function create(): View
    {
        return view('auth.login');
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

        // A privileged account belongs in its own console, not wherever it was
        // last bounced from. redirect()->intended() wins over the role home, so
        // a superadmin who had touched /student while signed out was returned
        // to the student dashboard after a perfectly good sign-in -- which reads
        // as "the login did not work" and leaves them with no route to the
        // Command Center.
        //
        // Intended is still honoured for the student experience, where landing
        // back on the page they asked for is the useful behaviour.
        $home = $this->roles->homeFor($user);
        $intended = (string) $request->session()->get('url.intended', '');

        // A privileged account belongs in its own console. intended() wins over
        // the role home, so a superadmin who had touched /student while signed
        // out was returned to the student dashboard after a perfectly good
        // sign-in -- which reads as "the login did not work" and leaves them no
        // route to the Command Center. Only an intended URL that already points
        // into /superadmin is honoured.
        //
        // Students keep intended(), where landing back on the page they asked
        // for is the useful behaviour.
        if ($user->isSuperadmin() && ! str_starts_with($intended, '/superadmin')) {
            $request->session()->forget('url.intended');

            return redirect()->to($home);
        }

        return redirect()->intended($home);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
