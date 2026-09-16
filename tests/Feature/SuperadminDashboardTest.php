<?php

namespace Tests\Feature;

use App\Models\InstitutionOnboarding;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\SuperadminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Superadmin Command Center authorization and rendering.
 *
 * Pins the platform-wide superadmin role against the scoped institution-admin
 * role: superadmin reaches every command-center page; institution admins get
 * 403 even when they manually request the URLs (hidden links are not a
 * security control).
 */
class SuperadminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $institutionAdmin;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->organization = Organization::firstOrFail();
        $this->institutionAdmin = User::where('email', 'admin@acl.local')->firstOrFail();

        // Platform-wide superadmin assignment.
        $this->superadmin = User::create([
            'name' => 'Test Superadmin',
            'email' => 'superadmin.test@acl.local',
            'password' => 'secret-password',
        ]);

        RoleAssignment::create([
            'user_id' => $this->superadmin->id,
            'role_id' => Role::where('slug', 'superadmin')->firstOrFail()->id,
            'entity_type' => null,
            'entity_id' => null,
        ]);
    }

    public function test_superadmin_role_is_platform_wide(): void
    {
        $this->assertTrue($this->superadmin->isSuperadmin());
        $this->assertTrue($this->superadmin->hasRole('superadmin'));
        $this->assertTrue($this->superadmin->hasPermission('institutions.manage.all'));
    }

    public function test_institution_admin_is_not_superadmin(): void
    {
        $this->assertFalse($this->institutionAdmin->isSuperadmin());
        $this->assertFalse($this->institutionAdmin->hasPermission('institutions.manage.all'));
    }

    public function test_guest_is_redirected_from_command_center(): void
    {
        $this->get('/superadmin')->assertRedirect('/login');
    }

    public function test_institution_admin_is_denied_command_center(): void
    {
        $this->actingAs($this->institutionAdmin)
            ->get('/superadmin')
            ->assertForbidden();

        $this->actingAs($this->institutionAdmin)
            ->get('/superadmin/institutions')
            ->assertForbidden();

        $this->actingAs($this->institutionAdmin)
            ->get('/superadmin/users')
            ->assertForbidden();
    }

    public function test_superadmin_reaches_command_center_pages(): void
    {
        $this->actingAs($this->superadmin)
            ->get('/superadmin')
            ->assertOk()
            ->assertSee('Command Center');

        $this->actingAs($this->superadmin)
            ->get('/superadmin/institutions')
            ->assertOk();

        $this->actingAs($this->superadmin)
            ->get('/superadmin/users')
            ->assertOk();

        $this->actingAs($this->superadmin)
            ->get('/superadmin/audit')
            ->assertOk();

        $this->actingAs($this->superadmin)
            ->get('/superadmin/academic')
            ->assertOk();

        $this->actingAs($this->superadmin)
            ->get('/superadmin/roles')
            ->assertOk();

        $this->actingAs($this->superadmin)
            ->get('/superadmin/search')
            ->assertOk();

        $this->actingAs($this->superadmin)
            ->get('/superadmin/system')
            ->assertOk();
    }

    public function test_superadmin_views_institution_detail_and_onboarding(): void
    {
        InstitutionOnboarding::create([
            'organization_id' => $this->organization->id,
            'status' => 'completed',
            'progress' => ['institution_info' => true, 'admin_profile' => true, 'academic_structure' => true, 'staff_setup' => true, 'content_setup' => true],
        ]);

        $this->actingAs($this->superadmin)
            ->get('/superadmin/institutions/' . $this->organization->id)
            ->assertOk()
            ->assertSee($this->organization->name);

        $this->actingAs($this->superadmin)
            ->get('/superadmin/onboarding')
            ->assertOk();

        $this->actingAs($this->superadmin)
            ->get('/superadmin/onboarding/' . $this->organization->id)
            ->assertOk()
            ->assertSee('Onboarding');
    }

    public function test_superadmin_audit_logs_institution_creation(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/superadmin/institutions', [
                'name' => 'Test University of Example',
                'type' => 'university',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('organizations', ['name' => 'Test University of Example']);
        $this->assertDatabaseHas('superadmin_audit_logs', [
            'action' => 'institution.created',
            'actor_id' => $this->superadmin->id,
        ]);
    }

    public function test_superadmin_assigns_institution_administrator_with_scoped_role(): void
    {
        $target = User::create([
            'name' => 'New Institution Admin',
            'email' => 'newadmin@acl.local',
            'password' => 'secret-password',
        ]);

        $this->actingAs($this->superadmin)
            ->post('/superadmin/institutions/' . $this->organization->id . '/assign-admin', [
                'user_id' => $target->id,
            ])
            ->assertRedirect();

        // The assignment must be scoped to the organization — never platform-wide.
        $this->assertDatabaseHas('role_assignments', [
            'user_id' => $target->id,
            'entity_type' => Organization::class,
            'entity_id' => $this->organization->id,
        ]);

        $this->assertFalse($target->isSuperadmin());
        $this->assertTrue($target->isInstitutionAdmin($this->organization));
        $this->assertFalse($target->isInstitutionAdmin());
    }

    public function test_institution_admin_cannot_toggle_institution(): void
    {
        $this->actingAs($this->institutionAdmin)
            ->post('/superadmin/institutions/' . $this->organization->id . '/toggle')
            ->assertForbidden();
    }

    public function test_superadmin_staff_invitation_creates_scoped_assignment_for_existing_user(): void
    {
        $staff = User::create([
            'name' => 'Test Moderator',
            'email' => 'moderator.test@acl.local',
            'password' => 'secret-password',
        ]);

        $this->actingAs($this->superadmin)
            ->post('/superadmin/staff/invite', [
                'email' => $staff->email,
                'organization_id' => $this->organization->id,
                'role_slug' => 'moderator',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('role_assignments', [
            'user_id' => $staff->id,
            'entity_type' => Organization::class,
            'entity_id' => $this->organization->id,
        ]);

        $this->assertTrue($staff->hasPermission('content.approve', $this->organization));
        $this->assertFalse($staff->hasPermission('content.approve'));
    }

    public function test_staff_invitation_for_new_user_creates_pending_invitation(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/superadmin/staff/invite', [
                'email' => 'brand.new@acl.local',
                'organization_id' => $this->organization->id,
                'role_slug' => 'level.coordinator',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('institution_staff_invitations', [
            'email' => 'brand.new@acl.local',
            'role_slug' => 'level.coordinator',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_audit_event_details_are_visible(): void
    {
        $log = SuperadminAuditLog::log([
            'action' => 'institution.updated',
            'resource_type' => Organization::class,
            'resource_id' => $this->organization->id,
            'organization_id' => $this->organization->id,
            'description' => 'Test audit event',
            'new_values' => ['name' => 'Renamed'],
        ]);

        $this->actingAs($this->superadmin)
            ->get('/superadmin/audit/' . $log->id)
            ->assertOk()
            ->assertSee('Test audit event');
    }

    public function test_global_search_returns_matching_users(): void
    {
        $this->actingAs($this->superadmin)
            ->get('/superadmin/search?q=' . urlencode($this->superadmin->name))
            ->assertOk()
            ->assertSee($this->superadmin->name);
    }
}