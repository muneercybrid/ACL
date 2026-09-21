<?php

namespace App\Services\Institution;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InstitutionAdminService
{
    /**
     * Find the current institution administrator scoped to the given organization.
     * Uses the roleAssignments → role two-hop relationship (User has no direct roles() method).
     */
    public function findAdmin(Organization $organization): ?User
    {
        return User::whereHas('roleAssignments', function ($q) use ($organization) {
            $q->whereHas('role', fn ($q2) => $q2->where('slug', 'institution.admin'))
                ->where('entity_type', Organization::class)
                ->where('entity_id', $organization->id);
        })->first();
    }

    /**
     * Provision a user as the institution administrator for the given organization.
     *
     * The role assignment is always entity-scoped to the organization — never
     * platform-wide — so the provisioned admin cannot manage other institutions
     * through this path.
     */
    public function provisionAdmin(Organization $organization, User $user): void
    {
        $role = Role::where('slug', 'institution.admin')->firstOrFail();

        DB::transaction(function () use ($user, $role, $organization) {
            RoleAssignment::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'entity_type' => Organization::class,
                    'entity_id' => $organization->id,
                ],
                ['updated_at' => now()]
            );

            OrganizationMembership::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'membership_type' => 'administrator',
                ],
                ['status' => 'active', 'joined_at' => now()]
            );
        });
    }
}
