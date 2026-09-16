@extends('superadmin.layout.app')
@section('title', 'All Users — Superadmin')
@section('header')
    <div>
        <h1 class="text-xl font-extrabold tracking-tight text-text">All Users</h1>
        <p class="text-sm text-muted">{{ $users->total() }} registered users</p>
    </div>
@endsection
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
        <form action="{{ route('superadmin.users') }}" method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold text-muted mb-1">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or email…"
                       class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
            </div>
            <div class="min-w-[150px]">
                <label class="block text-xs font-bold text-muted mb-1">Role</label>
                <select name="role" class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->slug }}" {{ ($filters['role'] ?? '')===$role->slug ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-primary-fg transition hover:bg-primary/90">Filter</button>
            <a href="{{ route('superadmin.users') }}" class="text-sm text-muted hover:text-text transition">Clear</a>
        </form>
    </div>

    <div class="rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-border bg-raised text-left text-xs uppercase tracking-widest text-muted">
                    <th class="px-6 py-3">User</th><th class="px-6 py-3">Email</th><th class="px-6 py-3">Roles</th><th class="px-6 py-3">Institution</th><th class="px-6 py-3">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                    @forelse ($users as $user)
                        <tr class="hover:bg-raised/50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary text-xs font-bold">{{ strtoupper(substr($user->name, 0, 2)) }}</div>
                                    <a href="{{ route('superadmin.users.show', $user) }}" class="font-semibold text-text hover:text-primary transition">{{ $user->name }}</a>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs text-muted">{{ $user->email }}</td>
                            <td class="px-6 py-4">
                                @forelse ($user->roleAssignments as $assignment)
                                    <span class="inline-block rounded-full {{ ($assignment->role?->slug ?? '') === 'superadmin' ? 'bg-red-50 text-red-700' : (($assignment->role?->slug ?? '') === 'institution.admin' ? 'bg-primary/10 text-primary' : 'bg-raised text-muted') }} px-2 py-0.5 text-[10px] font-bold">{{ $assignment->role?->name ?? '?' }} @if($assignment->entity_type) <span class="text-[9px]">(scoped)</span> @else <span class="text-[9px]">(platform)</span> @endif</span>
                                @empty
                                    <span class="text-xs text-muted">No roles</span>
                                @endforelse
                            </td>
                            <td class="px-6 py-4 text-xs text-muted">{{ $user->organizationMemberships->first()?->organization?->name ?? '—' }}</td>
                            <td class="px-6 py-4">
                                @if ($user->suspended_at)
                                    <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-red-700">Suspended</span>
                                @else
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">Active</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-muted">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
</div>
@endsection