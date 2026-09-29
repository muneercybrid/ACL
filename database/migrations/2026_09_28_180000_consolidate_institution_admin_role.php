<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Consolidates the two "Institution Admin" roles onto one.
 *
 * The problem
 * -----------
 * `institution.admin` (id 3) is created by RbacSeeder and carries the 7
 * permissions that define what an institution administrator may do.
 * `institution_admin` (id 2060001) is a second, hand-made role with the same
 * display name and NO permissions at all. Provisioning created 474 accounts
 * against that second role, so every institutional administrator on the
 * platform holds a role that grants nothing: a `hasPermission()` check fails
 * for all of them even though they are signed in and correctly scoped.
 *
 * The two slugs also disagree with each other's punctuation, so
 * `User::getHighestRoleSlug()` — whose priority table only knows
 * 'institution.admin' — does not recognise the slug that 474 accounts
 * actually hold.
 *
 * The fix
 * -------
 * Move every assignment to the permissioned role, then drop the empty one.
 * `institution.admin` is kept as the survivor because it is the slug the
 * seeder, the priority table and `isInstitutionAdmin()` already use, and it is
 * the one that carries the permissions.
 *
 * This is additive-then-converge: nothing is destroyed until the assignments
 * have actually moved, and the migration aborts rather than delete the old role
 * if any assignment could not be migrated.
 */
return new class extends Migration
{
    private const KEEP = 'institution.admin';
    private const REMOVE = 'institution_admin';

    public function up(): void
    {
        $keep = DB::table('roles')->where('slug', self::KEEP)->first();
        $remove = DB::table('roles')->where('slug', self::REMOVE)->first();

        if ($keep === null) {
            throw new RuntimeException(
                'Role [' . self::KEEP . '] is missing. RbacSeeder must run before this migration; '
                . 'refusing to delete the only institutional administrator role.'
            );
        }

        if ($remove === null) {
            return; // already consolidated
        }

        $moved = 0;

        // A user may already hold the surviving role scoped to the same
        // institution. In that case the duplicate assignment is redundant and
        // is removed rather than moved, because the (user, role, entity)
        // triple is the thing that must stay unique.
        $toMove = DB::table('role_assignments')->where('role_id', $remove->id)->get();

        foreach ($toMove->chunk(200) as $chunk) {
            foreach ($chunk as $assignment) {
                $already = DB::table('role_assignments')
                    ->where('user_id', $assignment->user_id)
                    ->where('role_id', $keep->id)
                    ->where('entity_type', $assignment->entity_type)
                    ->where('entity_id', $assignment->entity_id)
                    ->exists();

                if ($already) {
                    DB::table('role_assignments')->where('id', $assignment->id)->delete();

                    continue;
                }

                DB::table('role_assignments')->where('id', $assignment->id)
                    ->update(['role_id' => $keep->id]);
                $moved++;
            }
        }

        $left = DB::table('role_assignments')->where('role_id', $remove->id)->count();

        if ($left > 0) {
            throw new RuntimeException(
                "Cannot remove role [{$remove->slug}]: {$left} assignment(s) still reference it. Refusing to delete."
            );
        }

        // The duplicate grants nothing that the surviving role does not, so
        // removing it loses no authority.
        DB::table('role_permissions')->where('role_id', $remove->id)->delete();
        DB::table('roles')->where('id', $remove->id)->delete();

        info("consolidated institution admin role: {$moved} assignment(s) moved onto role {$keep->id}");
    }

    public function down(): void
    {
        // Re-creating the removed role would require inventing a role id and
        // would not restore its assignment rows, so this is not reversible
        // without the backup. Deliberately explicit rather than pretending.
    }
};
