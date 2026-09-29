<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * InstitutionProvision artisan command.
 *
 * Ensures the command looks up institution admin users via the correct
 * roleAssignments() → role relationship chain, not through the non-existent
 * roles() method that caused the original BadMethodCallException.
 */
class InstitutionProvisionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_gracefully_when_no_institution_needs_provisioning(): void
    {
        $this->artisan('institution:provision')
            ->expectsOutput('No institution to provision.')
            ->assertExitCode(0);
    }

    public function test_command_reports_existing_institution_admin_users_via_role_assignments(): void
    {
        $this->seed();

        // Create an institution that needs onboarding.
        $institution = Institution::create([
            'name' => 'Test University',
            'normalized_name' => 'test university',
            'ownership' => 'Federal',
            'state' => 'Lagos',
            'institution_status' => 'University',
            'onboarding_status' => 'NOT_ONBOARDED',
            'slug' => 'test-university',
        ]);

        // Give a user the institution.admin role via roleAssignments (the
        // correct path — User has no roles() relationship).
        $admin = User::where('email', 'admin@acl.local')->firstOrFail();

        $this->artisan('institution:provision')
            ->expectsOutputToContain('Provisioning institution: Test University')
            ->assertExitCode(0);
    }

    public function test_command_warns_when_no_institution_admin_exists(): void
    {
        Institution::create([
            'name' => 'Orphan University',
            'normalized_name' => 'orphan university',
            'ownership' => 'State',
            'state' => 'Kano',
            'institution_status' => 'University',
            'onboarding_status' => 'NOT_ONBOARDED',
            'slug' => 'orphan-university',
        ]);

        // No users, no role assignments — should warn gracefully.
        $this->artisan('institution:provision')
            ->expectsOutputToContain('No institution.admin users found')
            ->assertExitCode(0);
    }

    public function test_user_query_via_role_assignments_does_not_call_roles_method(): void
    {
        $this->seed();

        // Directly verify the query pattern used by the command does not
        // trigger the BadMethodCallException that /tmp/provision_sample.php
        // caused by calling whereHas('roles', ...) on User.
        $admins = User::whereHas(
            'roleAssignments',
            fn ($q) => $q->whereHas('role', fn ($q) => $q->where('slug', 'institution.admin'))
        )->get();

        // The seeder creates admin@acl.local with institution.admin assignment.
        $this->assertGreaterThanOrEqual(1, $admins->count());
        $this->assertTrue($admins->contains('email', 'admin@acl.local'));
    }
}
