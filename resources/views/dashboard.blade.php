@extends('layouts.app')

@section('title', 'Dashboard — ACL')

@section('header')
    <x-ui.page-header title="Dashboard" :subtitle="'Welcome back, ' . auth()->user()->name . '.'" />
@endsection

@section('content')
<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-ui.stat-card label="Enrolled courses" :value="$enrollments->count()" icon="book-open" hint="This semester" />
    <x-ui.stat-card label="Progress" value="—" tone="primary" icon="academic-cap" hint="Tracking — coming soon" />
    <x-ui.stat-card label="Certificates" value="—" tone="info" icon="rectangle-stack" hint="Coming soon" />
</div>

<h2 class="mb-4 text-sm font-semibold text-text">This semester&rsquo;s courses</h2>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
@forelse ($enrollments as $enrollment)
    <x-ui.card hover class="group p-5">
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
    </x-ui.card>
@empty
    <div class="col-span-full">
        <x-ui.empty-state icon="book-open" title="No courses yet"
            message="Your courses appear here automatically once your institution enrols you for the semester." />
    </div>
@endforelse
</div>
@endsection
