@extends('layouts.app')

@section('title', $offering->course->title . ' — ACL')

@section('header')
<div class="flex items-center gap-4">
    <a href="{{ route('dashboard') }}" class="font-mono text-xs text-slate-500 hover:text-glow transition">← DASHBOARD</a>
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <span class="font-mono text-terminal text-sm">{{ $offering->course->code }}</span> 
            {{ $offering->course->title }}
        </h1>
        <p class="text-[11px] font-mono text-slate-500">{{ $offering->semester->name }}</p>
    </div>
</div>
@endsection

@section('content')
<div class="flex flex-col lg:flex-row gap-6 h-[calc(100vh-8rem)]">
    
    {{-- Sidebar: Chapters & Lessons --}}
    <aside class="w-full lg:w-80 flex-shrink-0 bg-surface border border-edge rounded-xl overflow-y-auto custom-scrollbar">
        <div class="p-4 border-b border-edge sticky top-0 bg-surface z-10">
            <h2 class="font-mono text-[11px] uppercase tracking-widest text-slate-400">Course Modules</h2>
        </div>
        <div class="p-2 space-y-4">
            @foreach($offering->chapters as $chapter)
                <div>
                    <h3 class="px-3 py-2 text-xs font-bold text-white uppercase tracking-wide">{{ $chapter->title }}</h3>
                    <ul class="space-y-1">
                        @foreach($chapter->lessons as $lesson)
                            <li>
                                <a href="{{ route('courses.lessons.show', [$offering, $lesson]) }}" 
                                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition
                                          {{ $activeLesson && $activeLesson->id === $lesson->id 
                                              ? 'bg-raised border border-glow/50 text-white' 
                                              : 'text-slate-400 hover:bg-raised/50 hover:text-white border border-transparent' }}">
                                    <span class="font-mono text-[10px] {{ $activeLesson && $activeLesson->id === $lesson->id ? 'text-terminal' : 'text-slate-600' }}">
                                        {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                    <span class="truncate">{{ $lesson->title }}</span>
                                    @if($completedLessonIds->contains($lesson->id))
                                        <span class="ml-auto text-terminal">✓</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </aside>

    {{-- Main Content Area --}}
    <main class="flex-1 bg-surface border border-edge rounded-xl overflow-y-auto custom-scrollbar p-6 lg:p-10">
        @if($activeLesson)
            <header class="mb-8 pb-6 border-b border-edge">
                <div class="font-mono text-[11px] text-slate-500 mb-2">
                    {{ $activeLesson->chapter->title }} / LESSON {{ str_pad($activeLesson->position, 2, '0', STR_PAD_LEFT) }}
                </div>
                <h2 class="text-3xl font-extrabold text-white tracking-tight">{{ $activeLesson->title }}</h2>
            </header>

            <article class="prose prose-invert max-w-none space-y-6 text-slate-300 leading-relaxed">
                @foreach($activeLesson->blocks as $block)
                    @if($block->type === 'text')
                        <p class="text-base">{{ $block->data['content'] ?? '' }}</p>
                    @elseif($block->type === 'video')
                        <div class="rounded-xl overflow-hidden border border-edge bg-abyss aspect-video">
                            <iframe src="{{ $block->data['url'] ?? '' }}" class="w-full h-full" allowfullscreen></iframe>
                        </div>
                        @if(isset($block->data['title']))
                            <p class="text-xs font-mono text-slate-500 text-center -mt-4">{{ $block->data['title'] }}</p>
                        @endif
                    @elseif($block->type === 'image')
                        <img src="{{ $block->data['url'] ?? '' }}" alt="{{ $block->data['alt'] ?? '' }}" class="rounded-xl border border-edge">
                    @elseif($block->type === 'link')
                        <a href="{{ $block->data['url'] ?? '#' }}" target="_blank" class="inline-flex items-center gap-2 font-mono text-sm text-glow hover:underline">
                            → {{ $block->data['title'] ?? 'External Resource' }}
                        </a>
                    @endif
                @endforeach
            </article>

            {{-- Completion Action --}}
            <div class="mt-12 pt-6 border-t border-edge flex justify-end">
                @if($completedLessonIds->contains($activeLesson->id))
                    <button disabled class="px-6 py-3 rounded-lg bg-terminal/10 border border-terminal/30 text-terminal font-mono text-sm font-bold tracking-widest">
                        ✓ COMPLETED
                    </button>
                @else
                    <button id="complete-lesson-btn" 
                            onclick="completeLesson({{ $activeLesson->id }})"
                            class="press px-6 py-3 rounded-lg bg-brand hover:bg-brand/90 text-white font-mono text-sm font-bold tracking-widest transition shadow-[0_0_20px_rgba(255,46,77,0.3)]">
                        MARK AS COMPLETE
                    </button>
                @endif
            </div>
        @else
            <div class="flex items-center justify-center h-full text-slate-500 font-mono">
                Select a lesson from the sidebar to begin.
            </div>
        @endif
    </main>
</div>

<script>
async function completeLesson(lessonId) {
    const btn = document.getElementById('complete-lesson-btn');
    btn.disabled = true;
    btn.innerText = 'SYNCING...';
    btn.classList.add('opacity-50');

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
            btn.innerText = '✓ COMPLETED';
            btn.classList.remove('bg-brand', 'hover:bg-brand/90', 'shadow-[0_0_20px_rgba(255,46,77,0.3)]', 'opacity-50');
            btn.classList.add('bg-terminal/10', 'border', 'border-terminal/30', 'text-terminal');
            
            // HTB-style reaction flash
            document.querySelector('main').classList.add('flash-success');
            setTimeout(() => location.reload(), 800);
        }
    } catch (e) {
        btn.disabled = false;
        btn.innerText = 'MARK AS COMPLETE';
        btn.classList.remove('opacity-50');
    }
}
</script>
@endsection
