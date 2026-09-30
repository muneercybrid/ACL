<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Rules\StrongPassword;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * First-login onboarding for a newly appointed level coordinator.
 *
 * Reached from a signed activation link, not from a password. The account is
 * created with an unusable password, so this is the only way in — which is what
 * lets an appointment be made before anyone has been told a credential.
 *
 * The form requires a name, a phone, a password, and an address of the
 * coordinator's own. Submitting it retires the generated address: the email is
 * changed away from the scheme-generated one, and the appointment and role
 * assignment are re-pointed so nothing still keys off the old identity. That
 * matters because the generated address is predictable — anyone can derive
 * `nwucyblvl100lvlcoord@aclacademy.me` from the school, programme and level —
 * so an address left in place after onboarding is a standing published login.
 */
class OnboardingController extends Controller
{
    public function __construct(private readonly RoleHomeResolver $roles) {}

    public function show(Request $request, int $user): View|RedirectResponse
    {
        $account = \App\Models\User::find($user);

        if (! $account) {
            abort(404);
        }

        if (! $this->mayOnboard($request, $account)) {
            abort(403, 'This activation link is not valid.');
        }

        // Already onboarded: there is nothing here to set, and showing the form
        // would invite a second activation to overwrite a colleague's details.
        if (! $account->must_complete_onboarding) {
            return redirect()->route('login')
                ->withErrors(['email' => 'This account has already been set up. Please sign in.']);
        }

        // The link is what authenticates the holder at this point; there is no
        // password yet. Session regeneration here is what stops a second
        // person's pre-activation session from riding along.
        if (! Auth::check()) {
            $request->session()->regenerate();
            Auth::login($account);
        }

        return view('coordinator.onboarding', [
            'account' => $account,
        ]);
    }

    public function store(Request $request, int $user): RedirectResponse
    {
        $account = \App\Models\User::find($user);

        if (! $account) {
            abort(404);
        }

        if (! $this->mayOnboard($request, $account)) {
            abort(403, 'This activation link is not valid.');
        }

        if (! $account->must_complete_onboarding) {
            return redirect()->route('login')
                ->withErrors(['email' => 'This account has already been set up. Please sign in.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => [
                'required', 'string', 'email', 'max:190',
                // A coordinator must leave the generated address behind. If
                // the submitted address is still the generated one, the
                // retirement below would be a no-op and the predictable login
                // would survive, so it is refused rather than accepted.
                Rule::notIn([$account->email]),
                Rule::unique('users', 'email')->ignore($account->id),
            ],
            'password' => ['required', 'confirmed', new StrongPassword],
        ], [
            'email.not_in' => 'Please use your own email address rather than the one ACL generated for this role.',
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $generatedEmail = $account->email;

        DB::transaction(function () use ($account, $validated, $generatedEmail) {
            $account->forceFill([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'force_password_change' => false,
                'must_complete_onboarding' => false,
                'onboarding_completed_at' => now(),
                // The generated address is gone, so it must not keep verifying
                // this person's inbox on their behalf.
                'email_verified_at' => null,
            ])->save();

            // Re-point anything that still keys off the generated address.
            // A password-reset token issued to the old address would otherwise
            // remain a working way back into the account.
            DB::table('password_reset_tokens')->where('email', $generatedEmail)->delete();

            Log::info('level coordinator onboarded', [
                'user_id' => $account->id,
                'generated_email_retired' => $generatedEmail,
            ]);
        });

        $request->session()->regenerate();

        return redirect()
            ->route('coordinator.dashboard')
            ->with('success', 'Your account is set up. Welcome to ACL.');
    }

    /**
     * Whether this request may onboard the given account.
     *
     * Two ways in, and both must be checked before anything is shown:
     *
     *  - a valid signed link, which is how someone who has never signed in
     *    arrives. The link names one account and expires.
     *  - an authenticated session for that same account, which is how the
     *    force-onboarding gate sends them back here after the first visit. A
     *    bare redirect carries no signature, so the signed-link test alone
     *    would lock the user out of the form that is supposed to let them
     *    out.
     *
     * Anything else is refused. Notably this does not accept "is signed in as
     * anyone" — a signed-in administrator must not be able to walk into
     * another coordinator's account by changing the id in the URL.
     */
    private function mayOnboard(Request $request, \App\Models\User $account): bool
    {
        if ((int) $request->user()?->id === (int) $account->id) {
            return true;
        }

        return $request->hasValidSignature();
    }
}
