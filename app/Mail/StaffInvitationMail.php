<?php

namespace App\Mail;

use App\Models\InstitutionStaffInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StaffInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public InstitutionStaffInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: 'no-reply@aclacademy.me',
            subject: 'You have been invited as ' . ($this->invitation->role?->name ?? 'staff') . ' at ' . ($this->invitation->organization?->name ?? 'ACL'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.staff-invitation',
            with: [
                'invitation' => $this->invitation,
                'url' => route('invitation.show', $this->invitation->token),
            ],
        );
    }
}
