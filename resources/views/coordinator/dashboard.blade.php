@extends('layouts.app')

@section('title', 'Level coordinator — ACL')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">

    <header class="mb-8">
        <h1 class="text-2xl font-extrabold tracking-tight text-text">
            Level Coordinator
        </h1>
        <p class="mt-1 text-sm text-muted">
            {{ auth()->user()->name }} — you see only the levels and programmes you are appointed to.
        </p>
    </header>

    @if (session('success'))
        <div class="mb-6 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if ($appointments->isNotEmpty())
        <div class="mb-8">
            <a href="{{ route('coordinator.courses.index') }}"
               class="press inline-flex items-center gap-2 rounded-xl border border-primary/40 bg-primary/5 px-5 py-3 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus:ring-2 focus:ring-ring">
                Choose programme courses
                <span aria-hidden="true">→</span>
            </a>
            <p class="mt-2 text-xs text-muted">
                Search the NUC CCMAS list, or enter a course it does not carry.
            </p>
        </div>
    @endif

    <section class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-muted">Appointments</p>
            <p class="mt-1 text-3xl font-extrabold text-text">{{ $appointments->count() }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-muted">Active</p>
            <p class="mt-1 text-3xl font-extrabold text-primary">{{ $activeCount }}</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-muted">Students in scope</p>
            <p class="mt-1 text-3xl font-extrabold text-text">{{ $students }}</p>
        </div>
    </section>

    @if ($appointments->isEmpty())
        <div class="rounded-2xl border border-border bg-surface p-10 text-center shadow-sm">
            <h2 class="text-lg font-bold text-text">No appointments yet</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-muted">
                Your account holds the level coordinator role but has no level_coordinators
                record yet, so there is nothing to show. Your institution administrator
                appoints coordinators; ask them to add your level.
            </p>
        </div>
    @else
        <section>
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-muted">
                Levels you coordinate
            </h2>

            <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-border bg-bg/60 text-xs uppercase tracking-wider text-muted">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Programme</th>
                            <th class="px-5 py-3 font-semibold">Level</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Appointed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($appointments as $appointment)
                            <tr>
                                <td class="px-5 py-3 font-medium text-text">
                                    {{ $appointment->programme?->name ?? 'Programme unavailable' }}
                                </td>
                                <td class="px-5 py-3 text-muted">{{ $appointment->level }}</td>
                                <td class="px-5 py-3">
                                    @if ($appointment->isActive())
                                        <span class="inline-flex rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-muted/10 px-2 py-0.5 text-xs font-semibold text-muted">{{ ucfirst((string) $appointment->status) }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-muted">
                                    {{ $appointment->appointed_date?->format('j M Y') ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
