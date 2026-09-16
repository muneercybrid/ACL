<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            /*
             * 1. ADD SUPERADMIN ROLE
             *
             * Platform-wide role with no entity scope (entity_type = null).
             * This is the highest authority in ACL.
             */
            $superadminRole = [
                'name' => 'Super Admin',
                'slug' => 'superadmin',
                'scope_level' => 'platform',
                'description' => 'Platform-wide administrator with full operational oversight across all institutions.',
                'is_system' => true,
            ];

            $existingSuperadmin = DB::table('roles')
                ->where('slug', 'superadmin')
                ->exists();

            if ($existingSuperadmin) {
                DB::table('roles')
                    ->where('slug', 'superadmin')
                    ->update(array_merge($superadminRole, ['updated_at' => now()]));
            } else {
                DB::table('roles')->insert(array_merge($superadminRole, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            /*
             * 2. ADD SUPERADMIN PERMISSIONS
             */
            $superadminPermissions = [
                // Platform-wide management
                ['name' => 'Manage Platform Settings', 'slug' => 'platform.settings', 'group' => 'platform', 'description' => 'Modify platform-wide configuration and settings.'],
                ['name' => 'Manage Roles', 'slug' => 'roles.manage', 'group' => 'platform', 'description' => 'Create, edit, and delete roles and their permission mappings.'],
                ['name' => 'Manage Permissions', 'slug' => 'permissions.manage', 'group' => 'platform', 'description' => 'Create, edit, and delete permissions.'],
                ['name' => 'View Audit Logs', 'slug' => 'audit.view', 'group' => 'platform', 'description' => 'View platform-wide audit history.'],
                ['name' => 'Manage System Alerts', 'slug' => 'alerts.manage', 'group' => 'platform', 'description' => 'Configure and manage system alerts and notifications.'],
                ['name' => 'View System Health', 'slug' => 'health.view', 'group' => 'platform', 'description' => 'View system health, queue status, and provider status.'],
                ['name' => 'Impersonate Users', 'slug' => 'users.impersonate', 'group' => 'platform', 'description' => 'View the platform as another user (read-only).'],

                // Institution management
                ['name' => 'Manage All Institutions', 'slug' => 'institutions.manage.all', 'group' => 'institutions', 'description' => 'Create, edit, activate/deactivate any institution.'],
                ['name' => 'View Institution Onboarding', 'slug' => 'institutions.onboarding.view', 'group' => 'institutions', 'description' => 'View onboarding progress for all institutions.'],
                ['name' => 'Assign Institution Administrators', 'slug' => 'institutions.admin.assign', 'group' => 'institutions', 'description' => 'Create and assign institution administrators.'],

                // Academic structure oversight
                ['name' => 'Manage All Academic Structures', 'slug' => 'academic.structure.manage.all', 'group' => 'academic', 'description' => 'View and modify academic structures across all institutions.'],
                ['name' => 'Manage Curriculum Overrides', 'slug' => 'academic.overrides.manage', 'group' => 'academic', 'description' => 'Manage course additions/removals from programme curricula.'],
                ['name' => 'Import Academic Structures', 'slug' => 'academic.import', 'group' => 'academic', 'description' => 'Import CRF/NUC academic structures.'],

                // User management
                ['name' => 'Manage All Users', 'slug' => 'users.manage.all', 'group' => 'users', 'description' => 'View, activate, suspend any user across the platform.'],
                ['name' => 'View All Registrations', 'slug' => 'registrations.view.all', 'group' => 'registrations', 'description' => 'View all student and external learner registrations.'],

                // Verification monitoring
                ['name' => 'Monitor JAMB Verification', 'slug' => 'jamb.monitor', 'group' => 'verification', 'description' => 'View JAMB verification requests, success rates, and failures.'],
                ['name' => 'Manage Manual Verification', 'slug' => 'verification.manual.manage', 'group' => 'verification', 'description' => 'Review and resolve manual verification cases.'],

                // Reports
                ['name' => 'Generate Platform Reports', 'slug' => 'reports.platform', 'group' => 'reports', 'description' => 'Generate platform-wide operational reports.'],
                ['name' => 'Generate Institution Reports', 'slug' => 'reports.institution', 'group' => 'reports', 'description' => 'Generate reports for any institution.'],

                // AI/ACLi oversight
                ['name' => 'View AI Activity', 'slug' => 'ai.activity.view', 'group' => 'ai', 'description' => 'View ACLi usage, costs, and provider activity.'],
                ['name' => 'Manage AI Configuration', 'slug' => 'ai.config.manage', 'group' => 'ai', 'description' => 'Manage AI provider configuration and entitlements.'],
            ];

            foreach ($superadminPermissions as $permission) {
                $existing = DB::table('permissions')
                    ->where('slug', $permission['slug'])
                    ->exists();

                if ($existing) {
                    DB::table('permissions')
                        ->where('slug', $permission['slug'])
                        ->update(array_merge($permission, ['updated_at' => now()]));
                } else {
                    DB::table('permissions')->insert(array_merge($permission, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }

            /*
             * 3. ASSIGN ALL PERMISSIONS TO SUPERADMIN ROLE
             */
            $superadminId = DB::table('roles')
                ->where('slug', 'superadmin')
                ->value('id');

            $allPermissionIds = DB::table('permissions')->pluck('id');

            // Clear existing mappings for superadmin
            DB::table('role_permissions')
                ->where('role_id', $superadminId)
                ->delete();

            // Assign all permissions
            foreach ($allPermissionIds as $permissionId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $superadminId,
                    'permission_id' => $permissionId,
                ]);
            }

            /*
             * 4. ASSIGN SUPERADMIN ROLE TO EXISTING SUPERADMIN USER
             * (superadmin@acl.local) with PLATFORM-WIDE scope (null entity_type)
             */
            $superadminUserId = DB::table('users')
                ->where('email', 'superadmin@acl.local')
                ->value('id');

            if ($superadminUserId && $superadminId) {
                DB::table('role_assignments')->updateOrInsert(
                    [
                        'user_id' => $superadminUserId,
                        'role_id' => $superadminId,
                        'entity_type' => null,  // PLATFORM-WIDE
                        'entity_id' => null,
                    ],
                    [
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            /*
             * 5. ENSURE PLATFORM ADMIN USER ALSO EXISTS (admin@acl.local already has institution.admin)
             * The Platform Admin user (admin@acl.local) remains an Institution Admin scoped to the demo org.
             * This is by design - no hidden platform-admin bypass.
             */
        });

        /*
         * 6. CREATE ENHANCED AUDIT LOGS TABLE (for superadmin activities)
         * Extends the existing admin_audit_logs with better indexing and fields
         */
        Schema::create('superadmin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 50)->nullable();
            $table->foreignId('acting_as_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('resource_type', 100)->index();
            $table->unsignedBigInteger('resource_id')->nullable()->index();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete()->index();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 36)->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('severity', ['info', 'low', 'medium', 'high', 'critical'])->default('info')->index();
            $table->enum('result', ['success', 'failure'])->default('success');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index(['severity', 'created_at']);
        });

        /*
         * 7. CREATE SYSTEM ALERTS TABLE
         */
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning')->index();
            $table->string('source', 100)->nullable()->index(); // e.g., 'jamb', 'queue', 'database', 'auth'
            // Loose reference to avoid FK constraint naming collisions in fresh test DBs
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->json('metadata')->nullable(); // flexible context data
            $table->enum('status', ['active', 'acknowledged', 'resolved'])->default('active')->index();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity', 'created_at']);
            $table->index(['organization_id', 'status', 'created_at']);
        });

        /*
         * 8. CREATE INSTITUTION ONBOARDING TRACKING TABLE
         */
        Schema::create('institution_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->enum('status', ['pending', 'invited', 'started', 'partially_completed', 'awaiting_review', 'completed', 'suspended'])->default('pending')->index();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('administrator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('progress')->nullable(); // tracks step completion
            $table->json('notes')->nullable();
            $table->timestamps();

            $table->unique('organization_id');
            $table->index(['status', 'updated_at']);
        });

        /*
         * 9. CREATE INSTITUTION STAFF INVITATIONS TABLE
         */
        Schema::create('institution_staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('email');
            $table->string('role_slug'); // e.g., 'level.coordinator', 'moderator', 'tutor'
            $table->json('scope')->nullable(); // {academic_program_id, level_id, faculty_id, department_id}
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'email']);
            $table->index(['token', 'expires_at']);
        });

        /*
         * 10. CREATE GLOBAL SEARCH INDEX HELPERS (optional materialized views would be better, but we'll use tables for now)
         */
        // We'll implement search via services rather than materialized views for now
    }

    public function down(): void
    {
        // Rollback in reverse order
        Schema::dropIfExists('institution_staff_invitations');
        Schema::dropIfExists('institution_onboardings');
        Schema::dropIfExists('system_alerts');
        Schema::dropIfExists('superadmin_audit_logs');

        // Remove role assignments
        $superadminId = DB::table('roles')->where('slug', 'superadmin')->value('id');
        if ($superadminId) {
            DB::table('role_assignments')->where('role_id', $superadminId)->delete();
            DB::table('role_permissions')->where('role_id', $superadminId)->delete();
            DB::table('roles')->where('slug', 'superadmin')->delete();
        }

        // Remove superadmin permissions
        $permissionSlugs = [
            'platform.settings', 'roles.manage', 'permissions.manage', 'audit.view',
            'alerts.manage', 'health.view', 'users.impersonate',
            'institutions.manage.all', 'institutions.onboarding.view', 'institutions.admin.assign',
            'academic.structure.manage.all', 'academic.overrides.manage', 'academic.import',
            'users.manage.all', 'registrations.view.all',
            'jamb.monitor', 'verification.manual.manage',
            'reports.platform', 'reports.institution',
            'ai.activity.view', 'ai.config.manage',
        ];
        DB::table('permissions')->whereIn('slug', $permissionSlugs)->delete();
    }
};