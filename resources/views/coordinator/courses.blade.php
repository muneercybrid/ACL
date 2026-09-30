@extends('layouts.app')

@section('title', 'Programme courses — ACL')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">

    <header class="mb-8">
        <h1 class="text-2xl font-extrabold tracking-tight text-text">Programme Courses</h1>
        <p class="mt-1 text-sm text-muted">
            Choose the courses your programme runs at each level. Courses are shared across the platform — you choose what your school runs and the code it calls each one by.
        </p>
    </header>

    @if (session('success'))
        <div class="mb-6 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm font-medium text-text">{{ session('success') }}</div>
    @endif
    @error('course')<div class="mb-6 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

    @if ($offerings->isEmpty())
        <div class="rounded-2xl border border-border bg-surface p-10 text-center shadow-sm">
            <h2 class="text-lg font-bold text-text">No appointment yet</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-muted">
                You can add courses once your institution administrator has appointed you to a
                programme and level.
            </p>
        </div>
    @else
        {{-- Programme and level switcher --}}
        <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted">Programme</span>
                <select name="offering" onchange="this.form.submit()" class="mt-1 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
                    @foreach ($offerings as $row)
                        <option value="{{ $row->id }}" @selected($offering && $row->id === $offering->id)>
                            {{ $row->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted">Level</span>
                <select name="level" onchange="this.form.submit()" class="mt-1 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
                    @foreach ($offering->levels as $option)
                        <option value="{{ $option }}" @selected($level === $option)>Level {{ $option }}</option>
                    @endforeach
                </select>
            </label>
        </form>

        <div class="grid gap-8 lg:grid-cols-2">

            {{-- Current list --}}
            <section>
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-muted">
                    Level {{ $level }} courses ({{ $courses->count() }})
                </h2>

                @if ($courses->isEmpty())
                    <div class="rounded-2xl border border-dashed border-border bg-surface p-8 text-center">
                        <p class="text-sm text-muted">No courses added at this level yet.</p>
                    </div>
                @else
                    <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
                        <ul class="divide-y divide-border">
                            @foreach ($courses as $course)
                                <li class="flex items-start justify-between gap-3 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-semibold text-text">
                                            <span class="font-mono">{{ $course->course_code }}</span>
                                            <span class="ml-2 font-normal text-text">{{ $course->title }}</span>
                                        </p>
                                        <p class="mt-0.5 text-xs text-muted">
                                            @if ($course->credit_units !== null)
                                                {{ rtrim(rtrim(number_format($course->credit_units, 1), '0'), '.') }} units
                                            @else
                                                Units not stated
                                            @endif
                                            @if ($course->semester) · {{ $course->semester }} @endif
                                            · <span class="{{ $course->source === 'ccmas' ? 'text-primary' : 'text-amber-700' }}">{{ $course->source === 'ccmas' ? 'NUC CCMAS' : 'Entered manually' }}</span>
                                        </p>
                                    </div>
                                    <form method="POST" action="{{ route('coordinator.courses.destroy', $course->id) }}"
                                          onsubmit="return confirm('Remove {{ $course->course_code }} from this level?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Remove</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            {{-- Add --}}
            <section class="space-y-6">
                <div>
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-muted">Add a shared course</h2>

                    <form method="POST" action="{{ route('coordinator.courses.store.shared') }}"
                          class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                        @csrf
                        <input type="hidden" name="academic_program_id" value="{{ $offering->id }}">
                        <input type="hidden" name="level" value="{{ $level }}">

                        <label class="mb-4 block">
                            <span class="text-sm font-medium text-text">Search the CCMAS list</span>
                            <input type="search" id="ccmas-search" autocomplete="off"
                                   placeholder="Course title or code, e.g. Introduction to Computing"
                                   class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
                        </label>

                        <div id="ccmas-results" class="mb-4 max-h-64 overflow-y-auto rounded-lg border border-border">
                            <p class="px-3 py-4 text-center text-xs text-muted">Type to search every course on the platform.</p>
                        </div>

                        <p class="text-xs text-muted">
                            A course found here is already shared. Give it your school's code and its
                            content is reused — you do not write the outline again.
                        </p>
                    </form>
                </div>

                <div>
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-muted">Or add a course not in the catalogue</h2>

                    <form method="POST" action="{{ route('coordinator.courses.store.manual') }}"
                          class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                        @csrf
                        <input type="hidden" name="academic_program_id" value="{{ $offering->id }}">
                        <input type="hidden" name="level" value="{{ $level }}">

                        <label class="mb-3 block">
                            <span class="text-sm font-medium text-text">Course code</span>
                            <input type="text" name="course_code" value="{{ old('course_code') }}" required maxlength="32"
                                   class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
                        </label>

                        <label class="mb-3 block">
                            <span class="text-sm font-medium text-text">Course title</span>
                            <input type="text" name="title" value="{{ old('title') }}" required
                                   class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
                        </label>

                        <label class="mb-4 block">
                            <span class="text-sm font-medium text-text">Credit units</span>
                            <input type="number" name="credit_units" value="{{ old('credit_units') }}" step="0.5" min="0" max="30"
                                   class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
                        </label>

                        @error('course_code')<p class="mb-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        @error('title')<p class="mb-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        @error('credit_units')<p class="mb-2 text-sm text-red-700">{{ $message }}</p>@enderror

                        <button type="submit"
                                class="press w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
                            Add course
                        </button>

                        <p class="mt-3 text-xs text-muted">
                            Use this when no existing course matches. It is created on the platform so
                            other schools can find and reuse it.
                        </p>
                    </form>
                </div>
            </section>
        </div>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const box = document.getElementById('ccmas-search');
    const out = document.getElementById('ccmas-results');
    if (!box || !out) return;

    const programmeId = document.querySelector('input[name="academic_program_id"]').value;
    const level = document.querySelector('input[name="level"]').value;
    let timer = null;

    async function search() {
        const term = box.value.trim();
        if (term.length < 2) {
            out.innerHTML = '<p class="px-3 py-4 text-center text-xs text-muted">Type at least 2 characters to search.</p>';
            return;
        }

        out.innerHTML = '<p class="px-3 py-4 text-center text-xs text-muted">Searching…</p>';

        try {
            const response = await fetch(
                `/coordinator/courses/search?q=${encodeURIComponent(term)}`,
                { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }
            );
            const data = await response.json();

            if (!data.results || data.results.length === 0) {
                out.innerHTML = '<p class="px-3 py-4 text-center text-xs text-muted">No CCMAS course matched. Enter it yourself below.</p>';
                return;
            }

            out.innerHTML = '';
            data.results.forEach((c) => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route('coordinator.courses.store.shared') }}';

                const shared = c.shared_with > 0
                    ? `<span class="text-primary">shared by ${c.shared_with} ${c.shared_with === 1 ? 'school' : 'schools'}</span>`
                    : `<span class="text-muted">not yet shared</span>`;

                form.innerHTML = `
                    @csrf
                    <input type="hidden" name="academic_program_id" value="${programmeId}">
                    <input type="hidden" name="level" value="${level}">
                    <input type="hidden" name="course_id" value="${c.id}">
                    <div class="px-3 py-2.5 hover:bg-bg">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-text">${c.title}</p>
                                <p class="text-[11px] text-muted">
                                    <span class="font-mono">${c.code}</span>
                                    ${c.credit_units !== null ? ' · ' + c.credit_units + ' units' : ''}
                                    ${c.from_ccmas ? ' · NUC' : ''}
                                </p>
                                <p class="text-[11px] mt-0.5">${shared}</p>
                            </div>
                        </div>
                        <div class="mt-2">
                            <input type="text" name="course_code" required maxlength="64"
                                   placeholder="Your school's code"
                                   value="${c.local_code || ''}"
                                   class="w-full rounded-md border border-border bg-bg px-2 py-1.5 text-xs text-text focus:border-primary focus:outline-none focus:ring-1 focus:ring-ring">
                        </div>
                        <div class="mt-2 flex justify-end">
                            <button type="submit" class="text-xs font-semibold text-primary hover:underline">Add to level ${level}</button>
                        </div>
                    </div>`;

                out.appendChild(form);
            });
        } catch (e) {
            out.innerHTML = '<p class="px-3 py-4 text-center text-xs text-red-700">Search failed. Enter the course below instead.</p>';
        }
    }

    // Debounced so a fast typist does not fire a request per keystroke — this
    // matters on the low-bandwidth connections the platform is built for.
    box.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(search, 300);
    });
})();
</script>
@endpush
