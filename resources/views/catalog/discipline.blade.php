@extends('layouts.app')
@section('title', $discipline->name)
@section('content')
<div class="mx-auto max-w-6xl px-4 py-8">
    <a href="{{ route('catalogue') }}" class="text-sm text-primary hover:underline">← Catalogue</a>
    <h1 class="mt-2 text-3xl font-extrabold text-text">{{ $discipline->name }}</h1>

    <form method="GET" class="mt-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search courses…" class="rounded-xl border border-border bg-surface px-3 py-2 text-sm focus:border-primary focus:outline-none">
        <select name="difficulty" class="rounded-xl border border-border bg-surface px-3 py-2 text-sm focus:border-primary focus:outline-none">
            <option value="">All levels</option>
            @foreach (['beginner','intermediate','advanced','expert'] as $d)
                <option value="{{ $d }}" @selected(request('difficulty') === $d)>{{ ucfirst($d) }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-fg transition hover:bg-primary/90">Filter</button>
    </form>

    <h2 class="mt-6 text-sm font-semibold uppercase tracking-widest text-muted">Departments / programmes</h2>
    <div class="mt-2 flex flex-wrap gap-2">
        @foreach ($departments as $p)
            <span class="rounded-full border border-border px-3 py-1 text-xs text-muted">{{ $p->name }}</span>
        @endforeach
    </div>

    <h2 class="mt-6 text-sm font-semibold uppercase tracking-widest text-muted">External courses</h2>
    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($courses as $c)
            <x-ui.card hover class="p-4">
                <p class="font-mono text-xs text-primary">{{ $c->code }}</p>
                <p class="mt-1 font-semibold text-text">{{ $c->title }}</p>
                <p class="mt-1 text-xs text-muted">{{ ucfirst($c->difficulty ?? 'beginner') }} · External</p>
            </x-ui.card>
        @empty
            <p class="text-sm text-muted">No external courses for this discipline yet.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $courses->links() }}</div>
</div>
@endsection
