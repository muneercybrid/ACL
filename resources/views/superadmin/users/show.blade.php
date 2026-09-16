@extends('superadmin.layout.app')
@section('title', $user->name . ' — User — Superadmin')
@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('superadmin.users') }}" class="text-muted hover:text-text transition"><x-ui.icon name="arrow-left" class="h-5 w-5" /></a>
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary text-primary-fg text-sm font-bold">{{ strtoupper(substr($user->name, 0, 2)) }}</div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-extrabold tracking-tight text-text">{{ $user->name }}</h1>
                    @if ($user->suspended_at)
                        <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-red-700">Suspended</span>
                    @endif
                </div>
                <p class="text-sm text-muted">{{ $user->email }} · Member since {{ $user->created_at?->format('M d, Y') }}</p>
            </div>
        </div>
    </div>
@endsection
@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    {{-- Profile card --}}
    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-extrabold text-text">Profile</h3>
            <form action="{{ route('superadmin.users.toggle', $user) }}" method="POST">
                @csrf
                <button type="submit" class="rounded-xl px-4 py-2 text-xs font-bold transition border {{ $user->suspended_at ? 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' : 'border-red-200 text-red-700 hover:bg-red-50' }}">
                    {{ $user->suspended_at ? 'Restore Account' : 'Suspend Account' }}
                </button>
            </form>
        </div>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <dt class="text-muted">ID</dt><dd class="font-mono text-xs text-text">{{ $user->id }}</dd>
            <dt class="text-muted">Email</dt><dd class="text-text">{{ $user->email }}</dd>
            <dt class="text-muted">Name</dt><dd class="text-text">{{ $user->name }}</dd>
            <dt class="text-muted">Email Verified</dt><dd class="text-text">{{ $user->email_verified_at?->format('M d, Y H:i') ?? 'Not verified' }}</dd>
            <dt class="text-muted">Provider</dt><dd class="text-text">{{ $user->provider ? ucfirst($user->provider) : 'Email' }}</dd>
            <dt class="text-muted">Suspended At</dt><dd class="text-text">{{ $user->suspended_at?->format('M d, Y H:i') ?? '—' }}</dd>
            <dt class="text-muted">Highest Role</dt><dd class="text-text font-semibold">{{ $user->getHighestRoleSlug() ?? 'None' }}</dd>
        </dl>
    </div>

    {{-- Role assignments --}}
    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <h3 class="text-base font-extrabold text-text mb-4">Role Assignments</h3>
        @if ($user->roleAssignments->count())
            <div class="space-y-3">
                @foreach ($user->roleAssignments as $assignment)
                    <div class="flex items-center justify-between rounded-xl bg-raised px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="inline-block rounded-full {{ ($assignment->role?->slug ?? '') === 'superadmin' ? 'bg-red-100 text-red-700' : 'bg-primary/10 text-primary' }} px-3 py-1 text-sm font-bold">{{ $assignment->role?->name ?? 'Unknown' }}</span>
                            @if ($assignment->entity_type)
                                <span class="text-sm text-muted">Scoped to: {{ class_basename($assignment->entity_type) }} #{{ $assignment->entity_id }}</span>
                            @else
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">Platform-wide</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-muted">No roles assigned.</p>
        @endif
    </div>

    {{-- Academic context --}}
    @if ($user->student || $user->organizationMemberships->count())
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-base font-extrabold text-text mb-4">Academic Context</h3>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                @if ($user->student)
                    <dt class="text-muted">Nationality</dt><dd class="text-text">{{ $user->student->nationality ?? '—' }}</dd>
                    <dt class="text-muted">Admission Year</dt><dd class="text-text">{{ $user->student->admission_year ?? '—' }}</dd>
                @endif
                @foreach ($user->organizationMemberships as $membership)
                    <dt class="text-muted">Institution</dt><dd class="text-text">{{ $membership->organization?->name ?? '—' }} <span class="text-xs text-muted">({{ $membership->status }})</span></dd>
                    <dt class="text-muted">Programme</dt><dd class="text-text">{{ $membership->academicProgram?->name ?? '—' }}</dd>
                    <dt class="text-muted">Level</dt><dd class="text-text">{{ $membership->currentLevel?->name ?? '—' }}</dd>
                    <dt class="text-muted">Matric</dt><dd class="font-mono text-xs text-text">{{ $membership->matric_number ?? '—' }}</dd>
                @endforeach
            </dl>
        </div>
    @endif

    {{-- Enrollment --}}
    @if ($user->enrollments->count())
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-base font-extrabold text-text mb-4">Enrolled Courses ({{ $user->enrollments->count() }})</h3>
            <div class="divide-y divide-border">
                @foreach ($user->enrollments as $enrollment)
                    <div class="flex items-center justify-between py-2">
                        <div>
                            <span class="text-sm font-semibold text-text">{{ $enrollment->courseOffering?->course?->code ?? '—' }}</span>
                            <span class="text-sm text-muted ml-2">{{ $enrollment->courseOffering?->course?->title ?? '' }}</span>
                        </div>
                        <span class="text-xs font-bold {{ $enrollment->status === 'active' ? 'text-emerald-700' : 'text-muted' }}">{{ $enrollment->status }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Verification --}}
    @if ($verification)
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-base font-extrabold text-text mb-4">JAMB Verification</h3>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <dt class="text-muted">Status</dt><dd><span class="rounded-full bg-{{ $verification->status === 'verified' ? 'emerald-50 text-emerald-700' : 'amber-50 text-amber-700' }} px-2.5 py-0.5 text-xs font-bold">{{ ucfirst(str_replace('_', ' ', $verification->status)) }}</span></dd>
                <dt class="text-muted">Verified Name</dt><dd class="text-text">{{ $verification->verified_name ?? '—' }}</dd>
                <dt class="text-muted">Institution</dt><dd class="text-text">{{ $verification->verified_institution ?? '—' }}</dd>
                <dt class="text-muted">Programme</dt><dd class="text-text">{{ $verification->verified_programme ?? '—' }}</dd>
                <dt class="text-muted">Verified At</dt><dd class="text-text">{{ $verification->verified_at?->format('M d, Y H:i') ?? '—' }}</dd>
            </dl>
        </div>
    @endif

    {{-- Audit trail --}}
    @if ($audit->count())
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-base font-extrabold text-text mb-4">Recent Audit Trail</h3>
            <div class="space-y-3">
                @foreach ($audit as $event)
                    <div class="flex items-start gap-3">
                        <span class="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-{{ $event->severity === 'high' ? 'red-400' : ($event->severity === 'medium' ? 'amber-400' : 'emerald-400') }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-text">{{ $event->description ?? $event->action }}</p>
                            <p class="text-xs text-muted">{{ $event->created_at?->format('M d, Y H:i:s') }} · {{ class_basename($event->resource_type ?? 'N/A') }} #{{ $event->resource_id ?? '—' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection