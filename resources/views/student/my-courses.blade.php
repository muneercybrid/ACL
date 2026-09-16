@extends('layouts.app')

@section('title', 'My Courses — ACL')

@section('header')
    <x-ui.page-header title="My Courses" subtitle="Your enrolled courses and programme curriculum" />
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6">

    @if ($enrollments->count() === 0 && $programmeCourses->count() === 0)
        <div class="rounded-2xl border border-dashed border-border bg-raised/40 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            <h3 class="mt-4 text-lg font-bold text-text">No courses yet</h3>
            <p class="mt-2 text-sm text-muted">Once your institution enrols you for the semester, your courses will appear here automatically.</p>
            <a href="{{ route('dashboard') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-fg shadow-sm transition hover:opacity-90">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to Dashboard
            </a>
        </div>
    @else
        @if ($academicProgramme)
            <!-- Programme Info -->
            <div class="mb-6 rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-primary">{{ $academicProgramme->name }}</p>
                        <h2 class="mt-1 text-xl font-extrabold text-text">Your Programme Curriculum</h2>
                        <p class="mt-1 text-sm text-muted">Courses available for your programme. Click any course to view chapters and outlines.</p>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <span class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">{{ $programmeCourses->count() }} courses</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- Active Enrollments (ground truth) -->
        @if ($activeEnrollments->count() > 0 || ($enrollments->count() > 0 && !$academicProgramme))
            <div class="mb-8">
                <h3 class="mb-4 text-lg font-bold text-text">Currently Enrolled</h3>
                <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    @if ($academicProgramme)
                        @foreach ($activeEnrollments as $cc)
                            @php $offering = $cc->current_offering; @endphp
                            <a href="{{ $offering ? route('courses.show', $offering) : '#' }}" class="group block rounded-xl border border-emerald-300/60 bg-emerald-50/40 p-5 transition hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-ring">
                                <div class="flex items-start justify-between gap-3">
                                    <h4 class="font-bold text-text group-hover:text-primary transition">{{ $cc->course->title }}</h4>
                                    <span class="inline-flex rounded-full border border-emerald-300 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700 uppercase tracking-wide">Enrolled</span>
                                </div>
                                <p class="mt-2 text-sm text-muted">{{ $cc->course->description ?? 'No description available.' }}</p>
                                <div class="mt-4 flex items-center gap-3 text-xs text-muted">
                                    <span>{{ $cc->course->credit_units ?? '-' }} credits</span>
                                    <span aria-hidden="true">•</span>
                                    <span>{{ $offering?->semester?->name ?? 'Current semester' }}</span>
                                </div>
                                <div class="mt-4 text-sm font-semibold text-primary">Continue learning →</div>
                            </a>
                        @endforeach
                    @else
                        @foreach ($enrollments as $enrollment)
                            <a href="{{ route('courses.show', $enrollment->courseOffering) }}"
                               class="group block rounded-xl border border-emerald-300/60 bg-emerald-50/40 p-5 transition hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-ring">
                                <div class="flex items-start justify-between gap-3">
                                    <h4 class="font-bold text-text group-hover:text-primary transition">{{ $enrollment->courseOffering->course->title }}</h4>
                                    <span class="inline-flex rounded-full border border-emerald-300 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700 uppercase tracking-wide">Enrolled</span>
                                </div>
                                <p class="mt-2 text-sm text-muted">{{ $enrollment->courseOffering->course->description ?? 'No description available.' }}</p>
                                <div class="mt-4 flex items-center gap-3 text-xs text-muted">
                                    <span>{{ $enrollment->courseOffering->course->credit_units ?? '-' }} credits</span>
                                    <span aria-hidden="true">•</span>
                                    <span>{{ $enrollment->courseOffering->semester?->name ?? 'Current semester' }}</span>
                                </div>
                                <div class="mt-4 text-sm font-semibold text-primary">Open course →</div>
                            </a>
                        @endforeach
                    @endif
                </div>
            </div>
        @endif

        <!-- Available Courses (not yet enrolled) -->
        @if ($programmeCourses->count() > 0)
            <div>
                <h3 class="mb-4 text-lg font-bold text-text">Programme Curriculum</h3>

                @foreach ($programmeCoursesBySemester as $level => $semesters)
                    <div class="mb-8">
                        <div class="mb-4 flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">Level {{ $level }}</span>
                            <span class="text-xs text-muted">{{ $semesters->flatten()->count() }} courses across {{ $semesters->count() }} semester{{ $semesters->count() === 1 ? '' : 's' }}</span>
                        </div>

                        @foreach ($semesters as $semester => $semesterCourses)
                            <div class="mb-6">
                                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted">
                                    Semester {{ $semester }} · {{ $semesterCourses->count() }} course{{ $semesterCourses->count() === 1 ? '' : 's' }}
                                </p>
                                <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($semesterCourses as $cc)
                                        @php
                                            $offering = $cc->current_offering;
                                            $isEnrolled = $offering && in_array($offering->id, $enrolledOfferingIds);
                                        @endphp
                                        <a href="{{ route('student.course.show', $cc->id) }}"
                                           class="group block rounded-xl border p-5 shadow-sm transition hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-ring {{ $isEnrolled ? 'border-emerald-300/60 bg-emerald-50/40' : 'border-border bg-surface/80' }}">
                                            <div class="flex items-start justify-between gap-3">
                                                <h4 class="font-bold text-text group-hover:text-primary transition">{{ $cc->course->title }}</h4>
                                                <span class="inline-flex shrink-0 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $isEnrolled ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-border bg-raised text-muted' }}">{{ $cc->course->code ?? 'N/A' }}</span>
                                            </div>
                                            <p class="mt-2 text-sm text-muted">{{ $cc->course->description ?? 'No description available.' }}</p>
                                            <div class="mt-4 flex items-center gap-3 text-xs text-muted">
                                                <span>{{ $cc->credit_units ?? $cc->course->credit_units ?? '-' }} credits</span>
                                                <span aria-hidden="true">•</span>
                                                <span>{{ ucfirst($cc->course_type ?? 'core') }}</span>
                                                @if ($isEnrolled)
                                                    <span class="ml-auto inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Enrolled</span>
                                                @endif
                                            </div>
                                            <div class="mt-4 text-sm font-semibold text-primary">{{ $isEnrolled ? 'Continue learning →' : 'View details →' }}</div>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
@endsection