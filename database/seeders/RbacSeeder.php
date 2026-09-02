<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Core Permissions
        $permissions = [
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'group' => 'users'],
            ['name' => 'Manage Organizations', 'slug' => 'organizations.manage', 'group' => 'organizations'],
            ['name' => 'Create Courses', 'slug' => 'courses.create', 'group' => 'courses'],
            ['name' => 'Publish Courses', 'slug' => 'courses.publish', 'group' => 'courses'],
            ['name' => 'Submit Grades', 'slug' => 'grades.submit', 'group' => 'grades'],
        ];
        
        foreach ($permissions as $p) {
            Permission::create($p);
        }

        // 2. Create System Roles
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super.admin', 'scope_level' => 'platform', 'is_system' => true],
            ['name' => 'Platform Admin', 'slug' => 'platform.admin', 'scope_level' => 'platform', 'is_system' => true],
            ['name' => 'Institution Admin', 'slug' => 'institution.admin', 'scope_level' => 'organization', 'is_system' => true],
            ['name' => 'Head of Department', 'slug' => 'hod', 'scope_level' => 'department', 'is_system' => true],
            ['name' => 'Lecturer', 'slug' => 'lecturer', 'scope_level' => 'department', 'is_system' => true],
            ['name' => 'Student', 'slug' => 'student', 'scope_level' => 'organization', 'is_system' => true],
        ];

        foreach ($roles as $r) {
            Role::create($r);
        }

        // 3. Assign Permissions to Roles
        $superAdmin = Role::where('slug', 'super.admin')->first();
        $superAdmin->permissions()->attach(Permission::pluck('id'));

        $lecturerRole = Role::where('slug', 'lecturer')->first();
        $courseCreatePerm = Permission::where('slug', 'courses.create')->first();
        $lecturerRole->permissions()->attach($courseCreatePerm->id);

        // 4. Assign Roles to Test Users
        $adminUser = User::where('email', 'admin@acl.local')->first();
        RoleAssignment::create([
            'user_id' => $adminUser->id,
            'role_id' => Role::where('slug', 'platform.admin')->first()->id,
            'entity_type' => null, // Platform scope has no specific entity
            'entity_id' => null,
        ]);

        $lecturerUser = User::where('email', 'lecturer@acl.local')->first();
        $csDept = Department::where('slug', 'computer-science')->first();
        RoleAssignment::create([
            'user_id' => $lecturerUser->id,
            'role_id' => $lecturerRole->id,
            'entity_type' => Department::class, // Scoped to the CS Department
            'entity_id' => $csDept->id,
        ]);

        $studentUser = User::where('email', 'student@acl.local')->first();
        $uni = Organization::first();
        RoleAssignment::create([
            'user_id' => $studentUser->id,
            'role_id' => Role::where('slug', 'student')->first()->id,
            'entity_type' => Organization::class, // Scoped to the University
            'entity_id' => $uni->id,
        ]);

        $this->command->info('RBAC seeded successfully!');
    }
}
