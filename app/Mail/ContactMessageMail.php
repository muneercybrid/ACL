<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A message sent from the public contact form.
 *
 * The reply-to is the submitter, so hitting Reply in the inbox reaches them
 * without the owner having to copy an address across.
 */
class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $subjectLine,
        public readonly string $body,
        public readonly ?string $ip = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // Reply goes to the person who wrote in, not to a no-reply address.
            replyTo: [$this->email],
            subject: '[ACL contact] ' . $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-message',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'subject' => $this->subjectLine,
                'body' => $this->body,
                'ip' => $this->ip,
            ],
        );
    }

    /**
     * No attachments and no Markdown, so an HTML-only message is enough and
     * there is no path for a filename to be interpreted.
     */
    public function attachments(): array
    {
        return [];
    }
}
