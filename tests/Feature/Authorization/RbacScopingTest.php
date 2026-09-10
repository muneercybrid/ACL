<?php

namespace Tests\Feature\Authorization;

use App\Models\Department;
use App\Models\Level;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scoped RBAC resolution.
 *
 * The rule these lock in: a role assignment with a null entity_type is
 * platform-wide and applies everywhere, while an assignment carrying an
 * entity applies only to that exact entity -- and asking without an entity
 * asks about platform scope, which a scoped assignment must never satisfy.
 * Getting this backwards would silently widen every scoped role into a
 * platform role.
 *
 * Written against the role set introduced by the
 * 2026_09_09_142357_restructure_acl_roles_and_permissions migration. That
 * migration retired the platform.admin/super.admin roles together with the
 * Gate::before() bypass; one of these tests pins that they stay retired.
 */
class RbacScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $tutor;
    private User $student;
    private Organization $organization;
    private Organization $siblingOrganization;
    private Level $cs100Level;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@acl.local')->firstOrFail();
        $this->tutor = User::where('email', 'lecturer@acl.local')->firstOrFail();
        $this->student = User::where('email', 'student@acl.local')->firstOrFail();

        $this->organization = Organization::firstOrFail();
        $this->siblingOrganization = Organization::create([
            'name' => 'Rival University',
            'slug' => 'rival-uni',
            'type' => 'university',
            'code' => 'RVU',
            'is_active' => true,
        ]);

        $this->cs100Level = Level::query()
            ->where('code', '100')
            ->whereHas('academicProgram', fn ($query) => $query->where('slug', 'bsc-computer-science'))
            ->firstOrFail();
    }

    // --- Platform-scoped roles ---------------------------------------------

    public function test_platform_role_resolves_without_an_entity(): void
    {
        $this->assertTrue($this->tutor->hasRole('tutor'));
    }

    public function test_platform_role_applies_to_every_entity(): void
    {
        $this->assertTrue($this->tutor->hasRole('tutor', $this->organization));
        $this->assertTrue($this->tutor->hasRole('tutor', $this->cs100Level));
    }

    // --- Entity-scoped roles ------------------------------------------------

    public function test_scoped_role_resolves_for_its_own_entity(): void
    {
        $this->assertTrue($this->admin->hasRole('institution.admin', $this->organization));
    }

    public function test_scoped_role_does_not_leak_to_a_sibling_entity(): void
    {
        $this->assertFalse($this->admin->hasRole('institution.admin', $this->siblingOrganization));
    }

    public function test_scoped_role_is_not_a_platform_role(): void
    {
        // Asking without an entity asks about platform scope, which this
        // organization-scoped assignment must not satisfy.
        $this->assertFalse($this->admin->hasRole('institution.admin'));
        $this->assertFalse($this->student->hasRole('student'));
    }

    public function test_entity_scope_distinguishes_between_entity_types(): void
    {
        // Same id space, different entity_type: an Organization-scoped role
        // must not match a Department that happens to share an id.
        $this->assertFalse($this->student->hasRole('student', Department::firstOrFail()));
    }

    public function test_level_scoped_role_resolves_only_for_its_level(): void
    {
        $coordinator = User::factory()->create();

        RoleAssignment::create([
            'user_id' => $coordinator->id,
            'role_id' => Role::where('slug', 'level.coordinator')->firstOrFail()->id,
            'entity_type' => Level::class,
            'entity_id' => $this->cs100Level->id,
        ]);

        $this->assertTrue($coordinator->hasRole('level.coordinator', $this->cs100Level));
        $this->assertFalse($coordinator->hasRole('level.coordinator', $this->organization));
        $this->assertFalse($coordinator->hasRole('level.coordinator'));
    }

    // --- Permissions --------------------------------------------------------

    public function test_permission_resolves_through_a_scoped_role(): void
    {
        $this->assertTrue($this->admin->hasPermission('users.manage', $this->organization));
    }

    public function test_permission_does_not_leak_to_a_sibling_entity(): void
    {
        $this->assertFalse($this->admin->hasPermission('users.manage', $this->siblingOrganization));
    }

    public function test_permission_from_a_scoped_role_is_not_platform_wide(): void
    {
        $this->assertFalse($this->admin->hasPermission('users.manage'));
    }

    public function test_permission_through_a_platform_role_resolves_everywhere(): void
    {
        $this->assertTrue($this->tutor->hasPermission('courses.create'));
        $this->assertTrue($this->tutor->hasPermission('courses.create', $this->organization));
    }

    public function test_unheld_permission_is_denied(): void
    {
        // content.review belongs to the moderator; the tutor must not
        // inherit it just because both roles are platform-scoped.
        $this->assertFalse($this->tutor->hasPermission('content.review'));
        $this->assertFalse($this->student->hasPermission('users.manage', $this->organization));
    }

    public function test_learner_roles_hold_no_management_permissions(): void
    {
        $this->assertFalse($this->student->hasPermission('courses.create', $this->organization));
        $this->assertFalse($this->student->hasPermission('grades.submit', $this->organization));
    }

    // --- Retired platform administration ------------------------------------

    /**
     * The restructure migration retired the platform-administrator roles
     * alongside the Gate::before() bypass that consumed them. Nothing in the
     * permission model grants blanket authority anymore; if a super-admin
     * role ever returns it must come back through explicit permissions
     * (constitution §54), never through a bypass.
     */
    public function test_retired_platform_administrator_roles_stay_retired(): void
    {
        foreach (['super.admin', 'platform.admin'] as $slug) {
            $this->assertTrue(Role::where('slug', $slug)->doesntExist());
            $this->assertFalse($this->admin->hasRole($slug));
            $this->assertFalse($this->admin->hasRole($slug, $this->organization));
        }
    }
}
