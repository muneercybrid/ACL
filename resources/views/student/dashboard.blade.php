@extends('layouts.app')

@section('title', 'Dashboard — ACL')

@section('header')
    <x-ui.page-header title="Student Dashboard" subtitle="Your academic progress" />
@endsection

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">

    {{-- Email verification warning banner --}}
    @if ($user && ! $user->email_verified_at)
        <div class="mb-8 rounded-2xl border-2 border-amber-400 bg-gradient-to-r from-amber-100 to-amber-50 px-6 py-5 shadow-lg shadow-amber-200/50">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-500 text-xl font-extrabold text-white shadow">⚠</span>
                    <div>
                        <h3 class="text-base font-extrabold text-amber-900">Verify your email address</h3>
                        <p class="text-sm font-medium text-amber-800">Please check <strong>{{ $user->email }}</strong> and click the confirmation link to unlock full access.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('student.email.resend') }}" class="shrink-0">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-bold text-white shadow transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-400">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 3.75a.75.75 0 0 1 .75.75v6a.75.75 0 0 1-1.5 0v-6a.75.75 0 0 1 .75-.75Zm0 9.5a.75.75 0 0 1 .75.75v1a.75.75 0 0 1-1.5 0v-1a.75.75 0 0 1 .75-.75Z" />
                        </svg>
                        Resend verification link
                    </button>
                </form>
            </div>
        </div>
    @endif

    {{-- Hero --}}
    <section class="mb-8 rounded-3xl border-[3px] border-border bg-gradient-to-br from-primary/10 via-emerald-50 to-terracotta/10 p-8 sm:p-10 shadow-[inset_-2px_-2px_8px_rgba(0,0,0,0.04)] relative overflow-hidden">
        <div class="relative z-10 max-w-2xl">
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-text leading-tight">Anyone Can Learn.<br><span class="text-primary">Your course. Your pace.</span></h1>
            <p class="mt-4 text-base sm:text-lg text-muted leading-relaxed">Verified student access to accredited Nigerian university programmes — with AI study assistance, smart progress tracking, and course content linked to your verified JAMB profile.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('student.my-courses') }}" class="inline-flex items-center rounded-xl bg-primary px-5 py-3 text-sm font-bold text-primary-fg shadow transition hover:bg-accent">
                    My Courses
                    <span class="ml-2 rounded-full bg-white/25 px-2 py-0.5 text-xs">{{ $dashboardCounts['available'] }}</span>
                </a>
                <a href="{{ route('student.profile') }}" class="inline-flex items-center rounded-xl border border-border bg-surface px-5 py-3 text-sm font-bold text-text shadow transition hover:border-primary">My Profile</a>
            </div>
        </div>
        <div class="pointer-events-none absolute right-0 top-0 hidden lg:block opacity-10">
            <img src="{{ asset('images/logo.svg') }}" alt="" class="h-64 w-64 object-contain rotate-12 translate-x-10 -translate-y-6">
        </div>
    </section>

    {{-- Stats --}}
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-muted">Programme</h3>
            <p class="mt-2 text-lg font-extrabold leading-snug text-text">{{ $academicProgramme?->name ?? 'Programme Not Assigned' }}</p>
            <p class="text-xs text-muted">Verified institution</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-muted">Level</h3>
            <p class="mt-2 text-2xl font-extrabold text-text">{{ $student?->level ?? 100 }}</p>
            <p class="text-xs text-muted">Current academic year</p>
        </div>
        <div class="rounded-2xl border border-primary/40 bg-primary/5 p-6 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-muted">Enrolled Courses</h3>
            <p class="mt-2 text-2xl font-extrabold text-text">{{ $dashboardCounts['enrolled'] }}</p>
            <p class="text-xs text-muted">of {{ $dashboardCounts['available'] }} available to you</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-muted">Status</h3>
            <p class="mt-2 text-2xl font-extrabold text-emerald-600">Verified</p>
            <p class="text-xs text-muted">JAMB Matriculation List</p>
        </div>
    </div>

    {{-- Where the courses come from. The full list lives on My Courses. --}}
    <section id="courses" class="mb-8 rounded-3xl border-[3px] border-border bg-surface p-6 sm:p-8 shadow-sm">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-extrabold text-text">Your courses at a glance</h2>
            <a href="{{ route('student.my-courses') }}" class="text-sm font-bold text-primary hover:underline">Open My Courses →</a>
        </div>

        @if ($dashboardCounts['available'] === 0)
            <p class="text-sm text-muted">
                No courses are mapped to your programme and level yet. Once your
                school publishes them they will appear here.
            </p>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-border bg-bg p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-muted">National curriculum</p>
                    <p class="mt-1 text-3xl font-extrabold text-text">{{ $dashboardCounts['national'] }}</p>
                    <p class="mt-1 text-xs text-muted">Shared with every programme and institution that lists them.</p>
                </div>
                <div class="rounded-2xl border border-border bg-bg p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-muted">Added by your school</p>
                    <p class="mt-1 text-3xl font-extrabold text-text">{{ $dashboardCounts['institution'] }}</p>
                    <p class="mt-1 text-xs text-muted">Specific to your institution and level.</p>
                </div>
            </div>
        @endif
    </section>

    {{-- Enrolled only. A student sees what they are actually taking, not the
         whole catalogue. --}}
    @php
        $enrolledEntries = $programmeCourses->filter(
            fn ($entry) => $entry->current_offering
                && in_array($entry->current_offering->id, $enrolledOfferingIds)
        );
    @endphp
    @if ($enrolledEntries->isNotEmpty())
        <section class="rounded-3xl border-[3px] border-border bg-surface p-6 sm:p-8 shadow-sm">
            <h2 class="mb-6 text-xl font-extrabold text-text">Continue learning</h2>
            <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                @foreach ($enrolledEntries as $entry)
                    <a href="{{ route('student.course.show', $entry->route_ref) }}"
                       class="group block rounded-2xl border border-border bg-bg p-5 shadow-sm transition hover:border-primary hover:shadow-md">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-bold text-text group-hover:text-primary">{{ $entry->title }}</h3>
                            <span class="shrink-0 rounded-full border border-border bg-surface px-2 py-0.5 text-[10px] font-semibold text-muted">{{ $entry->code }}</span>
                        </div>
                        <p class="mt-2 text-sm text-muted">Semester {{ $entry->semester }} · {{ $entry->credit_units ?? '—' }} units</p>
                        <p class="mt-3 text-sm font-semibold text-primary">Continue →</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
