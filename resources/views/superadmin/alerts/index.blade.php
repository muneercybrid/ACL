@extends('superadmin.layout.app')
@section('title', 'System Alerts — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">System Alerts</h1><p class="text-sm text-muted">Active · Acknowledged · Resolved</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm"><div class="text-xl font-extrabold text-amber-700">{{ $counts['active'] }}</div><div class="text-xs text-muted">Active</div></div>
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm"><div class="text-xl font-extrabold text-sky-700">{{ $counts['acknowledged'] }}</div><div class="text-xs text-muted">Acknowledged</div></div>
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm"><div class="text-xl font-extrabold text-emerald-700">{{ $counts['resolved'] }}</div><div class="text-xs text-muted">Resolved</div></div>
    </div>

    <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
        <form action="{{ route('superadmin.alerts') }}" method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-bold text-muted mb-1">Status</label>
                <select name="status" class="rounded-xl border border-border bg-raised px-4 py-2 text-sm text-text focus:border-primary focus:outline-none">
                    <option value="">Active & acknowledged</option>
                    <option value="active" {{ ($filters['status'] ?? '')==='active' ? 'selected' : '' }}>Active</option>
                    <option value="acknowledged" {{ ($filters['status'] ?? '')==='acknowledged' ? 'selected' : '' }}>Acknowledged</option>
                    <option value="resolved" {{ ($filters['status'] ?? '')==='resolved' ? 'selected' : '' }}>Resolved</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-muted mb-1">Severity</label>
                <select name="severity" class="rounded-xl border border-border bg-raised px-4 py-2 text-sm text-text focus:border-primary focus:outline-none">
                    <option value="">All</option>
                    @foreach (['info','warning','critical'] as $s)
                        <option value="{{ $s }}" {{ ($filters['severity'] ?? '')===$s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-primary-fg transition hover:bg-primary/90">Filter</button>
        </form>
    </div>

    <div class="space-y-3">
        @forelse ($alerts as $alert)
            <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            <span class="rounded-full bg-{{ $alert->severity==='critical'?'red-100 text-red-700':($alert->severity==='warning'?'amber-100 text-amber-700':'sky-100 text-sky-700') }} px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide">{{ $alert->severity }}</span>
                            <span class="rounded-full bg-raised px-2 py-0.5 text-[10px] font-bold text-muted uppercase">{{ $alert->status }}</span>
                            @if ($alert->source) <span class="text-xs text-muted">Source: {{ $alert->source }}</span> @endif
                            <span class="text-xs text-muted">{{ $alert->created_at->format('M d, Y H:i') }}</span>
                        </div>
                        <h3 class="text-base font-bold text-text mb-1">{{ $alert->title }}</h3>
                        @if ($alert->message) <p class="text-sm text-muted mb-2">{{ $alert->message }}</p> @endif
                        @if ($alert->organization) <p class="text-xs text-muted">Institution: {{ $alert->organization->name }}</p> @endif
                    </div>
                    <div class="flex flex-col gap-2 shrink-0">
                        @if ($alert->status === 'active')
                            <form action="{{ route('superadmin.alerts.acknowledge', $alert) }}" method="POST">
                                @csrf
                                <button type="submit" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-primary-fg transition hover:bg-primary/90">Acknowledge</button>
                            </form>
                        @endif
                        @if (in_array($alert->status, ['active', 'acknowledged']))
                            <form action="{{ route('superadmin.alerts.resolve', $alert) }}" method="POST">
                                @csrf
                                <button type="submit" class="rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-muted transition hover:bg-raised">Resolve</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-surface p-10 text-center">
                <p class="text-sm text-muted">No alerts match your filters.</p>
            </div>
        @endforelse
        {{ $alerts->links() }}
    </div>
</div>
@endsection