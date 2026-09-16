@extends('layouts.public')

@section('title', 'Catalogue')
@section('content')
<div class="mx-auto max-w-6xl px-4 py-8">
    <h1 class="text-3xl font-extrabold text-text">Professional Catalogue</h1>
    <p class="mt-1 text-sm text-muted">External, industry and certification-oriented courses. These are separate from your university curriculum.</p>

    @if ($student && $recommended->isNotEmpty())
        <div class="mt-6">
            <h2 class="text-sm font-semibold uppercase tracking-widest text-muted">Recommended for your discipline</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($recommended as $c)
                    <x-ui.card hover class="p-4">
                        <p class="font-mono text-xs text-primary">{{ $c->code }}</p>
                        <p class="mt-1 font-semibold text-text">{{ $c->title }}</p>
                        <p class="mt-1 text-xs text-muted">{{ ucfirst($c->difficulty ?? 'beginner') }} · External</p>
                    </x-ui.card>
                @endforeach
            </div>
        </div>
    @endif

    <h2 class="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Browse by faculty / discipline</h2>
    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($disciplines as $d)
            <a href="{{ route('catalogue.discipline', $d) }}" class="block rounded-2xl border border-border bg-surface p-4 transition hover:border-primary/40 hover:shadow-sm">
                <p class="font-semibold text-text">{{ $d->name }}</p>
                <p class="mt-1 text-xs text-muted">{{ $d->external_count }} external courses</p>
            </a>
        @endforeach
    </div>
</div>
@endsection
