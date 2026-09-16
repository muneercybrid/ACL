<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Student;
use App\Models\StudentRegistrationVerification;
use App\Models\SuperadminAuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->with('roleAssignments.role', 'student');

        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%"));
        }

        if ($request->filled('role')) {
            $query->whereHas('roleAssignments.role', fn ($q) => $q->where('slug', $request->string('role')));
        }

        if ($request->filled('status')) {
            if ($request->string('status') === 'suspended') $query->whereNotNull('suspended_at');
            else $query->whereNull('suspended_at');
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $roles = Role::orderBy('name')->get();

        return view('superadmin.users.index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $request->only(['q', 'role', 'status']),
        ]);
    }

    public function show(User $user): View
    {
        $user->load([
            'roleAssignments.role',
            'roleAssignments.entity',
            'organizationMemberships.organization',
            'organizationMemberships.academicProgram',
            'organizationMemberships.currentLevel',
            'student',
            'enrollments.courseOffering.course',
        ]);

        $verification = StudentRegistrationVerification::where('user_id', $user->id)->latest('created_at')->first();

        $audit = SuperadminAuditLog::where('target_user_id', $user->id)
            ->orWhere('actor_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $roles = Role::orderBy('name')->get();

        return view('superadmin.users.show', [
            'user' => $user,
            'verification' => $verification,
            'audit' => $audit,
            'roles' => $roles,
        ]);
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $suspended = $user->suspended_at ? null : now();
        $user->update(['suspended_at' => $suspended]);

        SuperadminAuditLog::log([
            'action' => $suspended ? 'user.suspended' : 'user.restored',
            'resource_type' => User::class,
            'resource_id' => $user->id,
            'target_user_id' => $user->id,
            'severity' => 'high',
            'description' => ($suspended ? 'Suspended' : 'Restored') . " account for {$user->name} ({$user->email})",
        ]);

        return back()->with('success', $suspended ? 'User suspended.' : 'User restored.');
    }

    /**
     * Update a user's role assignments. Superadmin role can only be granted
     * to platform scope (null entity) — never as an institution-scoped grant,
     * and never by an institution admin (this route is superadmin-only).
     */
    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['array'],
            'roles.*.role_id' => ['required', 'exists:roles,id'],
            'roles.*.entity_type' => ['nullable', 'string'],
            'roles.*.entity_id' => ['nullable', 'integer'],
        ]);

        foreach ($data['roles'] as $assignment) {
            $role = Role::findOrFail($assignment['role_id']);

            // Safety: superadmin role must always be platform-scoped.
            if ($role->slug === 'superadmin' && $assignment['entity_type'] !== null) {
                return back()->withErrors(['roles' => 'The superadmin role can only be granted at platform scope.']);
            }

            RoleAssignment::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'entity_type' => $assignment['entity_type'] ?: null,
                    'entity_id' => $assignment['entity_id'] ?: null,
                ],
                ['updated_at' => now()]
            );
        }

        SuperadminAuditLog::log([
            'action' => 'user.roles_updated',
            'resource_type' => User::class,
            'resource_id' => $user->id,
            'target_user_id' => $user->id,
            'severity' => 'high',
            'description' => "Role assignments updated for {$user->name}",
            'new_values' => $data['roles'],
        ]);

        return back()->with('success', 'Role assignments updated.');
    }
}