<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * The public contact channel.
 *
 * This exists because support@aclacademy.me does not currently receive mail:
 * the domain's MX points at this host and nothing listens on port 25, so
 * messages to that address are discarded after the sending server hands them
 * over. A form that posts through the application's own working SMTP path
 * reaches a real mailbox immediately, without waiting on a DNS change.
 *
 * The address is still shown to users, because it is the address they will
 * eventually reach once the MX is corrected.
 */
class ContactController extends Controller
{
    public function create(): View
    {
        return view('contact', [
            'supportAddress' => config('mail.support_address'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // 'dns' is deliberately absent: it performs a live MX lookup on every
            // submission, so a resolver hiccup or a captive network would reject
            // a genuine student. Syntax is what the form can actually vouch for.
            'email' => ['required', 'email:rfc', 'max:190'],
            'subject' => ['required', 'string', 'max:150'],
            // Bounded so one submission cannot be used to mail someone a
            // novel. The route is rate limited as well; the two are different
            // controls and both are needed.
            'message' => ['required', 'string', 'max:5000'],
            // A real person ticking a box. Absent from the validated payload,
            // so a bot that fills every field still submits an empty string.
            'website' => ['nullable', 'max:0'],
        ], [
            'website.max' => 'This submission was rejected as automated.',
        ]);

        $recipient = config('mail.contact_recipient');

        if (! $recipient) {
            report(new \RuntimeException('MAIL_CONTACT_RECIPIENT is not configured.'));

            return back()->with('error', 'Sorry, messages cannot be sent at the moment. Please try again later.');
        }

        try {
            Mail::to($recipient)->send(new ContactMessageMail(
                name: $validated['name'],
                email: $validated['email'],
                subjectLine: $validated['subject'],
                body: $validated['message'],
                ip: $request->ip(),
            ));
        } catch (\Throwable $e) {
            // Reported, and the visitor is told honestly. Silently claiming
            // success on a failed send is the same class of bug as swallowing
            // the verification-mail exception.
            report($e);

            return back()
                ->with('error', 'Sorry, your message could not be sent just now. Please try again shortly.')
                ->withInput($request->except('website'));
        }

        return redirect()->route('contact.create')
            ->with('success', 'Thank you. Your message has been sent and we will reply to the address you gave.');
    }
}
