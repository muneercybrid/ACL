@extends('layouts.auth')

@section('title', 'Organization sign in — ACL')

@section('content')
<div class="w-full max-w-2xl">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-extrabold tracking-tight text-text">Organization sign in</h1>
        <p class="mt-2 text-sm text-muted">
            Select your institution to continue to your staff sign in.
        </p>
    </div>

    <form method="GET" action="{{ route('organizations.login') }}" class="mb-6">
        <label class="block">
            <span class="sr-only">Search organizations</span>
            <input type="search" name="q" value="{{ $search }}"
                   placeholder="Search by name or state"
                   class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text placeholder-muted transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
        </label>
    </form>

    @if ($organizations->isEmpty())
        <div class="rounded-2xl border border-border bg-surface p-10 text-center shadow-sm">
            <p class="text-sm font-medium text-text">No organizations matched.</p>
            <p class="mt-1 text-xs text-muted">
                Check the spelling, or search by state.
            </p>
        </div>
    @else
        <ul class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
            @foreach ($organizations as $organization)
                <li>
                    <a href="{{ route('organizations.login.show', $organization) }}"
                       class="flex items-center gap-4 px-5 py-4 transition hover:bg-raised focus:outline-none focus:bg-raised">
                        <span aria-hidden="true"
                              class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-border bg-raised text-sm font-bold text-primary">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($organization->short_name ?: $organization->name, 0, 2)) }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold text-text">{{ $organization->name }}</span>
                            @if ($organization->state)
                                <span class="block text-xs text-muted">{{ $organization->state }}</span>
                            @endif
                        </span>

                        <span aria-hidden="true" class="text-muted">→</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="mt-6 text-center text-xs text-muted">
        Staff accounts are issued by ACL. If you cannot sign in, contact your ACL administrator.
    </p>

    <p class="mt-4 text-center text-xs text-muted">
        Are you a student?
        <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">Student sign in</a>
    </p>
</div>
@endsection