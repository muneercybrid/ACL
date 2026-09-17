@extends('layouts.app')

@section('title', $curriculumCourse->course->title . ' — Course Outline — ACL')

@section('header')
    <x-ui.page-header 
        title="{{ $curriculumCourse->course->code . ': ' . $curriculumCourse->course->title }}"
        subtitle="Course outline, goals, and readiness questions"
    />
@endsection

@section('content')
<div class="mx-auto max-w-4xl px-4 py-6 sm:px-6">

    {{-- Course Meta --}}
    <div class="mb-6 rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-8">
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <span class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                Level {{ $curriculumCourse->level }}L
            </span>
            <span class="inline-flex items-center rounded-full bg-info/10 px-3 py-1 text-xs font-semibold text-info">
                Semester {{ $curriculumCourse->semester }}
            </span>
            <span class="inline-flex items-center rounded-full bg-accent/10 px-3 py-1 text-xs font-semibold text-accent">
                {{ ucfirst($curriculumCourse->course_type) }} · {{ $curriculumCourse->credit_units }} credits
            </span>
        </div>
        <p class="mt-4 text-sm text-muted">{{ $curriculumCourse->course->description ?? 'No description available.' }}</p>
    </div>

    {{-- Outline Content --}}
    @if ($outline)
        <div class="mb-6 space-y-6">
            {{-- Goal --}}
            <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-8">
                <h2 class="flex items-center gap-2 text-lg font-extrabold text-text">
                    <x-ui.icon name="target" class="h-5 w-5 text-primary" />
                    Course Goal
                </h2>
                <p class="mt-3 text-base leading-7 text-text">{{ $outline->goal ?? 'Goal not yet defined.' }}</p>
            </section>

            {{-- Aim --}}
            <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-8">
                <h2 class="flex items-center gap-2 text-lg font-extrabold text-text">
                    <x-ui.icon name="light-bulb" class="h-5 w-5 text-accent" />
                    Course Aim
                </h2>
                <p class="mt-3 text-base leading-7 text-text">{{ $outline->aim ?? 'Aim not yet defined.' }}</p>
            </section>

            {{-- Importance --}}
            <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-8">
                <h2 class="flex items-center gap-2 text-lg font-extrabold text-text">
                    <x-ui.icon name="star" class="h-5 w-5 text-warning" />
                    Why This Course Matters
                </h2>
                <p class="mt-3 text-base leading-7 text-text">{{ $outline->importance ?? 'Importance not yet defined.' }}</p>
            </section>

            {{-- Full Outline --}}
            <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-8">
                <h2 class="flex items-center gap-2 text-lg font-extrabold text-text">
                    <x-ui.icon name="document-text" class="h-5 w-5 text-info" />
                    Course Outline
                </h2>
                <div class="mt-3 prose prose-sm max-w-none text-text">
                    {{ $outline->description ?? $outline->outline_text ?? 'Outline content not yet available.' }}
                </div>
            </section>
        </div>

        {{-- Readiness Questions --}}
        @if ($questions->count() > 0)
            <section class="mb-6">
                <h2 class="mb-4 flex items-center gap-2 text-lg font-extrabold text-text">
                    <x-ui.icon name="question-mark-circle" class="h-5 w-5 text-info" />
                    Readiness Check
                </h2>
                <p class="mb-4 text-sm text-muted">
                    Answer these questions to confirm you understand what this course covers.
                    All answers must be correct to unlock the course.
                </p>

                <form action="{{ route('student.course.check-readiness', $curriculumCourse->id) }}" method="POST" class="space-y-4">
                    @csrf
                    @foreach ($questions as $index => $question)
                        <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-6">
                            <h3 class="font-semibold text-text">{{ $loop->iteration }}. {{ $question->question_text }}</h3>
                            
                            @if ($question->question_type === 'multiple_choice' && $question->options)
                                <div class="mt-3 space-y-2">
                                    @foreach ($question->options as $optKey => $optVal)
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <input type="radio" name="answers[{{ $question->id }}]" value="{{ $optKey }}" class="h-4 w-4 text-primary border-border focus:ring-primary" required>
                                            <span class="text-sm text-text">{{ $optVal }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <textarea name="answers[{{ $question->id }}]" rows="3" required
                                    class="mt-3 w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text placeholder-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                    placeholder="Type your answer here..."></textarea>
                            @endif
                        </div>
                    @endforeach

                    <div class="flex justify-end">
                        <button type="submit" class="press inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-primary-fg transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring">
                            <x-ui.icon name="check-circle" class="h-4 w-4" />
                            Submit & Unlock Course
                        </button>
                    </div>
                </form>
            </section>
        @else
            <div class="mb-6 rounded-2xl border border-dashed border-border bg-raised/40 p-6 text-center">
                <x-ui.icon name="clock" class="mx-auto h-10 w-10 text-muted" />
                <h3 class="mt-3 text-lg font-bold text-text">Course content being prepared</h3>
                <p class="mt-1 text-sm text-muted">
                    The outline and readiness questions for this course are still being generated.
                    Check back soon — you'll be notified when it's ready.
                </p>
            </div>
        @endif

    @else
        <div class="rounded-2xl border border-dashed border-border bg-raised/40 p-12 text-center">
            <x-ui.icon name="clock" class="mx-auto h-12 w-12 text-muted" />
            <h3 class="mt-4 text-lg font-bold text-text">Course content being prepared</h3>
            <p class="mt-2 text-sm text-muted">
                The outline and readiness questions for this course are still being generated by ACLi.
                Check back soon — you'll be notified when it's ready to start.
            </p>
        </div>
    @endif

    {{-- Back Link --}}
    <div class="mt-6">
        <a href="{{ route('student.my-courses') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Back to My Courses
        </a>
    </div>

</div>
@endsection