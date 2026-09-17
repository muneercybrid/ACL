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
                <span class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-bold text-white shadow">Resend verification link</span>
            </div>
        </div>
    @endif

    {{-- Hero / ACL landing --}}
    <section class="mb-8 rounded-3xl border-[3px] border-border bg-gradient-to-br from-primary/10 via-emerald-50 to-terracotta/10 p-8 sm:p-10 shadow-[inset_-2px_-2px_8px_rgba(0,0,0,0.04)] relative overflow-hidden">
        <div class="relative z-10 max-w-2xl">
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-text leading-tight">Anyone Can Learn.<br><span class="text-primary">Your course. Your pace.</span></h1>
            <p class="mt-4 text-base sm:text-lg text-muted leading-relaxed">Verified student access to accredited Nigerian university programmes — with AI study assistance, smart progress tracking, and course content linked to your verified JAMB profile.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('student.profile') }}" class="inline-flex items-center rounded-xl bg-primary px-5 py-3 text-sm font-bold text-primary-fg shadow transition hover:bg-accent">My Profile</a>
                <a href="#courses" class="inline-flex items-center rounded-xl border border-border bg-surface px-5 py-3 text-sm font-bold text-text shadow transition hover:border-primary">My Courses</a>
            </div>
        </div>
        <div class="pointer-events-none absolute right-0 top-0 hidden lg:block opacity-10">
            <img src="{{ asset('images/logo.svg') }}" alt="" class="h-64 w-64 object-contain rotate-12 translate-x-10 -translate-y-6">
        </div>
    </section>

    {{-- Stats bento grid --}}
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-muted">Programme</h3>
            <p class="mt-2 text-2xl font-extrabold text-text">{{ $academicProgramme?->name ?? 'Mass Communication' }}</p>
            <p class="text-xs text-muted">Verified institution</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-muted">Level</h3>
            <p class="mt-2 text-2xl font-extrabold text-text">{{ $student?->level ?? 100 }}</p>
            <p class="text-xs text-muted">Current academic year</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-muted">Courses Active</h3>
            <p class="mt-2 text-2xl font-extrabold text-text">13</p>
            <p class="text-xs text-muted">Semester offering linked</p>
        </div>
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-muted">Status</h3>
            <p class="mt-2 text-2xl font-extrabold text-emerald-600">Verified</p>
            <p class="text-xs text-muted">JAMB Matriculation List</p>
        </div>
    </div>

    {{-- Courses — level-filtered by student, split by semester per CCMAS --}}
    <section id="courses" class="rounded-3xl border-[3px] border-border bg-surface p-6 sm:p-8 shadow-sm">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-xl font-extrabold text-text">Level {{ $student?->level ?? 100 }} — NUC CCMAS Curriculum</h2>
            <a href="{{ route('student.my-courses') }}" class="text-sm font-bold text-primary hover:underline">Full list →</a>
        </div>

        @php $semesters = $programmeCoursesBySemester ?? collect(); @endphp
        @if($semesters->isEmpty())
            <p class="text-sm text-muted">No curriculum courses mapped for your current level yet.</p>
        @else
            @foreach($semesters as $level => $semestersInLevel)
                @foreach($semestersInLevel as $semNumber => $courseCollection)
                    <div class="mb-8">
                        <h3 class="mb-3 text-sm font-extrabold uppercase tracking-widest text-primary">Semester {{ $semNumber }}</h3>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @forelse($courseCollection as $cc)
                                <a href="{{ route('course.show', $cc->id) }}" class="rounded-xl border border-border bg-bg p-4 shadow-sm transition hover:border-primary hover:shadow-md">
                                    <h3 class="font-bold text-text">{{ $cc->course->code ?? 'CODE' }}</h3>
                                    <p class="text-xs text-muted">{{ $cc->course->name ?? 'Course title' }} — Level {{ $cc->level }}</p>
                                </a>
                            @empty
                                <p class="text-xs text-muted">No courses mapped for this semester.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            @endforeach
        @endif
    </section>
</div>
@endsection
