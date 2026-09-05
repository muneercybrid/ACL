@extends('layouts.app')

@section('title', 'Dashboard — ACL')

@section('header')
<div>
    <h1 class="text-lg font-bold text-white">Dashboard</h1>
    <p class="text-[11px] font-mono text-slate-500">session: active • {{ now()->format('Y-m-d H:i') }} UTC</p>
</div>
<span class="font-mono text-[11px] text-terminal border border-terminal/30 bg-terminal/10 rounded px-2 py-1">● ONLINE</span>
@endsection

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <div class="bg-surface border border-edge rounded-xl p-5">
        <p class="font-mono text-[11px] uppercase tracking-widest text-slate-500 mb-1">enrolled_courses</p>
        <p class="text-3xl font-extrabold text-white">{{ $enrollments->count() }}</p>
    </div>
    <div class="bg-surface border border-edge rounded-xl p-5">
        <p class="font-mono text-[11px] uppercase tracking-widest text-slate-500 mb-1">xp</p>
        <p class="text-3xl font-extrabold text-terminal">0</p>
        <p class="text-[10px] font-mono text-slate-600 mt-1">gamification // soon</p>
    </div>
    <div class="bg-surface border border-edge rounded-xl p-5">
        <p class="font-mono text-[11px] uppercase tracking-widest text-slate-500 mb-1">streak</p>
        <p class="text-3xl font-extrabold text-glow">0d</p>
        <p class="text-[10px] font-mono text-slate-600 mt-1">gamification // soon</p>
    </div>
</div>

<div class="bg-abyss border border-edge rounded-xl p-4 mb-8 font-mono text-xs leading-6">
    <p class="text-slate-500">$ acl status --student "{{ auth()->user()->name }}"</p>
    <p class="text-terminal">[✓] membership verified • {{ $enrollments->first()?->courseOffering?->semester?->name ?? 'no active semester' }}</p>
    <p class="text-terminal">[✓] institutional entitlements synced ({{ $enrollments->count() }})</p>
    <p class="text-slate-500">$ <span class="cursor-blink">▊</span></p>
</div>

<h2 class="font-mono text-sm text-slate-400 mb-4">// CURRENT SEMESTER COURSES</h2>

<div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
@forelse ($enrollments as $enrollment)
    <article class="group bg-surface border border-edge rounded-xl p-5 transition hover:border-glow/60 hover:shadow-[0_0_30px_rgba(34,211,238,0.08)]">
        <div class="flex items-center justify-between mb-3">
            <span class="font-mono text-terminal text-sm">{{ $enrollment->courseOffering->course->code }}</span>
            <span class="font-mono text-[9px] uppercase px-2 py-0.5 rounded border border-terminal/30 bg-terminal/10 text-terminal">
                {{ str_replace('_', ' ', $enrollment->source) }}
            </span>
        </div>
        <h3 class="font-semibold text-white mb-1">{{ $enrollment->courseOffering->course->title }}</h3>
        <p class="text-xs text-slate-500 mb-4">
            {{ $enrollment->courseOffering->course->credit_units }} CU • {{ $enrollment->courseOffering->semester->name }}
        </p>
        <div class="h-1.5 rounded bg-abyss overflow-hidden mb-4">
            <div class="h-full bg-gradient-to-r from-glow to-terminal transition-all duration-500" style="width: 0%"></div>
        </div>
        <a href="{{ route('courses.show', $enrollment->courseOffering) }}"
           class="press block w-full text-center rounded-lg border border-glow/40 py-2 font-mono text-xs text-glow hover:bg-glow/10 transition">
            ENTER COURSE →
        </a>
    </article>
@empty
    <p class="font-mono text-sm text-slate-500">No enrollments yet. Run: php artisan acl:sync-enrollments</p>
@endforelse
</div>
@endsection
