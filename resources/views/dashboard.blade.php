@extends('layouts.app')

@section('title', 'Dashboard — ACL')

@section('header')
<div>
    <h1 class="text-lg font-bold text-text">Dashboard</h1>
    <p class="text-xs text-muted">Welcome back, {{ auth()->user()->name }}.</p>
</div>
@endsection

@section('content')
<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-2xl border border-border bg-surface p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-muted">Enrolled courses</p>
        <p class="mt-1 text-3xl font-extrabold text-text">{{ $enrollments->count() }}</p>
        <p class="mt-1 text-xs text-muted">This semester</p>
    </div>
    <div class="rounded-2xl border border-border bg-surface p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-muted">Progress</p>
        <p class="mt-1 text-3xl font-extrabold text-primary">&mdash;</p>
        <p class="mt-1 text-xs text-muted">Tracking &mdash; coming soon</p>
    </div>
    <div class="rounded-2xl border border-border bg-surface p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-muted">Certificates</p>
        <p class="mt-1 text-3xl font-extrabold text-info">&mdash;</p>
        <p class="mt-1 text-xs text-muted">Coming soon</p>
    </div>
</div>

<h2 class="mb-4 text-sm font-semibold text-text">This semester&rsquo;s courses</h2>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
@forelse ($enrollments as $enrollment)
    <article class="group rounded-2xl border border-border bg-surface p-5 transition hover:border-primary/50">
        <div class="mb-3 flex items-center justify-between">
            <span class="text-sm font-semibold text-primary">{{ $enrollment->courseOffering->course->code }}</span>
            <span class="rounded-full border border-border px-2 py-0.5 text-[10px] uppercase tracking-wide text-muted">
                {{ str_replace('_', ' ', $enrollment->source) }}
            </span>
        </div>
        <h3 class="mb-1 font-semibold text-text">{{ $enrollment->courseOffering->course->title }}</h3>
        <p class="mb-4 text-xs text-muted">
            {{ $enrollment->courseOffering->course->credit_units }} credit units &bull; {{ $enrollment->courseOffering->semester->name }}
        </p>
        <a href="{{ route('courses.show', $enrollment->courseOffering) }}"
           class="press block w-full rounded-lg border border-primary/40 py-2 text-center text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus:ring-2 focus:ring-ring">
            Open course
        </a>
    </article>
@empty
    <div class="col-span-full rounded-2xl border border-dashed border-border bg-surface p-10 text-center">
        <p class="font-semibold text-text">No courses yet</p>
        <p class="mx-auto mt-2 max-w-md text-sm text-muted">Your courses appear here automatically once your institution enrols you for the semester.</p>
    </div>
@endforelse
</div>
@endsection
