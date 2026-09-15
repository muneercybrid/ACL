@extends('layouts.app')

@section('title', '{{ $curriculumCourse->course->title }} — ACL')

@section('header')
    <x-ui.page-header title="{{ $curriculumCourse->course->title }}" subtitle="{{ $curriculumCourse->course->code }} · {{ $curriculumCourse->course->credit_units }} credits" />
@endsection

@section('content')
<div class="mx-auto max-w-5xl px-4 py-6 sm:px-6">

    @if ($enrollment)
        <a href="{{ route('courses.show', $enrollment->courseOffering) }}" class="inline-flex items-center gap-2 mb-6 text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-ring">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Open Course Viewer
        </a>
    @else
        <a href="{{ route('student.my-courses') }}" class="inline-flex items-center gap-2 mb-6 text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-ring">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to My Courses
        </a>
    @endif

    <!-- Course Header -->
    <div class="mb-8 rounded-2xl border border-border bg-surface p-6 shadow-sm sm:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <span class="inline-flex rounded-full border border-primary/40 bg-primary/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-primary">
                    {{ $curriculumCourse->course->credit_units }} Credits
                </span>
                <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-text sm:text-3xl">{{ $curriculumCourse->course->title }}</h1>
                <p class="mt-1 text-sm text-muted">Course Code: <span class="font-mono text-text">{{ $curriculumCourse->course->code }}</span></p>
            </div>
            @if ($enrollment)
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-4 py-2 text-sm font-semibold text-emerald-700">
                    <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Enrolled
                </span>
            @endif
        </div>

        <div class="mt-5 flex flex-wrap gap-3">
            @if ($currentOffering?->semester?->name)
                <span class="inline-flex items-center rounded-full bg-raised px-3 py-1 text-xs font-semibold text-muted">
                    <svg class="h-3 w-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ $currentOffering->semester->name }}
                </span>
            @endif
            @if ($currentOffering?->semester?->academicSession?->name)
                <span class="inline-flex items-center rounded-full bg-raised px-3 py-1 text-xs font-semibold text-muted">
                    <svg class="h-3 w-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    {{ $currentOffering->semester->academicSession->name }}
                </span>
            @endif
            <span class="inline-flex items-center rounded-full bg-raised px-3 py-1 text-xs font-semibold text-muted">
                Level {{ $curriculumCourse->level ?? 'N/A' }} · Semester {{ $curriculumCourse->semester ?? 'N/A' }}
            </span>
        </div>

        <div class="mt-6 prose prose-sm max-w-none text-muted">
            <p>{{ $curriculumCourse->course->description ?? 'No description available for this course.' }}</p>
        </div>
    </div>

    <!-- Tabs for Course Content -->
    <div class="mb-6 flex gap-1 overflow-x-auto rounded-xl bg-surface p-1 shadow-sm border border-border">
        <button onclick="showContentTab('outline')" class="content-tab-btn flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-text transition hover:bg-raised focus:outline-none focus:ring-2 focus:ring-ring whitespace-nowrap bg-primary text-primary-fg shadow-sm" id="tab-outline">Course Outline</button>
        <button onclick="showContentTab('chapters')" class="content-tab-btn flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-text transition hover:bg-raised focus:outline-none focus:ring-2 focus:ring-ring whitespace-nowrap" id="tab-chapters">Chapters</button>
        <button onclick="showContentTab('outcomes')" class="content-tab-btn flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-text transition hover:bg-raised focus:outline-none focus:ring-2 focus:ring-ring whitespace-nowrap" id="tab-outcomes">Learning Outcomes</button>
    </div>

    <!-- COURSE OUTLINE -->
    <section id="content-outline" class="content-section mb-8 rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="border-b border-border bg-raised/30 px-6 py-4">
            <h2 class="text-lg font-extrabold text-text">Course Outline</h2>
            <p class="mt-0.5 text-xs text-muted">Detailed curriculum structure and resources</p>
        </div>
        <div class="p-6">
            @php $outline = $curriculumCourse->course->outlines->first(); @endphp
            @if ($outline)
                <div class="prose prose-sm max-w-none">
                    <div class="mb-4">
                        <h3 class="font-bold text-text mb-2">Description</h3>
                        <p class="text-muted">{{ $outline->description }}</p>
                    </div>
                    @if ($outline->learning_outcomes && count($outline->learning_outcomes) > 0)
                        <div class="mb-4">
                            <h3 class="font-bold text-text mb-2">Learning Outcomes</h3>
                            <ul class="list-disc list-inside space-y-1 text-muted">
                                @foreach ($outline->learning_outcomes as $outcome)
                                    <li>{{ $outcome }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($outline->recommended_resources && count($outline->recommended_resources) > 0)
                        <div>
                            <h3 class="font-bold text-text mb-2">Recommended Resources</h3>
                            <ul class="list-disc list-inside space-y-1 text-muted">
                                @foreach ($outline->recommended_resources as $resource)
                                    <li>{{ $resource }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @else
                <div class="rounded-xl border border-dashed border-border bg-raised/40 p-8 text-center">
                    <svg class="mx-auto h-10 w-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <h3 class="mt-4 text-base font-semibold text-text">Course outline not yet published</h3>
                    <p class="mt-1 text-sm text-muted">The course outline is currently being prepared. Check back later.</p>
                </div>
            @endif
        </div>
    </section>

    <!-- CHAPTERS -->
    <section id="content-chapters" class="content-section hidden mb-8 rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="border-b border-border bg-raised/30 px-6 py-4">
            <h2 class="text-lg font-extrabold text-text">Chapters</h2>
            <p class="mt-0.5 text-xs text-muted">Structured learning content by chapter</p>
        </div>
        <div class="p-6">
            @php $chapters = $curriculumCourse->course->chapters()->orderBy('position')->get(); @endphp
            @if ($chapters->count() > 0)
                <div class="space-y-3">
                    @foreach ($chapters as $chapter)
                        <div class="group rounded-xl border border-border bg-bg/60 p-5 transition hover:border-primary/20 hover:bg-bg">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary text-sm font-bold">{{ $chapter->position }}</span>
                                        <div>
                                            <h3 class="font-bold text-text">{{ $chapter->title }}</h3>
                                            <p class="text-xs text-muted">{{ $chapter->slug }}</p>
                                        </div>
                                    </div>
                                    @if ($chapter->introduction)
                                        <p class="mt-2 text-sm text-muted">{{ $chapter->introduction }}</p>
                                    @endif
                                </div>
                                @if ($enrollment)
                                <a href="{{ route('courses.lessons.show', [$enrollment->courseOffering, $chapter->lessons->first()]) }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-border bg-raised px-3 py-1.5 text-sm font-semibold text-text transition hover:border-primary hover:bg-bg">
                                @else
                                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-border bg-raised px-3 py-1.5 text-sm font-semibold text-muted cursor-not-allowed">
                                @endif
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    {{ $enrollment ? 'View' : 'Enroll to view' }}
                                @if ($enrollment)
                                </a>
                                @else
                                </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-xl border border-dashed border-border bg-raised/40 p-8 text-center">
                    <svg class="mx-auto h-10 w-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    <h3 class="mt-4 text-base font-semibold text-text">No chapters available</h3>
                    <p class="mt-1 text-sm text-muted">Chapters will be added when the course content is ready.</p>
                </div>
            @endif
        </div>
    </section>

    <!-- LEARNING OUTCOMES -->
    <section id="content-outcomes" class="content-section hidden mb-8 rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="border-b border-border bg-raised/30 px-6 py-4">
            <h2 class="text-lg font-extrabold text-text">Learning Outcomes</h2>
            <p class="mt-0.5 text-xs text-muted">What you'll be able to do after completing this course</p>
        </div>
        <div class="p-6">
            @php $outline = $curriculumCourse->course->outlines->first(); @endphp
            @if ($outline && $outline->learning_outcomes && count($outline->learning_outcomes) > 0)
                <ol class="space-y-3">
                    @foreach ($outline->learning_outcomes as $index => $outcome)
                        <li class="relative pl-10 rounded-xl border border-border bg-bg/60 p-4">
                            <span class="absolute left-3 top-4 h-6 w-6 rounded-full bg-primary/10 text-primary text-xs font-bold flex items-center justify-center">{{ $index + 1 }}</span>
                            <p class="text-sm text-text">{{ $outcome }}</p>
                        </li>
                    @endforeach
                </ol>
            @else
                <div class="rounded-xl border border-dashed border-border bg-raised/40 p-8 text-center">
                    <svg class="mx-auto h-10 w-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <h3 class="mt-4 text-base font-semibold text-text">Learning outcomes not yet published</h3>
                    <p class="mt-1 text-sm text-muted">Outcomes will be published when the course outline is finalized.</p>
                </div>
            @endif
        </div>
    </section>

    <script>
        function showContentTab(tabId) {
            ['outline', 'chapters', 'outcomes'].forEach(function(id) {
                document.getElementById('content-' + id).classList.add('hidden');
                document.getElementById('tab-' + id).classList.remove('bg-primary', 'text-primary-fg', 'shadow-sm');
                document.getElementById('tab-' + id).classList.add('text-text');
            });
            document.getElementById('content-' + tabId).classList.remove('hidden');
            document.getElementById('tab-' + tabId).classList.remove('text-text');
            document.getElementById('tab-' + tabId).classList.add('bg-primary', 'text-primary-fg', 'shadow-sm');
        }
    </script>
</div>
@endsection