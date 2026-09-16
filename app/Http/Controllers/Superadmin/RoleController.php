<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount('assignments', 'permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('group')->orderBy('name')->get()->groupBy('group');

        return view('superadmin.roles.index', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function show(Role $role): View
    {
        $role->load(['permissions', 'assignments.user']);

        $permissions = Permission::orderBy('group')->orderBy('name')->get()->groupBy('group');

        return view('superadmin.roles.show', [
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }
}