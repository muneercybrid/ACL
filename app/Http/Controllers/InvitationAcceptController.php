<?php

namespace App\Http\Controllers;

use App\Models\InstitutionStaffInvitation;
use App\Models\RoleAssignment;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvitationAcceptController extends Controller
{
    public function show(string $token): View
    {
        $invitation = InstitutionStaffInvitation::where('token', $token)->first();

        if (! $invitation) {
            abort(404, 'This invitation link is not valid.');
        }

        if ($invitation->isExpired()) {
            abort(410, 'This invitation has expired. Please request a new link.');
        }

        if ($invitation->isAccepted()) {
            abort(410, 'This invitation has already been accepted.');
        }

        $org = $invitation->organization;
        $role = $invitation->role;

        return view('auth.invitation-accept', [
            'invitation' => $invitation,
            'organization' => $org,
            'roleName' => $role?->name ?? 'Administrator',
        ]);
    }

    public function store(string $token, Request $request): RedirectResponse
    {
        $invitation = InstitutionStaffInvitation::where('token', $token)->first();

        if (! $invitation || $invitation->isExpired() || $invitation->isAccepted()) {
            abort(410, 'This invitation is no longer valid.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($invitation, $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $org = $invitation->organization;
            $role = Role::where('slug', $invitation->role_slug)->firstOrFail();

            RoleAssignment::create([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'entity_type' => Organization::class,
                'entity_id' => $org ? $org->id : null,
            ]);

            if ($org) {
                OrganizationMembership::updateOrCreate(
                    [
                        'organization_id' => $org->id,
                        'user_id' => $user->id,
                        'membership_type' => 'administrator',
                    ],
                    [
                        'status' => 'active',
                        'joined_at' => now(),
                    ],
                );
            }

            $invitation->update([
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
            ]);

            return $user;
        });

        // Sign the user in immediately so the welcome page can redirect
        // straight to their institution rather than bouncing them to login.
        auth()->login($user);

        return redirect()->route('institution.dashboard')
            ->with('success', 'Welcome, ' . $user->name . '. You are now the administrator for ' . ($invitation->organization?->name ?? 'this institution') . '.');
    }
}
