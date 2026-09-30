@extends('layouts.app')

@section('title', 'Institution administration — ACL')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">

    <header class="mb-8">
        <h1 class="text-2xl font-extrabold tracking-tight text-text">
            Institution Administration
        </h1>
        <p class="mt-1 text-sm text-muted">
            {{ auth()->user()->name }} — you administer {{ $organizationCount }}
            {{ \Illuminate\Support\Str::plural('institution', $organizationCount) }}.
        </p>
    </header>

    @if (session('success'))
        <div class="mb-6 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary" role="status">
            {{ session('success') }}
        </div>
    @endif

    <section class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-muted">Institutions</p>
            <p class="mt-1 text-3xl font-extrabold text-text">{{ $organizationCount }}</p>
        </div>

        <a href="{{ route('institution.coordinators') }}"
           class="press rounded-2xl border border-border bg-surface p-5 shadow-sm transition hover:border-primary/50 focus:outline-none focus:ring-2 focus:ring-ring">
            <p class="text-xs font-bold uppercase tracking-wider text-muted">Level coordinators</p>
            <p class="mt-1 text-sm font-semibold text-primary">Appoint and manage →</p>
        </a>
    </section>

    @if ($organizations->isEmpty())
        <div class="rounded-2xl border border-border bg-surface p-10 text-center shadow-sm">
            <h2 class="text-lg font-bold text-text">No institutions assigned</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-muted">
                Your account holds the institution administrator role but is not yet
                scoped to any university. A platform administrator assigns the
                institutions you look after.
            </p>
        </div>
    @else
        <section>
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-muted">
                Your institutions
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($organizations as $organization)
                    <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm transition hover:border-primary/50">
                        <h3 class="font-bold text-text">{{ $organization->name }}</h3>
                        <p class="mt-1 text-xs text-muted">
                            {{ $organization->state ?? $organization->location ?? 'Location not recorded' }}
                        </p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
