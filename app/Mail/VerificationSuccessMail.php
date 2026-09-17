<?php
namespace App\Mail;
use App\Models\StudentRegistrationVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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
            ->markdown('emails.verification-success', [
                'name' => $this->verification->verified_name,
                'institution' => $this->verification->verified_institution,
                'programme' => $this->verification->verified_programme,
            ]);
    }
}