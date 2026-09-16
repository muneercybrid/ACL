@extends('superadmin.layout.app')
@section('title', $programme->name . ' — Programme — Superadmin')
@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('superadmin.academic') }}" class="text-muted hover:text-text transition"><x-ui.icon name="arrow-left" class="h-5 w-5" /></a>
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-text">{{ $programme->name }}</h1>
            <p class="text-sm text-muted">NUC Programme @if($programme->nucDiscipline) · {{ $programme->nucDiscipline->name }} @endif · {{ $versions->count() }} curriculum versions</p>
        </div>
    </div>
@endsection
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    {{-- Versions --}}
    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <h3 class="text-base font-extrabold text-text mb-3">Curriculum Versions</h3>
        @if ($versions->count())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($versions as $version)
                    <div class="rounded-xl border border-border p-4 {{ $version->id === $activeVersion?->id ? 'border-primary/40 bg-primary/5' : '' }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-bold text-text">{{ $version->version_label }}</span>
                            @if ($version->is_active)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Active</span>
                            @endif
                        </div>
                        <p class="text-xs text-muted">Session: {{ $version->academicSession?->name ?? '—' }}</p>
                        <p class="text-xs text-muted">Scope: <code>{{ $version->scope ?? '—' }}</code> · Source: {{ $version->source_type ?? '—' }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-muted">No curriculum versions yet.</p>
        @endif
    </div>

    {{-- Course map --}}
    @if ($activeVersion && $coursesByLevel->count())
        <div class="space-y-6">
            @php
                $semesterLabels = [1 => 'First Semester', 2 => 'Second Semester'];
                $levelLabels = [100 => '100 Level', 200 => '200 Level', 300 => '300 Level', 400 => '400 Level', 500 => '500 Level'];
            @endphp
            @foreach ($coursesByLevel as $level => $semesters)
                <div class="rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
                    <div class="px-6 py-3 bg-raised/60 border-b border-border">
                        <h4 class="text-sm font-extrabold text-text">{{ $levelLabels[$level] ?? $level . ' Level' }}</h4>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-px">
                        @foreach ($semesters as $semester => $courses)
                            <div class="p-6">
                                <h5 class="text-xs font-bold uppercase tracking-widest text-primary mb-3">{{ $semesterLabels[$semester] ?? 'Semester ' . $semester }} · {{ $courses->count() }} courses</h5>
                                <div class="space-y-2">
                                    @foreach ($courses as $cc)
                                        <div class="flex items-center justify-between rounded-lg bg-raised px-3 py-2">
                                            <div class="min-w-0">
                                                <span class="font-mono text-xs font-bold text-text">{{ $cc->course?->code }}</span>
                                                <span class="text-xs text-muted ml-2">{{ $cc->course?->title }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                @if ($cc->credit_units)<span class="text-[10px] text-muted">{{ $cc->credit_units }} units</span>@endif
                                                @if ($cc->course_type)<span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">{{ $cc->course_type }}</span>@endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-2xl border border-border bg-surface p-10 text-center text-sm text-muted">No curriculum courses assigned for this programme yet.</div>
    @endif
</div>
@endsection