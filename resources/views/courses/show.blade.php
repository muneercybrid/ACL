@extends('layouts.app')

@section('title', $offering->course->title . ' — ACL')

@section('header')
<div class="flex items-center gap-4">
    <a href="{{ route('dashboard') }}" class="text-sm text-muted transition hover:text-primary">&larr; Dashboard</a>
    <div>
        <h1 class="flex items-center gap-2 text-lg font-bold text-text">
            <span class="text-sm font-semibold text-primary">{{ $offering->course->code }}</span>
            {{ $offering->course->title }}
        </h1>
        <p class="text-xs text-muted">{{ $offering->semester->name }}</p>
    </div>
</div>
@endsection

@section('content')
<div class="flex h-[calc(100dvh-8rem)] flex-col gap-6 lg:flex-row">

    {{-- Sidebar: chapters & lessons --}}
    <aside class="custom-scrollbar w-full flex-shrink-0 overflow-y-auto rounded-2xl border border-border bg-surface lg:w-80">
        <div class="sticky top-0 z-10 border-b border-border bg-surface p-4">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-muted">Course modules</h2>
        </div>
        <div class="space-y-4 p-2">
            @foreach($offering->chapters as $chapter)
                <div>
                    <h3 class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-text">{{ $chapter->title }}</h3>
                    <ul class="space-y-1">
                        @foreach($chapter->lessons as $lesson)
                            <li>
                                <a href="{{ route('courses.lessons.show', [$offering, $lesson]) }}"
                                   class="flex items-center gap-3 rounded-lg border px-3 py-2 text-sm transition
                                          {{ $activeLesson && $activeLesson->id === $lesson->id
                                              ? 'border-primary/50 bg-raised text-text'
                                              : 'border-transparent text-muted hover:bg-raised hover:text-text' }}">
                                    <span class="text-[10px] {{ $activeLesson && $activeLesson->id === $lesson->id ? 'text-primary' : 'text-muted' }}">
                                        {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                    <span class="truncate">{{ $lesson->title }}</span>
                                    @if($completedLessonIds->contains($lesson->id))
                                        <span class="ml-auto text-primary" aria-label="Completed">&check;</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </aside>

    {{-- Main content --}}
    <main class="custom-scrollbar flex-1 overflow-y-auto rounded-2xl border border-border bg-surface p-6 lg:p-10">
        @if($activeLesson)
            <header class="mb-8 border-b border-border pb-6">
                <div class="mb-2 text-xs text-muted">
                    {{ $activeLesson->chapter->title }} / Lesson {{ str_pad($activeLesson->position, 2, '0', STR_PAD_LEFT) }}
                </div>
                <h2 class="text-3xl font-extrabold tracking-tight text-text">{{ $activeLesson->title }}</h2>
            </header>

            <article class="max-w-none space-y-6 leading-relaxed text-text">
                @foreach($activeLesson->blocks as $block)
                    @if($block->type === 'text')
                        <p class="text-base">{{ $block->data['content'] ?? '' }}</p>
                    @elseif($block->type === 'video')
                        <div class="aspect-video overflow-hidden rounded-xl border border-border bg-bg">
                            <iframe src="{{ $block->data['url'] ?? '' }}" class="h-full w-full" allowfullscreen title="{{ $block->data['title'] ?? 'Lesson video' }}"></iframe>
                        </div>
                        @isset($block->data['title'])
                            <p class="-mt-4 text-center text-xs text-muted">{{ $block->data['title'] }}</p>
                        @endisset
                    @elseif($block->type === 'image')
                        <img src="{{ $block->data['url'] ?? '' }}" alt="{{ $block->data['alt'] ?? '' }}" class="rounded-xl border border-border">
                    @elseif($block->type === 'link')
                        <a href="{{ $block->data['url'] ?? '#' }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-sm font-semibold text-info hover:underline">
                            &rarr; {{ $block->data['title'] ?? 'External resource' }}
                        </a>
                    @endif
                @endforeach
            </article>

            <div class="mt-12 flex justify-end border-t border-border pt-6">
                @if($completedLessonIds->contains($activeLesson->id))
                    <button disabled class="rounded-lg border border-primary/30 bg-primary/10 px-6 py-3 text-sm font-semibold text-primary">
                        &check; Completed
                    </button>
                @else
                    <button id="complete-lesson-btn"
                            onclick="completeLesson({{ $activeLesson->id }})"
                            class="press rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
                        Mark as complete
                    </button>
                @endif
            </div>
        @else
            <div class="flex h-full items-center justify-center text-muted">
                Select a lesson from the sidebar to begin.
            </div>
        @endif
    </main>
</div>

<script>
async function completeLesson(lessonId) {
    const btn = document.getElementById('complete-lesson-btn');
    btn.disabled = true;
    btn.innerText = 'Saving…';
    btn.classList.add('opacity-60');
    try {
        const response = await fetch(`/lessons/${lessonId}/complete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        });
        if (response.ok) {
            btn.innerText = '✓ Completed';
            btn.classList.remove('bg-primary', 'text-primary-fg', 'opacity-60');
            btn.classList.add('border', 'border-primary/30', 'bg-primary/10', 'text-primary');
            document.querySelector('main').classList.add('flash-success');
            setTimeout(() => location.reload(), 700);
        } else {
            throw new Error('Request failed');
        }
    } catch (e) {
        btn.disabled = false;
        btn.innerText = 'Mark as complete';
        btn.classList.remove('opacity-60');
    }
}
</script>
@endsection
