@extends('superadmin.layout.app')
@section('title', 'Academic Explorer — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">Academic Structure Explorer</h1><p class="text-sm text-muted">Institutions → Faculties → Departments → Programmes → Levels → Courses</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-7xl space-y-8">
    {{-- Quick counts --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm"><div class="text-xl font-extrabold text-text">{{ $faculties->count() }}</div><div class="text-xs text-muted">Faculties configured</div></div>
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm"><div class="text-xl font-extrabold text-text">{{ $departments->count() }}</div><div class="text-xs text-muted">Departments configured</div></div>
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm"><div class="text-xl font-extrabold text-text">{{ $programmes->count() }}</div><div class="text-xs text-muted">NUC Programmes (active)</div></div>
    </div>

    {{-- Programmes (NUC curriculum) --}}
    <section>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-extrabold text-text">NUC Programmes ({{ $programmes->count() }})</h2>
            <a href="{{ route('superadmin.academic.structures') }}" class="text-sm font-bold text-primary hover:underline">Curriculum structures →</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($programmes as $programme)
                <a href="{{ route('superadmin.academic.programme', $programme) }}" class="group rounded-2xl border border-border bg-surface p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md hover:border-primary/30">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary text-xs font-bold">{{ strtoupper(substr($programme->name, 0, 2)) }}</span>
                        <h3 class="text-sm font-bold text-text group-hover:text-primary transition">{{ $programme->name }}</h3>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-muted">
                        @if ($programme->nucDiscipline)
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">{{ $programme->nucDiscipline->name }}</span>
                        @endif
                        <span>{{ $programme->curriculumVersions->count() }} versions</span>
                    </div>
                </a>
            @empty
                <div class="col-span-full rounded-2xl border border-border bg-surface p-10 text-center text-sm text-muted">No NUC programmes found.</div>
            @endforelse
        </div>
    </section>

    {{-- Department mapping --}}
    <section>
        <h2 class="text-lg font-extrabold text-text mb-4">Institution Departments</h2>
        <div class="space-y-4">
            @forelse ($faculties as $faculty)
                <div class="rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-3 bg-raised/60 border-b border-border">
                        <div class="flex items-center gap-2">
                            <x-ui.icon name="building-library" class="h-4 w-4 text-primary" />
                            <span class="text-sm font-bold text-text">{{ $faculty->name }}</span>
                            <span class="text-xs text-muted">· {{ $faculty->organization?->name }}</span>
                        </div>
                        <span class="text-xs text-muted">{{ $faculty->departments_count }} departments</span>
                    </div>
                    @php $depts = $departments->where('faculty_id', $faculty->id); @endphp
                    @if ($depts->count())
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 p-6">
                            @foreach ($depts as $dept)
                                <div class="rounded-xl border border-border p-3">
                                    <p class="text-sm font-semibold text-text">{{ $dept->name }}</p>
                                    <p class="text-xs text-muted">{{ $dept->academicPrograms->count() }} programmes · {{ $dept->code ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-6 text-sm text-muted">No departments configured.</div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-border bg-surface p-10 text-center text-sm text-muted">No faculties configured yet.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection