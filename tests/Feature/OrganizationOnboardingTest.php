<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Curriculum\Programme;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\InstitutionOnboarding;
use App\Models\LevelCoordinator;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\OrganizationOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The onboarding flow, as the project owner specified it:
 *
 *   1. an organization is given exactly one administrator credential
 *   2. that administrator appoints a level coordinator per programme level
 *   3. a student registering under the organization is attached to it
 *
 * These build their own organizations and users, so nothing here depends on
 * production data or on seeded fixtures that might later change.
 */
class OrganizationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrganization(array $attributes = []): Organization
    {
        static $counter = 0;
        $counter++;
        $name = $attributes['name'] ?? "Probe University {$counter}, Somewhere";

        // normalized_name is derived from the resolved name, and placed after
        // $attributes in the merge so a caller-supplied name cannot be paired
        // with a stale normalized_name and collide on the unique index.
        return Organization::create(array_merge([
            'type' => 'University',
            'is_active' => true,
            'is_nuc_listed' => true,
            'status' => 'active',
        ], $attributes, [
            'name' => $name,
            'normalized_name' => mb_strtolower(trim($name)),
            'slug' => 'probe-org-'.uniqid(),
        ]));
    }

    private function makeDepartment(Organization $organization): Department
    {
        $faculty = Faculty::create([
            'organization_id' => $organization->id,
            'name' => 'Probe Faculty of Science',
            'slug' => 'probe-faculty-'.uniqid(),
            'is_active' => true,
        ]);

        return Department::create([
            'faculty_id' => $faculty->id,
            'name' => 'Probe Department of Computing',
            'slug' => 'probe-department-'.uniqid(),
            'is_active' => true,
        ]);
    }

    public function test_organization_is_issued_exactly_one_administrator(): void
    {
        $organization = $this->makeOrganization();

        $result = OrganizationOnboarding::issueAdministrator($organization);

        $this->assertTrue($result['created']);
        $this->assertNotEmpty($result['password'], 'a generated credential must be returned to the operator');
        $this->assertSame($organization->id, $result['user']->institution_id);

        // The administrator must be bound to the organization, and only it.
        $this->assertTrue($result['user']->isInstitutionAdmin());
        $this->assertFalse($result['user']->isInstitutionAdmin($this->makeOrganization()));

        // Issued as a temporary credential, so the holder is forced to replace it.
        $this->assertTrue((bool) $result['user']->force_password_change);

        // The scope of the role assignment is the organization itself.
        $assignment = RoleAssignment::where('user_id', $result['user']->id)
            ->where('role_id', Role::where('slug', 'institution.admin')->value('id'))
            ->first();

        $this->assertSame(Organization::class, $assignment->entity_type);
        $this->assertSame($organization->id, $assignment->entity_id);
    }

    public function test_issuing_a_second_administrator_is_refused(): void
    {
        $organization = $this->makeOrganization();

        $first = OrganizationOnboarding::issueAdministrator($organization);
        $second = OrganizationOnboarding::issueAdministrator($organization);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created'], 'a second administrator must not strand the first');
        $this->assertSame($first['user']->id, $second['user']->id);
    }

    public function test_level_coordinator_is_scoped_to_one_level_of_one_programme(): void
    {
        $organization = $this->makeOrganization();
        $programme = Programme::create([
            'name' => 'B.Sc. Computer Science',
            'normalized_name' => 'computer science',
            'code' => 'CSC',
            'nuc_discipline_id' => 7,
            'status' => 'active',
        ]);

        $coordinator = User::factory()->create();

        $appointment = OrganizationOnboarding::appointLevelCoordinator(
            $organization, $programme, 100, $coordinator
        );

        $this->assertTrue($appointment->isActive());
        $this->assertSame(100, $appointment->level);
        $this->assertSame($programme->id, $appointment->programme_id);
        $this->assertSame($coordinator->id, $appointment->user_id);

        // The authorization scope must record the level, or a 100-level
        // coordinator would be indistinguishable from a 200-level one.
        $assignment = RoleAssignment::where('user_id', $coordinator->id)
            ->where('role_id', Role::where('slug', 'level.coordinator')->value('id'))
            ->first();

        $this->assertSame(Programme::class, $assignment->entity_type);
        $this->assertSame($programme->id, $assignment->entity_id);
        $this->assertSame('level', $assignment->scope_type);
        $this->assertSame('100', (string) $assignment->scope_id);
    }

    public function test_student_registering_is_attached_to_the_organization_they_selected(): void
    {
        $organization = $this->makeOrganization();
        $otherOrganization = $this->makeOrganization(['name' => 'Unrelated College']);
        $programme = Programme::create([
            'name' => 'B.Sc. Cyber Security',
            'normalized_name' => 'cyber security',
            'code' => 'CYS',
            'nuc_discipline_id' => 7,
            'status' => 'active',
        ]);

        $studentUser = User::factory()->create();
        $department = $this->makeDepartment($organization);

        OrganizationOnboarding::attachStudent($studentUser, $organization, $programme, $department->id);

        // Attached to exactly the organization selected — not merely attached.
        $this->assertSame($organization->id, $studentUser->fresh()->institution_id);
        $this->assertNotSame($otherOrganization->id, $studentUser->fresh()->institution_id);

        $membership = OrganizationMembership::where('user_id', $studentUser->id)
            ->where('organization_id', $organization->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame('student', $membership->membership_type);
        $this->assertSame('active', $membership->status);
        // The membership must point at this organization's *offering* of the
        // programme, not at the central catalogue row.
        $offering = \App\Models\AcademicProgram::find($membership->academic_program_id);
        $this->assertNotNull($offering, 'membership must reference an existing academic_program');
        $this->assertSame($organization->id, $offering->organization_id);
        $this->assertSame($programme->id, $offering->nuc_programme_id);

        // A student record exists and carries ACL's own identifier.
        $this->assertNotNull($studentUser->fresh()->student);
        $this->assertNotEmpty($studentUser->fresh()->student->acl_student_id);
    }

    public function test_issued_student_identifiers_are_unique(): void
    {
        $seen = [];

        for ($i = 0; $i < 25; $i++) {
            $seen[] = OrganizationOnboarding::issueStudentIdentifier();
        }

        $this->assertCount(25, array_unique($seen));
    }

    public function test_summary_reports_onboarding_state(): void
    {
        $organization = $this->makeOrganization();
        OrganizationOnboarding::issueAdministrator($organization);

        $summary = OrganizationOnboarding::summary($organization);

        $this->assertSame('invited', $summary['status']);
        $this->assertNotNull($summary['administrator']);
        $this->assertSame(0, $summary['students']);
    }
}
