<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
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
    public function confirm(Request $request, int $user): View
    {
        $account = User::find($user);

        // The signature is valid, so this should not happen; an account deleted
        // between mailing and clicking is the realistic case. Either way the
        // student gets a readable page rather than an error.
        if (! $account) {
            return view('student.verification-success', [
                'student' => null,
                'alreadyConfirmed' => false,
            ]);
        }

        $alreadyConfirmed = $account->email_verified_at !== null;

        if (! $alreadyConfirmed) {
            $account->email_verified_at = now();
            $account->save();
        }

        return view('student.verification-success', [
            'student' => (object) [
                'user' => $account,
                'level' => $account->level ?? 'N/A',
            ],
            'alreadyConfirmed' => $alreadyConfirmed,
        ]);
    }
}
