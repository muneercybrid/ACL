<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            /*
             * Remove obsolete role-permission assignments first.
             */
            DB::table('role_permissions')
                ->whereIn(
                    'role_id',
                    DB::table('roles')
                        ->whereIn('slug', [
                            'super.admin',
                            'platform.admin',
                            'hod',
                            'lecturer',
                        ])
                        ->pluck('id')
                )
                ->delete();

            /*
             * Remove obsolete role assignments.
             *
             * Existing test users are reassigned below where appropriate.
             */
            DB::table('role_assignments')
                ->whereIn(
                    'role_id',
                    DB::table('roles')
                        ->whereIn('slug', [
                            'super.admin',
                            'platform.admin',
                            'hod',
                            'lecturer',
                        ])
                        ->pluck('id')
                )
                ->delete();

            /*
             * Remove obsolete roles.
             */
            DB::table('roles')
                ->whereIn('slug', [
                    'super.admin',
                    'platform.admin',
                    'hod',
                    'lecturer',
                ])
                ->delete();

            /*
             * Add the active ACL roles.
             */
            $roles = [
                [
                    'name' => 'Institution Admin',
                    'slug' => 'institution.admin',
                    'scope_level' => 'organization',
                    'description' => 'Manages users and institutional operations within one organization.',
                    'is_system' => true,
                ],
                [
                    'name' => 'Moderator',
                    'slug' => 'moderator',
                    'scope_level' => 'platform',
                    'description' => 'Reviews and approves educational content before publication.',
                    'is_system' => true,
                ],
                [
                    'name' => 'Level Coordinator',
                    'slug' => 'level.coordinator',
                    'scope_level' => 'level',
                    'description' => 'Coordinates academic activity for a specific programme level.',
                    'is_system' => true,
                ],
                [
                    'name' => 'Student',
                    'slug' => 'student',
                    'scope_level' => 'organization',
                    'description' => 'Institutional learner enrolled in an academic organization.',
                    'is_system' => true,
                ],
                [
                    'name' => 'External Learner',
                    'slug' => 'external.learner',
                    'scope_level' => 'platform',
                    'description' => 'Learner using ACL independently of an institution.',
                    'is_system' => true,
                ],
                [
                    'name' => 'Tutor',
                    'slug' => 'tutor',
                    'scope_level' => 'platform',
                    'description' => 'Creates and teaches their own external educational content subject to moderation.',
                    'is_system' => true,
                ],
            ];

            foreach ($roles as $role) {
                $existingRole = DB::table('roles')
                    ->where('slug', $role['slug'])
                    ->exists();

                if ($existingRole) {
                    DB::table('roles')
                        ->where('slug', $role['slug'])
                        ->update(array_merge($role, [
                            'updated_at' => now(),
                        ]));
                } else {
                    DB::table('roles')->insert(array_merge($role, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }

            /*
             * Ensure the permissions required by the new RBAC model exist.
             */
            $permissions = [
                [
                    'name' => 'Manage Users',
                    'slug' => 'users.manage',
                    'group' => 'users',
                    'description' => 'Manage users within an authorized institutional scope.',
                ],
                [
                    'name' => 'Manage Organizations',
                    'slug' => 'organizations.manage',
                    'group' => 'organizations',
                    'description' => 'Manage institutional organization settings.',
                ],
                [
                    'name' => 'Create Courses',
                    'slug' => 'courses.create',
                    'group' => 'courses',
                    'description' => 'Create educational course content within an authorized scope.',
                ],
                [
                    'name' => 'Submit Content for Review',
                    'slug' => 'content.submit',
                    'group' => 'content',
                    'description' => 'Submit content for moderation and approval.',
                ],
                [
                    'name' => 'Review Content',
                    'slug' => 'content.review',
                    'group' => 'content',
                    'description' => 'Review submitted educational content.',
                ],
                [
                    'name' => 'Approve Content',
                    'slug' => 'content.approve',
                    'group' => 'content',
                    'description' => 'Approve educational content for publication.',
                ],
                [
                    'name' => 'Coordinate Level',
                    'slug' => 'level.coordinate',
                    'group' => 'academic',
                    'description' => 'Coordinate academic activity for an assigned programme level.',
                ],
                [
                    'name' => 'Submit Grades',
                    'slug' => 'grades.submit',
                    'group' => 'grades',
                    'description' => 'Submit academic grades within an authorized scope.',
                ],
            ];

            foreach ($permissions as $permission) {
                $existingPermission = DB::table('permissions')
                    ->where('slug', $permission['slug'])
                    ->exists();

                if ($existingPermission) {
                    DB::table('permissions')
                        ->where('slug', $permission['slug'])
                        ->update(array_merge($permission, [
                            'updated_at' => now(),
                        ]));
                } else {
                    DB::table('permissions')->insert(array_merge($permission, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }

            /*
             * courses.publish belonged to the previous permission model.
             * ACL content is now published through moderation approval.
             */
            DB::table('permissions')
                ->where('slug', 'courses.publish')
                ->delete();

            /*
             * Remove old role-permission mappings for the retained system roles
             * so the new permission matrix is authoritative.
             */
            $activeRoleIds = DB::table('roles')
                ->whereIn('slug', [
                    'institution.admin',
                    'moderator',
                    'level.coordinator',
                    'student',
                    'external.learner',
                    'tutor',
                ])
                ->pluck('id');

            DB::table('role_permissions')
                ->whereIn('role_id', $activeRoleIds)
                ->delete();

            /*
             * Institution Admin:
             * institution-scoped administration and content oversight.
             */
            $institutionAdminId = DB::table('roles')
                ->where('slug', 'institution.admin')
                ->value('id');

            $institutionAdminPermissions = DB::table('permissions')
                ->whereIn('slug', [
                    'users.manage',
                    'organizations.manage',
                    'courses.create',
                    'content.review',
                    'content.approve',
                    'level.coordinate',
                    'grades.submit',
                ])
                ->pluck('id');

            foreach ($institutionAdminPermissions as $permissionId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $institutionAdminId,
                    'permission_id' => $permissionId,
                ]);
            }

            /*
             * Moderator:
             * review/approval only; deliberately cannot create courses.
             */
            $moderatorId = DB::table('roles')
                ->where('slug', 'moderator')
                ->value('id');

            $moderatorPermissions = DB::table('permissions')
                ->whereIn('slug', [
                    'content.review',
                    'content.approve',
                ])
                ->pluck('id');

            foreach ($moderatorPermissions as $permissionId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $moderatorId,
                    'permission_id' => $permissionId,
                ]);
            }

            /*
             * Level Coordinator:
             * scoped to a Level, which inherently belongs to an AcademicProgram.
             */
            $levelCoordinatorId = DB::table('roles')
                ->where('slug', 'level.coordinator')
                ->value('id');

            $levelCoordinatorPermissions = DB::table('permissions')
                ->whereIn('slug', [
                    'level.coordinate',
                    'grades.submit',
                ])
                ->pluck('id');

            foreach ($levelCoordinatorPermissions as $permissionId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $levelCoordinatorId,
                    'permission_id' => $permissionId,
                ]);
            }

            /*
             * Tutor:
             * may create and submit their own external content.
             * Publication remains controlled by moderation.
             */
            $tutorId = DB::table('roles')
                ->where('slug', 'tutor')
                ->value('id');

            $tutorPermissions = DB::table('permissions')
                ->whereIn('slug', [
                    'courses.create',
                    'content.submit',
                ])
                ->pluck('id');

            foreach ($tutorPermissions as $permissionId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $tutorId,
                    'permission_id' => $permissionId,
                ]);
            }

            /*
             * Student and External Learner intentionally receive no
             * management permissions.
             */

            /*
             * Reassign the development admin account to Institution Admin
             * within the existing demo organization instead of leaving a
             * platform-wide administrator behind.
             */
            $adminUserId = DB::table('users')
                ->where('email', 'admin@acl.local')
                ->value('id');

            $demoOrganizationId = DB::table('organizations')
                ->where('id', 1)
                ->value('id');

            if ($adminUserId && $demoOrganizationId) {
                $institutionAdminId = DB::table('roles')
                    ->where('slug', 'institution.admin')
                    ->value('id');

                DB::table('role_assignments')->updateOrInsert(
                    [
                        'user_id' => $adminUserId,
                        'role_id' => $institutionAdminId,
                        'entity_type' => 'App\\Models\\Organization',
                        'entity_id' => $demoOrganizationId,
                    ],
                    [
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });
    }

    public function down(): void
    {
        /*
         * This migration intentionally does not reconstruct the previous
         * production RBAC state. The pre-migration database backup remains
         * the authoritative recovery mechanism if a full rollback is ever
         * required.
         */
        throw new RuntimeException(
            'Rollback is intentionally disabled for ACL RBAC restructuring. Restore the pre-migration database backup instead.'
        );
    }
};
