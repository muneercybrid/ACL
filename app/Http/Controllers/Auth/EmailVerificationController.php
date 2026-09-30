<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerificationSuccessMail;
use App\Models\StudentRegistrationVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Resending the email-confirmation link.
 *
 * The dashboard showed a "Resend verification link" control that was a styled
 * <span> with nothing behind it, so a student who lost the mail had no way to
 * get a new one. This is the action it needed.
 */
class EmailVerificationController extends Controller
{
    /**
     * Issue a fresh confirmation link for the signed-in student.
     *
     * Rate limited per account: each send is an outbound SMTP call, and without
     * a limit this becomes a way to mail someone else's inbox as fast as the
     * server will go.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return back()->with('info', 'Your email address is already verified.');
        }

        $key = 'verify-resend:'.$user->id;
        $maxAttempts = 3;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->with(
                'error',
                "You have requested several links already. Please wait {$seconds} seconds and try again, or check your spam folder."
            );
        }

        RateLimiter::hit($key, 300);

        // The newest verification record for this account carries the verified
        // name, institution and programme the mail renders.
        $verification = StudentRegistrationVerification::where('user_id', $user->id)
            ->where('status', 'verified')
            ->orderByDesc('id')
            ->first();

        if (! $verification) {
            return back()->with(
                'error',
                'We could not find your verification record. Please contact your institution administrator.'
            );
        }

        try {
            Mail::to($user->email)->send(new VerificationSuccessMail($verification));
        } catch (\Throwable $e) {
            // Reported rather than swallowed: a mail outage must not read as a
            // successful resend, which is the same class of bug as claiming a
            // contact message was sent when it was not.
            report($e);

            return back()->with(
                'error',
                'We could not send the email just now. Please try again shortly, or contact your institution administrator.'
            );
        }

        return back()->with(
            'success',
            'A fresh confirmation link is on its way to '.$user->email.'. It is valid for 7 days — links you used earlier have expired, so please use this one.'
        );
    }
}
