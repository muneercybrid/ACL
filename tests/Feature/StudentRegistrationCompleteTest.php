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

/**
 * The final step of JAMB student registration.
 *
 * A verification is not always tied to an institution. Those students must
 * still get an account and a platform-scoped student role, and no membership
 * row (organization_id is non-nullable).
 */
class StudentRegistrationCompleteTest extends TestCase
{
    use RefreshDatabase;

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
        $state = State::create(['name' => 'Kaduna '.Str::random(4)]);
        $lga = Lga::create(['state_id' => $state->id, 'name' => 'Zaria']);

        return $this->withSession(['student_verification_token' => $verification->token])
            ->post(route('register.student.complete'), [
                'email' => 'student.'.Str::lower(Str::random(6)).'@acl.test',
                'phone' => '07073998056',
                'nationality' => 'Nigerian',
                'state_id' => $state->id,
                'lga_id' => $lga->id,
                'school_registration_number' => 'U26/'.Str::random(5),
                'level' => 100,
                'terms_accepted' => 'on',
                'password' => 'Str0ng!Passw0rd#2026',
                'password_confirmation' => 'Str0ng!Passw0rd#2026',
            ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'scope_level' => 'organization'],
        );
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
