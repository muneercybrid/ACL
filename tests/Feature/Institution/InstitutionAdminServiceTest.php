<?php

namespace Tests\Feature\Institution;

use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Institution\InstitutionAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * InstitutionAdminService — provisionAdmin() and findAdmin().
 *
 * Pins the roleAssignments → role two-hop relationship used by the service,
 * preventing a regression to the invalid User::roles() call that caused the
 * BadMethodCallException in production.
 */
class InstitutionAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    private InstitutionAdminService $service;
    private Organization $organization;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->service = new InstitutionAdminService();
        $this->organization = Organization::firstOrFail();

        $this->user = User::create([
            'name' => 'Test Institution Admin',
            'email' => 'test.admin@acl.local',
            'password' => 'secret-password',
        ]);
    }

    public function test_provision_admin_creates_scoped_role_assignment(): void
    {
        $this->service->provisionAdmin($this->organization, $this->user);

        $this->assertDatabaseHas('role_assignments', [
            'user_id' => $this->user->id,
            'entity_type' => Organization::class,
            'entity_id' => $this->organization->id,
        ]);

        $this->assertTrue($this->user->isInstitutionAdmin($this->organization));
    }

    public function test_provision_admin_does_not_grant_platform_wide_role(): void
    {
        $this->service->provisionAdmin($this->organization, $this->user);

        // A scoped assignment must never satisfy a platform-wide check.
        $this->assertFalse($this->user->isInstitutionAdmin());
        $this->assertFalse($this->user->isSuperadmin());
    }

    public function test_provision_admin_creates_administrator_membership(): void
    {
        $this->service->provisionAdmin($this->organization, $this->user);

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'membership_type' => 'administrator',
            'status' => 'active',
        ]);
    }

    public function test_provision_admin_is_idempotent(): void
    {
        $this->service->provisionAdmin($this->organization, $this->user);
        $this->service->provisionAdmin($this->organization, $this->user);

        $count = RoleAssignment::where('user_id', $this->user->id)
            ->where('entity_type', Organization::class)
            ->where('entity_id', $this->organization->id)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_find_admin_returns_provisioned_user(): void
    {
        $this->service->provisionAdmin($this->organization, $this->user);

        $found = $this->service->findAdmin($this->organization);

        $this->assertNotNull($found);
        $this->assertEquals($this->user->id, $found->id);
    }

    public function test_find_admin_returns_null_when_no_admin_exists(): void
    {
        $found = $this->service->findAdmin($this->organization);

        // The seeded demo admin belongs to the organization — check against a fresh org.
        $fresh = Organization::create([
            'name' => 'Fresh Org',
            'slug' => 'fresh-org',
            'type' => 'university',
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->assertNull($this->service->findAdmin($fresh));
    }

}
