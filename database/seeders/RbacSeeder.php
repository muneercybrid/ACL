<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Assigns the development accounts to the RBAC roles introduced by the
 * 2026_09_09_142357_restructure_acl_roles_and_permissions migration.
 *
 * The roles, permissions and the role-permission matrix are owned by that
 * migration and already exist by the time this seeder runs; seeding them
 * again here would collide on the unique slugs. What the migration cannot
 * do is bind users -- on a fresh database no user exists while migrations
 * run -- so the user -> role assignments live here.
 *
 * There is deliberately no platform-administrator assignment: the
 * restructure removed the platform.admin/super.admin roles together with
 * the Gate::before() bypass, and nothing reintroduced them. The admin demo
 * account is an institution admin whose authority stops at the demo
 * organization.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::firstOrFail();

        $assignments = [
            [
                'email' => 'admin@acl.local',
                'role' => 'institution.admin',
                'entity_type' => Organization::class,
                'entity_id' => $organization->id,
            ],
            // The teaching demo account. The old lecturer role is gone; the
            // closest role in the restructured model is the platform-scoped
            // tutor.
            [
                'email' => 'lecturer@acl.local',
                'role' => 'tutor',
                'entity_type' => null,
                'entity_id' => null,
            ],
            [
                'email' => 'student@acl.local',
                'role' => 'student',
                'entity_type' => Organization::class,
                'entity_id' => $organization->id,
            ],
        ];

        foreach ($assignments as $assignment) {
            $user = User::where('email', $assignment['email'])->firstOrFail();
            $role = Role::where('slug', $assignment['role'])->firstOrFail();

            RoleAssignment::firstOrCreate([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'entity_type' => $assignment['entity_type'],
                'entity_id' => $assignment['entity_id'],
            ]);
        }

        $this->command->info('RBAC seeded successfully!');
    }
}
