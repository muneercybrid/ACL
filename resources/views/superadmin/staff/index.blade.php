@extends('superadmin.layout.app')
@section('title', 'Staff Management — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">Staff Management</h1><p class="text-sm text-muted">Invite staff · Assign roles · Scope control</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    {{-- Invite form --}}
    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <h2 class="text-base font-extrabold text-text mb-4">Invite Staff Member</h2>
        <form action="{{ route('superadmin.staff.invite') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            @csrf
            <div>
                <label for="email" class="block text-xs font-bold text-muted mb-1">Email *</label>
                <input type="email" id="email" name="email" required value="{{ old('email') }}" placeholder="staff@institution.edu.ng"
                       class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
            </div>
            <div>
                <label for="organization_id" class="block text-xs font-bold text-muted mb-1">Institution *</label>
                <select id="organization_id" name="organization_id" required class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                    <option value="">Select institution…</option>
                    @foreach ($organizations as $org)
                        <option value="{{ $org->id }}" {{ old('organization_id')==$org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="role_slug" class="block text-xs font-bold text-muted mb-1">Role *</label>
                <select id="role_slug" name="role_slug" required class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                    <option value="">Select role…</option>
                    @foreach ($invitableRoles as $role)
                        <option value="{{ $role->slug }}" {{ old('role_slug')===$role->slug ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-primary-fg transition hover:bg-primary/90">Send Invitation</button>
            </div>
            @error('email')<p class="text-xs text-red-600 sm:col-span-4">{{ $message }}</p>@enderror
        </form>
        <p class="mt-3 text-xs text-muted">If the email matches an existing ACL user, the role is assigned immediately with institution scope. Otherwise an invitation link is generated.</p>
    </div>

    {{-- Invitations list --}}
    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-extrabold text-text">Staff Invitations</h2>
            <span class="text-xs text-muted">{{ $invitations->total() }} total</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($invitations as $invitation)
                <div class="rounded-xl border border-border bg-raised p-4">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-text truncate">{{ $invitation->email }}</p>
                            <p class="text-xs text-muted truncate">{{ $invitation->organization?->name }}</p>
                        </div>
                        @if ($invitation->isAccepted())
                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Accepted</span>
                        @elseif ($invitation->isExpired())
                            <span class="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-700">Expired</span>
                        @else
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700">Pending</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">{{ $invitation->role?->name ?? $invitation->role_slug }}</span>
                        <span class="text-[10px] text-muted">{{ $invitation->getScopeDescription() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] text-muted">Expires {{ $invitation->expires_at->format('M d, Y') }} · Invited by {{ $invitation->invitedBy?->name ?? '—' }}</p>
                        @if (!$invitation->isAccepted())
                            <form action="{{ route('superadmin.staff.invitation.revoke', $invitation) }}" method="POST" onsubmit="return confirm('Revoke this invitation?');">
                                @csrf
                                <button type="submit" class="text-[10px] font-bold text-red-600 hover:underline">Revoke</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="sm:col-span-2 lg:col-span-3 py-10 text-center text-sm text-muted">No staff invitations yet.</div>
            @endforelse
        </div>
        {{ $invitations->links() }}
    </div>
</div>
@endsection