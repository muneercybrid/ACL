<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\InstitutionStaffInvitation;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Student;
use App\Models\SuperadminAuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $query = InstitutionStaffInvitation::with(['organization', 'invitedBy', 'acceptedUser']);

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->integer('organization_id'));
        }

        if ($request->filled('status')) {
            $status = $request->string('status');
            if ($status === 'pending') $query->whereNull('accepted_at')->where('expires_at', '>', now());
            if ($status === 'accepted') $query->whereNotNull('accepted_at');
            if ($status === 'expired') $query->whereNull('accepted_at')->where('expires_at', '<=', now());
        }

        $invitations = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $organizations = Organization::orderBy('name')->get();
        $invitableRoles = Role::whereIn('slug', ['institution.admin', 'level.coordinator', 'moderator', 'tutor'])->get();

        return view('superadmin.staff.index', [
            'invitations' => $invitations,
            'organizations' => $organizations,
            'invitableRoles' => $invitableRoles,
            'filters' => $request->only(['organization_id', 'status']),
        ]);
    }

    /**
     * Invite a staff member to an institution with a scoped role.
     * The invitation is bound to the organization; acceptance assigns the
     * role with entity scope = the organization (never platform-wide).
     */
    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'role_slug' => ['required', 'in:level.coordinator,moderator,tutor'],
            'scope' => ['nullable', 'array'],
            'scope.academic_program_id' => ['nullable', 'integer'],
            'scope.level_id' => ['nullable', 'integer'],
            'scope.faculty_id' => ['nullable', 'integer'],
            'scope.department_id' => ['nullable', 'integer'],
        ]);

        $organization = Organization::findOrFail($data['organization_id']);
        $role = Role::where('slug', $data['role_slug'])->firstOrFail();

        // A level coordinator's authority is the programme *and* the level, not
        // the whole organization.
        //
        // This was the worst defect found in the data-model audit: the scope was
        // validated above and then dropped on the floor, and the role assignment
        // was written org-wide. Inviting someone as a "level coordinator" granted
        // them authority over every programme and level in the organization —
        // precisely the over-grant AGENTS.md section 6 forbids. The audit log even
        // recorded the discarded scope, so the log looked correct while the grant
        // was not.
        $scope = $data['scope'] ?? null;

        if ($role->slug === 'level.coordinator' && empty($scope['level_id'])) {
            return back()->withErrors([
                'scope.level_id' => 'A level coordinator must be scoped to a level. '
                    .'Without it the account would be granted the whole organization.',
            ])->withInput();
        }

        // If the user already exists, assign the role directly (scoped).
        $existingUser = User::where('email', $data['email'])->first();

        if ($existingUser) {
            DB::transaction(function () use ($existingUser, $role, $organization, $data, $scope) {
                RoleAssignment::updateOrCreate(
                    [
                        'user_id' => $existingUser->id,
                        'role_id' => $role->id,
                        'entity_type' => $role->slug === 'level.coordinator'
                            ? \App\Models\Curriculum\Programme::class
                            : Organization::class,
                        'entity_id' => $role->slug === 'level.coordinator'
                            ? (int) $scope['academic_program_id']
                            : $organization->id,
                    ],
                    // The level is the part that was being lost. It is what makes a
                    // 100-level coordinator distinct from a 200-level one.
                    $role->slug === 'level.coordinator'
                        ? ['scope_type' => 'level', 'scope_id' => (string) $scope['level_id'], 'updated_at' => now()]
                        : ['updated_at' => now()]
                );

                OrganizationMembership::updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'user_id' => $existingUser->id,
                        'membership_type' => 'staff',
                    ],
                    ['status' => 'active', 'joined_at' => now()]
                );
            });

            SuperadminAuditLog::log([
                'action' => 'staff.role_assigned',
                'resource_type' => Organization::class,
                'resource_id' => $organization->id,
                'organization_id' => $organization->id,
                'target_user_id' => $existingUser->id,
                'severity' => 'medium',
                'description' => "{$existingUser->name} assigned role {$role->name} at {$organization->name}",
                'new_values' => ['role' => $role->slug, 'scope' => $role->slug === 'level.coordinator'
                    ? 'Programme#' . $scope['academic_program_id'] . ' level:' . $scope['level_id']
                    : Organization::class . '#' . $organization->id],
            ]);

            return back()->with('success', "Role {$role->name} assigned to existing user {$existingUser->name}.");
        }

        // Otherwise create an invitation token for later acceptance.
        $invitation = InstitutionStaffInvitation::create([
            'organization_id' => $organization->id,
            'email' => $data['email'],
            'role_slug' => $role->slug,
            'scope' => $scope,
            'invited_by' => auth()->id(),
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        \Illuminate\Support\Facades\Mail::to($data['email'])
            ->queue(new \App\Mail\StaffInvitationMail($invitation));

        SuperadminAuditLog::log([
            'action' => 'staff.invited',
            'resource_type' => Organization::class,
            'resource_id' => $organization->id,
            'organization_id' => $organization->id,
            'severity' => 'medium',
            'description' => "Staff invitation sent to {$data['email']} for {$role->name} at {$organization->name}",
            'new_values' => ['email' => $data['email'], 'role' => $role->slug],
        ]);

        return back()->with('success', "Invitation sent to {$data['email']}. They will receive a link to set up their account.");
    }

    public function revokeInvitation(InstitutionStaffInvitation $invitation): RedirectResponse
    {
        SuperadminAuditLog::log([
            'action' => 'staff.invitation_revoked',
            'resource_type' => InstitutionStaffInvitation::class,
            'resource_id' => $invitation->id,
            'organization_id' => $invitation->organization_id,
            'severity' => 'high',
            'description' => "Staff invitation for {$invitation->email} revoked",
        ]);

        $invitation->delete();

        return back()->with('success', 'Invitation revoked.');
    }
}