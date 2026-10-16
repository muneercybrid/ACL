<?php

namespace Tests\Feature;

use App\Models\Lga;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\State;
use App\Models\StudentRegistrationVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentRegistrationCompleteTest extends TestCase
{
    use RefreshDatabase;

    private StudentRegistrationVerification $verification;

    private Lga $lga;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'scope_level' => 'platform', 'is_system' => true],
        );

        $state = State::create(['name' => 'Kano', 'code' => 'KN']);
        $this->lga = Lga::create(['state_id' => $state->id, 'name' => 'Nassarawa']);

        $this->verification = StudentRegistrationVerification::create([
            'token' => Str::random(64),
            'status' => 'verified',
            'jamb_exam_year' => 2024,
            'jamb_registration_number_hash' => hash('sha256', 'JAMB-TEST-1'),
            'verified_name' => 'Test Student',
            'verified_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
    }

    private function verification(?int $organizationId): StudentRegistrationVerification
    {
        return StudentRegistrationVerification::create([
            'token' => Str::random(40),
            'status' => 'verified',
            'jamb_exam_year' => '2026',
            'jamb_registration_number' => 'JAMB'.Str::random(8),
            'jamb_registration_number_hash' => hash('sha256', Str::random(16)),
            'verified_name' => 'Test Student',
            'organization_id' => $organizationId,
            'verified_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
    }

    private function submit(StudentRegistrationVerification $verification)
    {
        return $this->withSession(['student_verification_token' => $verification->token])
            ->post(route('register.student.complete'), [
                'email' => 'student.'.Str::lower(Str::random(6)).'@acl.test',
                'phone' => '07073998056',
                'nationality' => 'Nigerian',
                'state_id' => $this->lga->state_id,
                'lga_id' => $this->lga->id,
                'school_registration_number' => 'U26/'.Str::random(5),
                'level' => 100,
                'terms_accepted' => 'on',
                'password' => 'Str0ng!Passw0rd#2026',
                'password_confirmation' => 'Str0ng!Passw0rd#2026',
            ]);
    }

    private function payload(string $email): array
    {
        return [
            'email' => $email,
            'phone' => '07000000000',
            'nationality' => 'Nigerian',
            'state_id' => $this->lga->state_id,
            'lga_id' => $this->lga->id,
            'level' => 100,
            'terms_accepted' => 'on',
            'password' => 'Str0ng!Passw0rd#2026',
            'password_confirmation' => 'Str0ng!Passw0rd#2026',
        ];
    }

    public function test_repeat_submission_for_a_completed_verification_does_not_create_a_second_user(): void
    {
        $existing = User::factory()->create(['email' => 'student@example.com']);
        $this->verification->update(['user_id' => $existing->id]);

        // Simulates the second request of a double-submit: validation is
        // bypassed for the email by using a fresh address, but the locked
        // verification row already points at the first request's user.
        $this->withSession(['student_verification_token' => $this->verification->token])
            ->post(route('register.student.complete'), $this->payload('another@example.com'))
            ->assertRedirect(route('login'));

        $this->assertSame(1, User::count());
    }

    public function test_already_registered_email_returns_a_validation_error_not_an_exception(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->withSession(['student_verification_token' => $this->verification->token])
            ->post(route('register.student.complete'), $this->payload('taken@example.com'))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_a_verification_without_an_institution_completes_registration(): void
    {
        $verification = $this->verification(null);

        $this->submit($verification)->assertRedirect(route('student.dashboard'));

        $user = User::where('name', 'Test Student')->firstOrFail();

        $assignment = RoleAssignment::where('user_id', $user->id)->firstOrFail();
        $this->assertNull($assignment->entity_id);
        $this->assertSame(0, OrganizationMembership::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('students', ['user_id' => $user->id]);
    }

    public function test_a_verification_with_an_institution_creates_a_membership(): void
    {
        $organization = Organization::create([
            'name' => 'Test University',
            'slug' => 'test-university-'.Str::lower(Str::random(4)),
        ]);
        $verification = $this->verification($organization->id);

        $this->submit($verification)->assertRedirect(route('student.dashboard'));

        $user = User::where('name', 'Test Student')->firstOrFail();

        $this->assertDatabaseHas('role_assignments', [
            'user_id' => $user->id,
            'entity_id' => $organization->id,
        ]);
        $this->assertDatabaseHas('organization_memberships', [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'membership_type' => 'student',
            'status' => 'active',
        ]);
    }
}
