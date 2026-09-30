<?php

namespace Tests\Feature;

use App\Mail\VerificationSuccessMail;
use App\Models\StudentRegistrationVerification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The link in the verification mail.
 *
 * It is signed and expiring, so these tests pin three things: a genuine link
 * confirms the account and clears the dashboard reminder, a tampered link is
 * refused, and the page renders for a guest because the link is followed from
 * an inbox where nobody is signed in.
 */
class EmailVerificationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_confirm_their_address_from_the_emailed_link(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('email.verify.confirm', now()->addDays(7), ['user' => $user->id]);

        // A genuine link confirms the account and sends the student onward to
        // the dashboard that just unlocked for them.
        $this->get($url)->assertRedirect(route('student.dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_a_link_with_a_stripped_signature_is_refused(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->get('/verify-email-confirm/' . $user->id)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_signature_for_one_account_cannot_confirm_another(): void
    {
        $attacker = User::factory()->create(['email_verified_at' => null]);
        $victim = User::factory()->create(['email_verified_at' => null]);

        // A genuine, correctly signed link — but issued for a different account.
        // Changing the id in the URL invalidates the signature, so the swap
        // fails even though the link format is real.
        $url = URL::temporarySignedRoute('email.verify.confirm', now()->addDays(7), ['user' => $attacker->id]);
        $swapped = str_replace(
            '/verify-email-confirm/' . $attacker->id,
            '/verify-email-confirm/' . $victim->id,
            $url,
        );

        $this->get($swapped)->assertForbidden();

        $this->assertNull($victim->fresh()->email_verified_at, 'One account must not be confirmed with another account\'s link.');
    }

    public function test_an_expired_link_is_refused(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('email.verify.confirm', now()->subDay(), ['user' => $user->id]);

        $this->get($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_clicking_twice_is_safe(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('email.verify.confirm', now()->addDays(7), ['user' => $user->id]);

        $this->get($url)->assertRedirect();
        $first = $user->fresh()->email_verified_at;

        $this->get($url)->assertOk();

        $this->assertEquals($first, $user->fresh()->email_verified_at);
    }

    public function test_the_emailed_link_resolves_to_a_real_account(): void
    {
        $user = User::factory()->create();
        $verification = $this->verification($user->id);

        $rendered = (new VerificationSuccessMail($verification))->render();

        // The button and the fallback URL must both point at a link that
        // identifies an account, not at the candidate's name.
        $this->assertStringContainsString('/verify-email-confirm/' . $user->id, $rendered);
        $this->assertStringContainsString('signature=', $rendered);
    }

    public function test_the_email_carries_the_logo_and_a_styled_button(): void
    {
        $user = User::factory()->create();
        $verification = $this->verification($user->id);

        $rendered = (new VerificationSuccessMail($verification))->render();

        $this->assertStringContainsString('images/logo.svg', $rendered, 'The branded header logo is missing.');

        // A background colour on a padded element is what survives Gmail and
        // Outlook; the bare markdown link this replaced arrived as plain text.
        $this->assertStringContainsString('background-color:', $rendered);
        $this->assertStringContainsString('Confirm my email address', $rendered);
    }

    public function test_confirming_signs_the_student_in_and_lands_on_the_dashboard(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('email.verify.confirm', now()->addDays(7), ['user' => $user->id]);

        $response = $this->get($url);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_confirming_while_already_signed_in_keeps_the_current_session(): void
    {
        $signedIn = User::factory()->create();
        $other = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('email.verify.confirm', now()->addDays(7), ['user' => $other->id]);

        $this->actingAs($signedIn)->get($url)->assertRedirect(route('student.dashboard'));

        // The student already on this device must not be swapped to another
        // account just because they followed a link.
        $this->assertAuthenticatedAs($signedIn);
        $this->assertNotNull($other->fresh()->email_verified_at);
    }

    public function test_reclicking_an_old_link_does_not_sign_anyone_in(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $url = URL::temporarySignedRoute('email.verify.confirm', now()->addDays(7), ['user' => $user->id]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('already confirmed');
        $this->assertGuest();
    }

    public function test_the_dashboard_resend_control_is_a_working_form(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->actingAs($user)->get(route('student.dashboard'));

        $response->assertOk();
        // It used to be a bare <span> that did nothing when clicked.
        $response->assertSee('action="'.route('student.email.resend').'"', false);
    }

    public function test_resending_sends_a_fresh_link(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email_verified_at' => null]);
        $this->verification($user->id);

        $response = $this->actingAs($user)->post(route('student.email.resend'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Mail::assertSent(VerificationSuccessMail::class);
    }

    public function test_resending_is_refused_once_the_address_is_confirmed(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->verification($user->id);

        $this->actingAs($user)->post(route('student.email.resend'))
            ->assertSessionHas('info');

        Mail::assertNothingSent();
    }

    public function test_resending_is_throttled(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email_verified_at' => null]);
        $this->verification($user->id);

        // The route allows 5 per minute; the sixth is refused. Without this,
        // the button is a way to mail an inbox as fast as SMTP will accept.
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post(route('student.email.resend'))->assertRedirect();
        }

        $this->actingAs($user)->post(route('student.email.resend'))->assertStatus(429);
    }

    public function test_a_send_failure_is_reported_rather_than_claimed_as_success(): void
    {
        // No Mail::fake: a real transport failure must surface as an error.
        config(['mail.default' => 'array']);
        config(['mail.mailers.array' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1]]);

        $user = User::factory()->create(['email_verified_at' => null]);
        $this->verification($user->id);

        $response = $this->actingAs($user)->post(route('student.email.resend'));

        $response->assertSessionHas('error');
        $response->assertSessionMissing('success');
    }

    public function test_a_student_cannot_resend_for_someone_else(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email_verified_at' => null]);
        $other = User::factory()->create(['email_verified_at' => null]);

        $this->verification($user->id);
        $this->verification($other->id);

        // No user id is accepted from the request: the recipient is always the
        // authenticated account, so posting somebody else's id must not send to
        // their inbox.
        $this->actingAs($user)->post(route('student.email.resend'), ['user_id' => $other->id])
            ->assertRedirect();

        Mail::assertSent(VerificationSuccessMail::class, fn (VerificationSuccessMail $mail) => $mail->hasTo($user->email));
        Mail::assertNotSent(VerificationSuccessMail::class, fn (VerificationSuccessMail $mail) => $mail->hasTo($other->email));
    }

    public function test_resending_without_a_verification_record_reports_clearly(): void
    {
        Mail::fake();

        // Signed in, but no verified JAMB record — so there is nothing to put in
        // the mail. It must say so rather than claim a link was sent.
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)->post(route('student.email.resend'))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    /**
     * A verified candidate record. Built directly because the model has no
     * factory, and only these fields affect the mail.
     *
     * The token is a real UUID and the registration number is an encrypted
     * cast, so both have to be in their stored form.
     */
    private function verification(int $userId): StudentRegistrationVerification
    {
        $model = new StudentRegistrationVerification();
        $model->forceFill([
            'token' => (string) Str::uuid(),
            'user_id' => $userId,
            'status' => 'verified',
            'jamb_exam_year' => 2025,
            'jamb_exam_type' => 'UTME',
            'jamb_exam_value' => '38',
            'jamb_registration_number' => '202551494080CF',
            'jamb_registration_number_hash' => hash('sha256', '202551494080CF'),
            'verified_name' => 'Test Candidate',
            'verified_institution' => 'Northwest University, Kano',
            'verified_programme' => 'Cyber Security',
            'verified_at' => now(),
        ]);
        $model->save();

        return $model;
    }
}
