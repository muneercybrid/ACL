@extends('superadmin.layout.app')
@section('title', $onboarding->organization->name . ' — Onboarding — Superadmin')
@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('superadmin.onboarding') }}" class="text-muted hover:text-text transition"><x-ui.icon name="arrow-left" class="h-5 w-5" /></a>
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-text">Onboarding — {{ $onboarding->organization->name }}</h1>
            <p class="text-sm text-muted">Status: {{ str_replace('_', ' ', $onboarding->status) }} · {{ $onboarding->getCompletionPercentage() }}% complete</p>
        </div>
    </div>
@endsection
@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-extrabold text-text">Onboarding Progress</h2>
            <div class="flex items-center gap-2">
                <div class="h-2.5 w-40 rounded-full bg-raised overflow-hidden">
                    <div class="h-full rounded-full bg-primary transition-all duration-500" style="width: {{ $onboarding->getCompletionPercentage() }}%"></div>
                </div>
                <span class="text-sm font-extrabold text-primary">{{ $onboarding->getCompletionPercentage() }}%</span>
            </div>
        </div>

        <div class="space-y-2 mb-6">
            @php
                $steps = [
                    ['key'=>'institution_info','label'=>'Institution Setup','icon'=>'building-library'],
                    ['key'=>'admin_profile','label'=>'Administrator Account','icon'=>'shield-check'],
                    ['key'=>'academic_structure','label'=>'Academic Structure (faculties / departments / programmes)','icon'=>'academic-cap'],
                    ['key'=>'staff_setup','label'=>'Staff Setup (coordinators / moderators / tutors)','icon'=>'users'],
                    ['key'=>'content_setup','label'=>'Content Setup (chapters / lessons / materials)','icon'=>'book-open'],
                ];
                $progress = $onboarding->progress ?? [];
            @endphp
            @foreach ($steps as $i => $step)
                @php $done = !empty($progress[$step['key']]); @endphp
                <div class="flex items-center gap-4 rounded-xl px-4 py-3 {{ $done ? 'bg-emerald-50' : 'bg-raised' }}">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $done ? 'bg-emerald-500 text-white' : 'bg-surface border border-border text-muted' }}">
                        @if ($done)
                            <x-ui.icon name="check" class="h-4 w-4" />
                        @else
                            <span class="text-xs font-bold">{{ $i + 1 }}</span>
                        @endif
                    </div>
                    <span class="text-sm font-medium {{ $done ? 'text-emerald-800' : 'text-muted' }}">{{ $step['label'] }}</span>
                </div>
            @endforeach
        </div>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <dt class="text-muted">Institution</dt><dd class="font-medium text-text"><a href="{{ route('superadmin.institutions.show', $onboarding->organization) }}" class="text-primary hover:underline">{{ $onboarding->organization->name }}</a></dd>
            <dt class="text-muted">Status</dt><dd class="font-medium text-text capitalize">{{ str_replace('_', ' ', $onboarding->status) }}</dd>
            <dt class="text-muted">Administrator</dt><dd class="font-medium text-text">{{ $onboarding->administrator?->name ?? 'Not assigned' }}</dd>
            <dt class="text-muted">Invited By</dt><dd class="font-medium text-text">{{ $onboarding->invitedBy?->name ?? '—' }}</dd>
            <dt class="text-muted">Invited At</dt><dd class="font-medium text-text">{{ $onboarding->invited_at?->format('M d, Y H:i') ?? '—' }}</dd>
            <dt class="text-muted">Started At</dt><dd class="font-medium text-text">{{ $onboarding->started_at?->format('M d, Y H:i') ?? '—' }}</dd>
            <dt class="text-muted">Completed At</dt><dd class="font-medium text-text">{{ $onboarding->completed_at?->format('M d, Y H:i') ?? '—' }}</dd>
        </dl>
    </div>

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <h3 class="text-base font-extrabold text-text mb-4">Academic Structure Snapshot</h3>
        @php
            $faculties = $onboarding->organization->faculties;
            $departments = $faculties->flatMap->departments;
            $programmes = $departments->flatMap->academicPrograms;
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-center">
            <div class="rounded-xl bg-raised p-4"><div class="text-xl font-extrabold text-text">{{ $faculties->count() }}</div><div class="text-xs text-muted">Faculties</div></div>
            <div class="rounded-xl bg-raised p-4"><div class="text-xl font-extrabold text-text">{{ $departments->count() }}</div><div class="text-xs text-muted">Departments</div></div>
            <div class="rounded-xl bg-raised p-4"><div class="text-xl font-extrabold text-text">{{ $programmes->count() }}</div><div class="text-xs text-muted">Programmes</div></div>
        </div>
    </div>

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <h3 class="text-base font-extrabold text-text mb-2">Next Steps</h3>
        @if ($onboarding->isCompleted())
            <p class="text-sm text-emerald-700 font-medium">Onboarding is complete. The institution is fully operational.</p>
        @else
            <div class="space-y-2">
                @if (empty($progress['institution_info']))
                    <p class="text-sm text-muted flex gap-2"><span>✓</span> Confirm institution information</p>
                @endif
                @if (empty($progress['admin_profile']))
                    <p class="text-sm text-muted flex gap-2"><span>→</span> Assign an institution administrator</p>
                @endif
                @if (empty($progress['academic_structure']))
                    <p class="text-sm text-muted flex gap-2"><span>→</span> Configure faculties, departments and programmes</p>
                @endif
                @if (empty($progress['staff_setup']))
                    <p class="text-sm text-muted flex gap-2"><span>→</span> Invite the institution staff</p>
                @endif
                @if (empty($progress['content_setup']))
                    <p class="text-sm text-muted flex gap-2"><span>→</span> Prepare academic content</p>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection