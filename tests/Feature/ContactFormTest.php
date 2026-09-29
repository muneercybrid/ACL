<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The public contact channel.
 *
 * The reason this exists is that support@aclacademy.me cannot receive mail: the
 * domain's MX points at the application host and nothing listens on port 25,
 * so a message to that address is accepted by the sender and then discarded.
 * These tests pin the behaviour that replaces it, so a future change cannot
 * quietly reintroduce a form that claims success while delivering nothing.
 */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_contact_page_is_reachable_by_guests(): void
    {
        $this->get(route('contact.create'))->assertOk();
    }

    public function test_a_submission_is_sent_to_the_configured_recipient(): void
    {
        Mail::fake();
        config(['mail.contact_recipient' => 'owner@example.test']);

        $response = $this->post(route('contact.store'), [
            'name' => 'Ade Student',
            'email' => 'ade@example.test',
            'subject' => 'Registration problem',
            'message' => 'My JAMB verification keeps failing.',
        ]);

        $response->assertRedirect(route('contact.create'));
        $response->assertSessionHas('success');

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            // The submitter must be the reply-to, or the owner has to copy an
            // address across manually to answer.
            return $mail->hasTo('owner@example.test')
                && $mail->email === 'ade@example.test'
                && $mail->subjectLine === 'Registration problem';
        });
    }

    public function test_submissions_are_rate_limited(): void
    {
        Mail::fake();
        config(['mail.contact_recipient' => 'owner@example.test']);

        $payload = [
            'name' => 'Ade',
            'email' => 'ade@example.test',
            'subject' => 'Hello',
            'message' => 'Testing the limit.',
        ];

        // The route allows 5 per minute; the sixth is refused. Without this,
        // the form is an open relay for spamming the owner's inbox.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('contact.store'), $payload)->assertRedirect();
        }

        $this->post(route('contact.store'), $payload)->assertStatus(429);
    }

    public function test_a_filled_honeypot_is_rejected(): void
    {
        Mail::fake();
        config(['mail.contact_recipient' => 'owner@example.test']);

        $this->post(route('contact.store'), [
            'name' => 'Bot',
            'email' => 'bot@example.test',
            'subject' => 'Cheap things',
            'message' => 'Buy now.',
            'website' => 'http://spam.example',
        ])->assertSessionHasErrors('website');

        Mail::assertNothingSent();
    }

    public function test_a_send_failure_is_reported_rather_than_claimed_as_success(): void
    {
        config(['mail.contact_recipient' => 'owner@example.test']);

        // A transport that cannot connect. The visitor must be told, not shown
        // a success message for a message that was never sent.
        config(['mail.default' => 'array']);
        config(['mail.mailers.array' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1]]);

        $response = $this->post(route('contact.store'), [
            'name' => 'Ade',
            'email' => 'ade@example.test',
            'subject' => 'Hello',
            'message' => 'Will this fail?',
        ]);

        $response->assertSessionHas('error');
        $response->assertSessionMissing('success');
    }

    public function test_the_public_support_address_is_shown_but_not_used_for_delivery(): void
    {
        Mail::fake();
        config(['mail.contact_recipient' => 'owner@example.test', 'mail.support_address' => 'support@aclacademy.me']);

        $this->get(route('contact.create'))
            ->assertOk()
            ->assertSee('support@aclacademy.me');

        // And it is never a delivery target, because it cannot receive mail.
        $this->post(route('contact.store'), [
            'name' => 'Ade',
            'email' => 'ade@example.test',
            'subject' => 'Hello',
            'message' => 'Where does this go?',
        ])->assertRedirect();

        Mail::assertSent(ContactMessageMail::class, fn (ContactMessageMail $m) => $m->hasTo('owner@example.test'));
        Mail::assertNotSent(ContactMessageMail::class, fn (ContactMessageMail $m) => $m->hasTo('support@aclacademy.me'));
    }
}
