@extends('superadmin.layout.app')
@section('title', $role->name . ' — Role — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">{{ $role->name }}</h1><p class="text-sm text-muted">Role · {{ $role->scope_level }} scope · {{ $role->permissions->count() }} permissions</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-{{ $role->slug === 'superadmin' ? 'red-50 text-red-700' : 'primary/10 text-primary' }} text-sm font-bold">{{ strtoupper(substr($role->name, 0, 2)) }}</span>
            <div><h2 class="text-lg font-extrabold text-text">{{ $role->name }}</h2><p class="text-xs text-muted">Slug: <code>{{ $role->slug }}</code> · Scope: {{ $role->scope_level }}</p></div>
        </div>
        <p class="text-sm text-muted">{{ $role->description ?? 'No description.' }}</p>
    </div>

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <h3 class="text-base font-extrabold text-text mb-4">Permissions</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach ($permissions as $group => $groupPermissions)
                <div class="rounded-xl bg-raised p-4">
                    <h4 class="text-xs font-bold text-primary uppercase tracking-widest mb-2">{{ $group }}</h4>
                    <div class="space-y-1.5">
                        @foreach ($groupPermissions as $perm)
                            <div class="flex items-center justify-between text-sm py-1">
                                <span class="font-medium text-text">{{ $perm->name }}</span>
                                <span class="text-xs font-mono text-muted">{{ $perm->slug }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <h3 class="text-base font-extrabold text-text mb-4">Assignments ({{ $role->assignments->count() }})</h3>
        <div class="divide-y divide-border">
            @forelse ($role->assignments as $assignment)
                <div class="flex items-center justify-between py-3">
                    <div>
                        <span class="text-sm font-semibold text-text">{{ $assignment->user?->name ?? 'Unknown' }}</span>
                        <span class="text-xs text-muted">{{ $assignment->user?->email ?? '' }}</span>
                    </div>
                    <div class="text-xs text-muted">{{ $assignment->entity_type ? class_basename($assignment->entity_type) . ' #' . $assignment->entity_id : 'Platform' }}</div>
                </div>
            @empty
                <p class="text-sm text-muted">No users assigned to this role.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection