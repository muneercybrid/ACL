@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-3xl py-8">

    <h1 class="text-2xl font-extrabold text-text">
        @if ($semester === 1)
            First semester — choose your courses
        @else
            Second semester — choose your courses
        @endif
    </h1>

    <p class="mt-1 text-sm text-muted">
        {{ $programme?->name ?? 'Programme not assigned' }} · Level {{ $level }}
    </p>

    {{-- The step indicator is a progress cue, not navigation. There is
         deliberately no link to semester 2: the flow is server-enforced, and
         offering a link that would only be refused is worse than not offering
         it. --}}
    <div class="mt-5 flex items-center gap-3" aria-label="Registration progress">
        <span @class([
            'rounded-full px-3 py-1 text-sm font-semibold',
            'bg-success text-success-fg' => $semester === 2,
            'bg-primary text-primary-fg' => $semester === 1,
        ])>
            1. First semester
            @if ($semester === 2)
                <span aria-hidden="true"> ✓</span>
            @endif
        </span>
        <span aria-hidden="true" class="text-muted">→</span>
        <span @class([
            'rounded-full px-3 py-1 text-sm font-semibold',
            'bg-primary text-primary-fg' => $semester === 2,
            'bg-muted text-muted' => $semester === 1,
        ])>
            2. Second semester
        </span>
    </div>

    @if ($semester === 2)
        <p class="mt-4 rounded-lg border border-border bg-surface px-4 py-3 text-sm text-muted">
            Your first semester is saved. Choose your second semester courses below.
        </p>
    @endif

    <form method="POST" action="{{ route('student.course.register.store') }}" class="mt-6">
        @csrf
        <input type="hidden" name="semester" value="{{ $semester }}">

        <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <h2 class="font-bold text-text">
                    @if ($semester === 1)
                        First semester courses
                    @else
                        Second semester courses
                    @endif
                    <span class="ml-1 text-sm font-normal text-muted">
                        ({{ $courses->count() }} available)
                    </span>
                </h2>
            </div>

            @forelse ($courses as $course)
                <label class="flex cursor-pointer items-start gap-3 border-b border-border px-4 py-3 last:border-b-0 hover:bg-raised">
                    <input type="checkbox"
                           name="courses[]"
                           value="{{ $course->course_id }}"
                           @checked($course->is_mandatory)
                           class="mt-1 h-4 w-4 shrink-0 rounded border-border text-primary focus:ring-ring">

                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-text">
                            {{ $course->code }}
                            <span class="font-normal text-muted">—</span>
                            {{ $course->title }}
                        </span>
                        <span class="mt-0.5 block text-xs text-muted">
                            {{ $course->credit_units }} credit unit{{ $course->credit_units === 1 ? '' : 's' }}
                            @if ($course->is_mandatory)
                                <span class="ml-2 font-semibold text-primary">Required</span>
                            @endif
                        </span>
                    </span>
                </label>
            @empty
                <div class="px-4 py-8 text-center text-sm text-muted">
                    No courses have been published for this semester yet. Your programme
                    coordinator has not released this list, so there is nothing to choose yet.
                </div>
            @endforelse
        </div>

        @if ($courses->isNotEmpty())
            <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-muted">
                    <span id="selected-count">0</span> selected ·
                    <span id="selected-credits">0</span> credit units
                </p>
                <button type="submit"
                        class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:ring-2 focus:ring-ring">
                    @if ($semester === 1)
                        Save and continue to second semester
                    @else
                        Complete registration
                    @endif
                </button>
            </div>
        @endif
    </form>
</div>

@push('scripts')
<script>
    // Progressive enhancement only: the form works without this, it just
    // cannot stop you submitting nothing. A required course that was left
    // unchecked is caught on the server as well, so this is a courtesy rather
    // than a control.
    (function () {
        var boxes = Array.prototype.slice.call(document.querySelectorAll('input[name="courses[]"]'));
        var count = document.getElementById('selected-count');
        var credits = document.getElementById('selected-credits');
        if (!boxes.length || !count || !credits) return;

        function unit(el) {
            var text = el.closest('label').textContent.match(/(\d+)\s+credit unit/);
            return text ? parseInt(text[1], 10) : 0;
        }

        function refresh() {
            var checked = boxes.filter(function (b) { return b.checked; });
            count.textContent = checked.length;
            credits.textContent = checked.reduce(function (sum, b) { return sum + unit(b); }, 0);
        }

        boxes.forEach(function (b) { b.addEventListener('change', refresh); });
        refresh();
    })();
</script>
@endpush
@endsection
