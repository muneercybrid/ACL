@extends('layouts.app')

@section('title', 'Level coordinators — ACL')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">

    <header class="mb-8">
        <h1 class="text-2xl font-extrabold tracking-tight text-text">Level Coordinators</h1>
        <p class="mt-1 text-sm text-muted">
            Appoint a coordinator for each level of each programme your institution offers.
        </p>
    </header>

    @if (session('activation'))
        <div class="mb-8 rounded-2xl border-2 border-primary bg-primary/5 p-5">
            <h2 class="text-base font-extrabold text-text">Coordinator appointed</h2>
            <p class="mt-1 text-sm text-muted">
                Give this link to <strong class="font-semibold text-text">{{ session('activation.email') }}</strong>.
                It is shown once and cannot be retrieved later.
            </p>
            <p class="mt-3 break-all rounded-lg border border-border bg-bg px-3 py-2 font-mono text-xs text-text">
                {{ session('activation.url') }}
            </p>
            <p class="mt-2 text-xs text-muted">
                The account cannot be signed into until this link is used. It expires in 14 days.
            </p>
        </div>
    @endif

    @if ($organizations->isEmpty())
        <div class="rounded-2xl border border-border bg-surface p-10 text-center shadow-sm">
            <h2 class="text-lg font-bold text-text">No institution assigned</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-muted">
                Your account is not yet scoped to a university, so there is nothing to appoint for.
            </p>
        </div>
    @else
        <div class="grid gap-8 lg:grid-cols-[1fr_1.2fr]">

            {{-- Appoint --}}
            <section>
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-muted">Appoint a coordinator</h2>

                <form method="POST" action="{{ route('institution.coordinators.store') }}"
                      class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                    @csrf

                    @if ($organizations->count() > 1)
                        <label class="mb-4 block">
                            <span class="text-sm font-medium text-text">Institution</span>
                            <select name="organization_id" class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
                                @foreach ($organizations as $org)
                                    <option value="{{ $org->id }}" @selected(old('organization_id', $organization?->id) === $org->id)>
                                        {{ $org->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    @else
                        <input type="hidden" name="organization_id" value="{{ $organization->id }}">
                        <p class="mb-4 text-sm font-medium text-text">{{ $organization->name }}</p>
                    @endif

                    <label class="mb-4 block">
                        <span class="text-sm font-medium text-text">Programme</span>
                        <select name="programme_id" required class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
                            <option value="">Select a programme…</option>
                            @foreach ($programmes as $programme)
                                <option value="{{ $programme->id }}" @selected(old('programme_id') == $programme->id)>
                                    {{ $programme->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="mb-4 block">
                        <span class="text-sm font-medium text-text">Level</span>
                        <select name="level" required class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
                            <option value="">Select a level…</option>
                            @foreach ($levels as $level)
                                <option value="{{ $level }}" @selected(old('level') == $level)>Level {{ $level }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="mb-5 block">
                        <span class="text-sm font-medium text-text">Coordinator's name</span>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
                    </label>

                    @error('organization_id')<p class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
                    @error('programme_id')<p class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
                    @error('level')<p class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
                    @error('name')<p class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror

                    <button type="submit"
                            class="press w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
                        Appoint and generate link
                    </button>

                    <p class="mt-3 text-xs text-muted">
                        ACL creates a generated address for the role. The coordinator replaces it
                        with their own the first time they use the link.
                    </p>
                </form>
            </section>

            {{-- Existing --}}
            <section>
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-muted">
                    {{ $organization?->name }}
                </h2>

                @if ($appointments->isEmpty())
                    <div class="rounded-2xl border border-border bg-surface p-8 text-center shadow-sm">
                        <p class="text-sm text-muted">No coordinators appointed yet.</p>
                    </div>
                @else
                    <div class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-border bg-bg/60 text-xs uppercase tracking-wider text-muted">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">Programme</th>
                                    <th class="px-4 py-3 font-semibold">Level</th>
                                    <th class="px-4 py-3 font-semibold">Coordinator</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($appointments as $appointment)
                                    <tr>
                                        <td class="px-4 py-3 text-text">{{ $appointment->programme?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-muted">{{ $appointment->level }}</td>
                                        <td class="px-4 py-3">
                                            <span class="block text-text">{{ $appointment->user?->name ?? '—' }}</span>
                                            @if ($appointment->user && ! $appointment->user->must_complete_onboarding)
                                                <span class="text-xs text-muted">{{ $appointment->user->email }}</span>
                                            @else
                                                <span class="text-xs text-amber-700">Awaiting activation</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    @endif
</div>
@endsection