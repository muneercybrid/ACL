<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\InstitutionOnboarding;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\SuperadminAuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InstitutionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Organization::query()->withCount([
            'memberships as student_count' => fn ($q) => $q->where('membership_type', 'student')->where('status', 'active'),
            'memberships as staff_count' => fn ($q) => $q->where('membership_type', 'staff')->where('status', 'active'),
            'memberships as admin_count' => fn ($q) => $q->where('membership_type', 'administrator')->where('status', 'active'),
        ]);

        // Filters
        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"));
        }

        if ($request->filled('status')) {
            $status = $request->string('status');
            if ($status === 'active') $query->where('is_active', true);
            if ($status === 'inactive') $query->where('is_active', false);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        $institutions = $query->with('onboarding')->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $types = Organization::query()->distinct()->pluck('type')->filter()->values();

        return view('superadmin.institutions.index', [
            'institutions' => $institutions,
            'types' => $types,
            'filters' => $request->only(['q', 'status', 'type']),
        ]);
    }

    public function create(): View
    {
        return view('superadmin.institutions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:university,federal,state,private,polytechnic,college,other'],
            'code' => ['nullable', 'string', 'max:50'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $organization = DB::transaction(function () use ($data) {
            $organization = Organization::create(array_merge($data, [
                'slug' => Str::slug($data['name']) . '-' . Str::lower(Str::random(4)),
                'is_active' => true,
                'status' => 'active',
                'onboarded_at' => now(),
            ]));

            // Create onboarding tracker
            InstitutionOnboarding::create([
                'organization_id' => $organization->id,
                'status' => 'pending',
                'invited_by' => auth()->id(),
                'progress' => ['institution_info' => true],
            ]);

            return $organization;
        });

        SuperadminAuditLog::log([
            'action' => 'institution.created',
            'resource_type' => Organization::class,
            'resource_id' => $organization->id,
            'organization_id' => $organization->id,
            'severity' => 'medium',
            'description' => "Institution \"{$organization->name}\" created",
            'new_values' => ['name' => $organization->name, 'type' => $organization->type],
        ]);

        return redirect()
            ->route('superadmin.institutions.show', $organization)
            ->with('success', 'Institution created successfully.');
    }

    public function show(Organization $organization): View
    {
        $organization->load([
            'faculties.departments',
            'memberships' => fn ($q) => $q->with(['user', 'academicProgram', 'currentLevel'])->orderBy('created_at', 'desc'),
        ]);

        $onboarding = $organization->onboarding ?: new InstitutionOnboarding(['organization_id' => $organization->id]);

        $stats = [
            'faculties' => $organization->faculties->count(),
            'departments' => $organization->faculties->flatMap->departments->count(),
            'students' => $organization->memberships->where('membership_type', 'student')->where('status', 'active')->count(),
            'staff' => $organization->memberships->where('membership_type', 'staff')->where('status', 'active')->count(),
            'administrators' => $organization->memberships->where('membership_type', 'administrator')->where('status', 'active')->count(),
        ];

        $administrators = $organization->memberships
            ->where('membership_type', 'administrator')
            ->where('status', 'active')
            ->values();

        $staffMembers = $organization->memberships
            ->where('membership_type', 'staff')
            ->where('status', 'active')
            ->values();

        $students = $organization->memberships
            ->where('membership_type', 'student')
            ->where('status', 'active')
            ->values();

        $recentActivity = SuperadminAuditLog::forOrganization($organization->id)
            ->with('actor')
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        $candidateAdmins = User::query()
            ->whereNotIn('id', $administrators->pluck('user_id'))
            ->orderBy('name')
            ->limit(25)
            ->get();

        return view('superadmin.institutions.show', [
            'organization' => $organization,
            'onboarding' => $onboarding,
            'stats' => $stats,
            'administrators' => $administrators,
            'staffMembers' => $staffMembers,
            'students' => $students,
            'recentActivity' => $recentActivity,
            'candidateAdmins' => $candidateAdmins,
        ]);
    }

    public function edit(Organization $organization): View
    {
        return view('superadmin.institutions.edit', ['organization' => $organization]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:university,federal,state,private,polytechnic,college,other'],
            'code' => ['nullable', 'string', 'max:50'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $old = $organization->only(array_keys($data));
        $organization->update($data);

        SuperadminAuditLog::log([
            'action' => 'institution.updated',
            'resource_type' => Organization::class,
            'resource_id' => $organization->id,
            'organization_id' => $organization->id,
            'severity' => 'low',
            'description' => "Institution \"{$organization->name}\" updated",
            'old_values' => $old,
            'new_values' => $organization->only(array_keys($data)),
        ]);

        return redirect()
            ->route('superadmin.institutions.show', $organization)
            ->with('success', 'Institution updated successfully.');
    }

    public function toggleActive(Organization $organization): RedirectResponse
    {
        $organization->update(['is_active' => !$organization->is_active]);

        SuperadminAuditLog::log([
            'action' => $organization->is_active ? 'institution.activated' : 'institution.suspended',
            'resource_type' => Organization::class,
            'resource_id' => $organization->id,
            'organization_id' => $organization->id,
            'severity' => 'high',
            'description' => "Institution \"{$organization->name}\" " . ($organization->is_active ? 'activated' : 'suspended'),
            'new_values' => ['is_active' => $organization->is_active],
        ]);

        return back()->with('success', $organization->is_active ? 'Institution activated.' : 'Institution suspended.');
    }

    /**
     * Assign an existing user as the institution administrator.
     * The user's role assignment is scoped to the organization (entity-scoped,
     * never platform-wide) — an institution admin can never manage other
     * institutions through this path.
     */
    public function assignAdmin(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $role = Role::where('slug', 'institution.admin')->firstOrFail();

        DB::transaction(function () use ($user, $role, $organization) {
            // Scoped assignment: entity = this organization only.
            RoleAssignment::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'entity_type' => Organization::class,
                    'entity_id' => $organization->id,
                ],
                ['updated_at' => now()]
            );

            // Mirror as an administrator membership for dashboard queries.
            OrganizationMembership::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'membership_type' => 'administrator',
                ],
                ['status' => 'active', 'joined_at' => now()]
            );

            // Track onboarding progress
            $onboarding = $organization->onboarding;
            if ($onboarding) {
                $progress = $onboarding->progress ?? [];
                $progress['admin_profile'] = true;
                $onboarding->update([
                    'progress' => $progress,
                    'administrator_user_id' => $user->id,
                    'status' => in_array($onboarding->status, ['pending', 'invited']) ? 'started' : $onboarding->status,
                ]);
            }
        });

        SuperadminAuditLog::log([
            'action' => 'institution.admin_assigned',
            'resource_type' => Organization::class,
            'resource_id' => $organization->id,
            'organization_id' => $organization->id,
            'target_user_id' => $user->id,
            'severity' => 'high',
            'description' => "{$user->name} assigned as administrator of {$organization->name}",
            'new_values' => ['user_id' => $user->id, 'role' => 'institution.admin', 'scope' => Organization::class . '#' . $organization->id],
        ]);

        return back()->with('success', "{$user->name} is now the institution administrator.");
    }
}