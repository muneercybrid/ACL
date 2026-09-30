<?php

namespace App\Mail;

use App\Models\StudentRegistrationVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\URL;
use Illuminate\Queue\SerializesModels;

class VerificationSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $verification;

    public function __construct(StudentRegistrationVerification $verification)
    {
        $this->verification = $verification;
    }

    public function build(): self
    {
        return $this->subject('Welcome to ACL — Your account is verified')
            // A view rather than markdown: the branded layout, the logo and the
            // CTA button are all HTML, and markdown() would escape them.
            ->view('emails.verification-success', [
                'name' => $this->verification->verified_name,
                'institution' => $this->verification->verified_institution,
                'programme' => $this->verification->verified_programme,
                'verify_url' => $this->verifyUrl(),
            ]);
    }

    /**
     * A signed, expiring link bound to the account.
     *
     * Two things were wrong with the previous link. It carried the candidate's
     * name, which matches no user, so clicking it could never confirm anything;
     * and it identified the account in plain text, so anyone could hand-edit a
     * URL and confirm somebody else's address, or pre-confirm an account before
     * its owner proved they own the mailbox.
     *
     * A temporary signed URL on the user id fixes both: the link resolves to a
     * real account, and the signature means the link is only honoured if it came
     * from us and has not expired.
     */
    private function verifyUrl(): string
    {
        $userId = $this->verification->user_id;

        if (! $userId) {
            // No account to bind to yet. The dashboard keeps showing the
            // reminder, and the student is told to contact their institution.
            return url('/contact');
        }

        return URL::temporarySignedRoute(
            'email.verify.confirm',
            now()->addDays(7),
            ['user' => $userId],
        );
    }
}
