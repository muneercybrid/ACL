@extends('superadmin.layout.app')
@section('title', 'Roles & Permissions — Superadmin')
@section('header')
    <div>
        <h1 class="text-xl font-extrabold tracking-tight text-text">Roles & Permissions</h1>
        <p class="text-sm text-muted">{{ $roles->count() }} roles · {{ $permissions->flatten()->count() }} permissions</p>
    </div>
@endsection
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Roles --}}
        <section>
            <h2 class="text-lg font-extrabold text-text mb-4">Roles</h2>
            <div class="space-y-3">
                @foreach ($roles as $role)
                    <a href="{{ route('superadmin.roles.show', $role) }}" class="block rounded-2xl border border-border bg-surface p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md hover:border-primary/30">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-{{ $role->slug === 'superadmin' ? 'red-50 text-red-700' : 'primary/10 text-primary' }} text-sm font-bold">{{ strtoupper(substr($role->name, 0, 2)) }}</span>
                                <div>
                                    <h3 class="text-sm font-bold text-text">{{ $role->name }}</h3>
                                    <p class="text-xs text-muted">{{ $role->description ?? '' }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-extrabold text-text">{{ $role->permissions_count ?? $role->permissions->count() }}</div>
                                <div class="text-xs text-muted">permissions</div>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center gap-4 text-xs text-muted">
                            <span>Scope: <strong class="text-text">{{ $role->scope_level }}</strong></span>
                            <span>Assignments: <strong class="text-text">{{ $role->assignments_count ?? $role->assignments->count() }}</strong></span>
                            @if ($role->is_system)
                                <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">System</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- Permissions grouped by group --}}
        <section>
            <h2 class="text-lg font-extrabold text-text mb-4">Permissions by Group</h2>
            <div class="space-y-4">
                @foreach ($permissions as $group => $groupPermissions)
                    <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                        <h3 class="text-sm font-bold text-primary uppercase tracking-wide mb-3">{{ $group }}</h3>
                        <div class="space-y-1.5">
                            @foreach ($groupPermissions as $perm)
                                <div class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-raised">
                                    <div>
                                        <span class="text-sm font-medium text-text">{{ $perm->name }}</span>
                                        <span class="ml-2 text-xs text-muted">· {{ $perm->slug }}</span>
                                    </div>
                                    <span class="text-xs text-muted">{{ $perm->roles->count() }} roles</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>
@endsection