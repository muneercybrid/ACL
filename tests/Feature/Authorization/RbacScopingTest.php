<?php

namespace Tests\Feature\Authorization;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scoped RBAC resolution.
 *
 * The rule these lock in: a role assignment with a null entity_type is
 * platform-wide and applies everywhere, while an assignment carrying an
 * entity applies only to that exact entity. Getting this backwards would
 * silently widen every scoped role into a platform role.
 */
class RbacScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $lecturer;
    private User $student;
    private Department $csDepartment;
    private Department $cyDepartment;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@acl.local')->firstOrFail();
        $this->lecturer = User::where('email', 'lecturer@acl.local')->firstOrFail();
        $this->student = User::where('email', 'student@acl.local')->firstOrFail();

        $this->csDepartment = Department::where('slug', 'computer-science')->firstOrFail();
        $this->cyDepartment = Department::where('slug', 'cybersecurity')->firstOrFail();
        $this->organization = Organization::firstOrFail();
    }

    // --- Platform-scoped roles ---------------------------------------------

    public function test_platform_role_resolves_without_an_entity(): void
    {
        $this->assertTrue($this->admin->hasRole('platform.admin'));
    }

    public function test_platform_role_applies_to_every_entity(): void
    {
        $this->assertTrue($this->admin->hasRole('platform.admin', $this->csDepartment));
        $this->assertTrue($this->admin->hasRole('platform.admin', $this->cyDepartment));
        $this->assertTrue($this->admin->hasRole('platform.admin', $this->organization));
    }

    // --- Entity-scoped roles ------------------------------------------------

    public function test_scoped_role_resolves_for_its_own_entity(): void
    {
        $this->assertTrue($this->lecturer->hasRole('lecturer', $this->csDepartment));
    }

    public function test_scoped_role_does_not_leak_to_a_sibling_entity(): void
    {
        $this->assertFalse($this->lecturer->hasRole('lecturer', $this->cyDepartment));
    }

    public function test_scoped_role_is_not_a_platform_role(): void
    {
        // Asking without an entity asks about platform scope, which this
        // department-scoped assignment must not satisfy.
        $this->assertFalse($this->lecturer->hasRole('lecturer'));
        $this->assertFalse($this->student->hasRole('student'));
    }

    public function test_organization_scoped_role_resolves_for_its_organization(): void
    {
        $this->assertTrue($this->student->hasRole('student', $this->organization));
    }

    public function test_entity_scope_distinguishes_between_entity_types(): void
    {
        // Same id space, different entity_type: an Organization-scoped role
        // must not match a Department that happens to share an id.
        $this->assertFalse($this->student->hasRole('student', $this->csDepartment));
    }

    // --- Permissions --------------------------------------------------------

    public function test_permission_resolves_through_a_scoped_role(): void
    {
        $this->assertTrue($this->lecturer->hasPermission('courses.create', $this->csDepartment));
    }

    public function test_permission_does_not_leak_to_a_sibling_entity(): void
    {
        $this->assertFalse($this->lecturer->hasPermission('courses.create', $this->cyDepartment));
    }

    public function test_permission_from_a_scoped_role_is_not_platform_wide(): void
    {
        $this->assertFalse($this->lecturer->hasPermission('courses.create'));
    }

    public function test_unheld_permission_is_denied(): void
    {
        $this->assertFalse($this->lecturer->hasPermission('users.manage', $this->csDepartment));
        $this->assertFalse($this->student->hasPermission('courses.publish', $this->organization));
    }

    /**
     * RbacSeeder attaches permissions to super.admin but not to
     * platform.admin, so the platform admin holds no permission rows at all.
     * Their access comes entirely from the Gate::before() bypass -- pinning
     * that here so the distinction is not mistaken for a bug later.
     */
    public function test_platform_admin_holds_no_explicit_permissions(): void
    {
        $this->assertFalse($this->admin->hasPermission('users.manage'));
    }

    // --- Administrator predicate --------------------------------------------

    public function test_only_platform_administrators_are_recognised_as_such(): void
    {
        $this->assertTrue($this->admin->isPlatformAdministrator());
        $this->assertFalse($this->lecturer->isPlatformAdministrator());
        $this->assertFalse($this->student->isPlatformAdministrator());
    }
}
