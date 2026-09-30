<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The organizational door: an institution administrator or level coordinator
 * signs in through their own institution rather than through a generic form
 * that offers every role.
 *
 * Two properties matter more than the appearance of the page.
 *
 * First, there is no role selector. The previous screen asked the visitor to
 * declare which role they were, and that declaration was itself a question
 * worth answering — a form offering "Level Coordinator" confirms to anyone
 * watching that the role exists. Dropping it removes that enumeration surface,
 * and the account's own roles decide where they land afterwards.
 *
 * Second, and less obviously: selecting an organization must not become a way
 * to test whether a given email works for it. Every refusal below — wrong
 * password, right password but wrong institution, an account with no staff
 * role at all — returns the same words. If they differed, this screen would be
 * an oracle for discovering which administrators and coordinators belong to
 * which university, which is exactly the roster ACL's scope rules exist to
 * keep private.
 */
class OrganizationLoginController extends Controller
{
    /** Deliberately identical for every failure. */
    private const REFUSAL = 'Those sign-in details were not recognised for this institution.';

    public function __construct(private readonly RoleHomeResolver $roles) {}

    /**
     * Step one: choose the institution.
     */
    public function select(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $organizations = Organization::query()
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $like = '%'.str_replace('%', '\%', $search).'%';
                    $inner->where('name', 'like', $like)
                        ->orWhere('short_name', 'like', $like)
                        ->orWhere('state', 'like', $like);
                });
            })
            // The set of institutions is public information anyway — it is the
            // same set the landing page describes — but it is bounded so this
            // page stays cheap on a low-bandwidth connection.
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'short_name', 'logo_path', 'state']);

        return view('auth.organization-select', [
            'organizations' => $organizations,
            'search' => $search,
        ]);
    }

    /**
     * Step two: this institution's logo, and the credentials box.
     */
    public function show(Organization $organization): View
    {
        abort_if(! $organization->is_active, 404);

        return view('auth.organization-login', [
            'organization' => $organization,
        ]);
    }

    /**
     * Step two, submitted.
     */
    public function store(Request $request, Organization $organization): RedirectResponse
    {
        abort_if(! $organization->is_active, 404);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable'],
        ]);

        $throttleKey = $this->throttleKey($request, $organization);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Please wait '
                    .RateLimiter::availableIn($throttleKey).' seconds and try again.',
            ]);
        }

        // Look the account up first so the scope check can run against a real
        // user without an authentication event. Both steps below are reported
        // with the same message, so this does not become a probe.
        $user = Auth::getProvider()->retrieveByCredentials([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ]);

        $authenticated = $user && Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            $request->boolean('remember'),
        );

        // Refuse unless BOTH the credentials were good AND the account belongs
        // to this institution. A valid staff member signing in through the
        // wrong organization's door lands here too, indistinguishably.
        if (! $authenticated || ! $this->roles->canAccessOrganization($user, $organization)) {
            // Any session the attempt opened must be closed again, or the
            // refusal would be cosmetic.
            Auth::guard('web')->logout();

            RateLimiter::hit($throttleKey, 300);

            throw ValidationException::withMessages(['email' => self::REFUSAL]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        return redirect()->intended($this->roles->homeFor($user));
    }

    /**
     * Throttled per address and per institution together.
     *
     * Keying on the address alone would let one attacker lock a real
     * administrator out of their own institution by guessing from elsewhere;
     * keying on the institution alone would let anyone lock an institution's
     * whole staff out at once. Both dimensions are needed.
     */
    private function throttleKey(Request $request, Organization $organization): string
    {
        $email = Str::lower((string) $request->input('email'));

        return 'org-login:'.$organization->id.':'.Str::transliterate($email).'|'.$request->ip();
    }
}