<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Confirms an email address from the link in the verification mail.
 *
 * The route sits behind the 'signed' middleware, so Laravel has already
 * rejected a link that was altered, forged or has expired by the time this
 * runs. What remains is to find the account the signature refers to and mark
 * it confirmed.
 */
class VerificationConfirmController extends Controller
{
    public function confirm(Request $request, int $user)
    {
        $account = User::find($user);

        // The signature is valid, so this should not happen; an account deleted
        // between mailing and clicking is the realistic case. Either way the
        // student gets a readable page rather than an error.
        if (! $account) {
            return response()->view('student.verification-success', [
                'student' => null,
                'alreadyConfirmed' => false,
            ]);
        }

        $alreadyConfirmed = $account->email_verified_at !== null;

        if ($alreadyConfirmed) {
            // Someone followed an old mail from a second device. Confirming it
            // again must not silently sign a different person in on a shared
            // machine, so the receipt is shown and nobody is logged in.
            return response()->view('student.verification-success', [
                'student' => (object) [
                    'user' => $account,
                    'level' => $account->level ?? 'N/A',
                ],
                'alreadyConfirmed' => true,
            ]);
        }

        $account->email_verified_at = now();
        $account->save();

        // Reaching this point means the person clicked a link that was signed by
        // us, addressed to this account, inside a mailbox only they can read.
        // That is the same proof a magic link relies on, so it is reasonable to
        // sign them in — otherwise they are confirmed but then have to type
        // their password to see the dashboard that just unlocked.
        //
        // The session id is regenerated first: without it, a session id captured
        // before sign-in would stay valid afterwards, which is session fixation.
        // Guarded so a student already signed in on this device keeps their own
        // session rather than being switched to the verified account.
        if (! Auth::check()) {
            $request->session()->regenerate();
            Auth::login($account);
        }

        return redirect()
            ->route('student.dashboard')
            ->with('success', 'Your email address is confirmed. Welcome to ACL.');
    }
}
