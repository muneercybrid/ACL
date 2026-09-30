<?php

namespace Tests\Feature;

use App\Mail\VerificationSuccessMail;
use App\Models\StudentRegistrationVerification;
use App\Models\User;
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

        $this->get($url)->assertOk();

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

        $this->get($url)->assertOk();
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
