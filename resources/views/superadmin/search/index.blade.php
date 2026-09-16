@extends('superadmin.layout.app')
@section('title', 'Global Search — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">Global Search</h1><p class="text-sm text-muted">Users · Institutions · Students · Audit events</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <form action="{{ route('superadmin.search') }}" method="GET" class="flex gap-3">
            <div class="relative flex-1">
                <x-ui.icon name="magnifying-glass" class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-muted" />
                <input type="text" name="q" value="{{ $query }}" placeholder="Search by name, email, institution, course code, event…" autofocus
                       class="w-full rounded-xl border border-border bg-raised py-3 pl-11 pr-4 text-base text-text placeholder-muted focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
            </div>
            <button type="submit" class="rounded-xl bg-primary px-6 py-3 text-sm font-bold text-primary-fg transition hover:bg-primary/90">Search</button>
        </form>
        @if ($hasQuery)
            <p class="mt-3 text-sm text-muted">{{ $totalFound }} result(s) for “{{ $query }}”</p>
        @endif
    </div>

    @if ($hasQuery)
        @foreach (['users' => 'Users', 'institutions' => 'Institutions', 'students' => 'Students', 'audit' => 'Audit Events'] as $key => $label)
            @if (($results[$key] ?? collect())->count())
                <div class="rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
                    <div class="px-6 py-3 bg-raised/60 border-b border-border">
                        <h3 class="text-sm font-extrabold text-text">{{ $label }} <span class="ml-1 text-xs font-bold text-muted">{{ $results[$key]->count() }}</span></h3>
                    </div>
                    <div class="divide-y divide-border">
                        @foreach ($results[$key] as $result)
                            <a href="{{ $result['url'] }}" class="flex items-start gap-3 px-6 py-3 transition hover:bg-raised/50">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary text-xs font-bold">{{ strtoupper(substr($result['label'], 0, 2)) }}</div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-text truncate">{{ $result['label'] }}</p>
                                    <p class="text-xs text-muted">{{ $result['sub'] }}</p>
                                </div>
                                <span class="text-[10px] font-bold text-muted uppercase tracking-wide">{{ $result['type'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        @if ($totalFound === 0)
            <div class="rounded-2xl border border-border bg-surface p-10 text-center">
                <x-ui.icon name="magnifying-glass" class="h-10 w-10 text-muted mx-auto mb-3" />
                <p class="text-sm text-muted">No results found for “{{ $query }}”. Try a different term.</p>
            </div>
        @endif
    @else
        <div class="rounded-2xl border border-border bg-surface p-10 text-center">
            <x-ui.icon name="magnifying-glass" class="h-12 w-12 text-primary mx-auto mb-3" />
            <h3 class="text-base font-extrabold text-text mb-1">Search across the entire platform</h3>
            <p class="text-sm text-muted">Search for users, institutions, students, and audit events. Visibility respects superadmin authorization boundaries — never credentials or secrets.</p>
        </div>
    @endif
</div>
@endsection