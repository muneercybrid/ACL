@extends('superadmin.layout.app')
@section('title', 'Authentication & Security — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">Authentication & Security</h1><p class="text-sm text-muted">Account activity · Suspensions · Permission changes</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm"><div class="text-xl font-extrabold text-text">{{ $summary['suspended_accounts'] }}</div><div class="text-xs text-muted">Suspended accounts</div></div>
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm"><div class="text-xl font-extrabold text-text">{{ $summary['high_severity_events_30d'] }}</div><div class="text-xs text-muted">High-severity events (30d)</div></div>
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm"><div class="text-xl font-extrabold text-text">{{ $summary['administrative_actions_30d'] }}</div><div class="text-xs text-muted">Admin actions (30d)</div></div>
    </div>

    <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
        <form action="{{ route('superadmin.security') }}" method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="min-w-[160px]">
                <label class="block text-xs font-bold text-muted mb-1">Event Type</label>
                <select name="event" class="w-full rounded-xl border border-border bg-raised px-3 py-2 text-sm text-text focus:border-primary focus:outline-none">
                    <option value="">All</option>
                    @foreach ($eventTypes as $val => $label)
                        <option value="{{ $val }}" {{ ($filters['event'] ?? '')===$val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[160px]">
                <label class="block text-xs font-bold text-muted mb-1">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full rounded-xl border border-border bg-raised px-3 py-2 text-sm text-text focus:border-primary focus:outline-none">
            </div>
            <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-primary-fg transition hover:bg-primary/90">Filter</button>
        </form>
    </div>

    <div class="rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-border bg-raised text-left text-xs uppercase tracking-widest text-muted">
                    <th class="px-6 py-3">Time</th><th class="px-6 py-3">Event</th><th class="px-6 py-3">Actor</th><th class="px-6 py-3">Target</th><th class="px-6 py-3">Severity</th><th class="px-6 py-3">Description</th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                    @forelse ($events as $event)
                        @php
                            $sevChip = match ($event->severity) {
                                'high'   => 'bg-danger-bg text-danger',
                                'medium' => 'bg-warning-bg text-warning',
                                default  => 'bg-success-bg text-success',
                            };
                        @endphp
                        <tr class="hover:bg-raised/50 transition">
                            <td class="px-6 py-3 text-xs text-muted whitespace-nowrap">{{ $event->created_at?->format('M d H:i') }}</td>
                            <td class="px-6 py-3 font-mono text-xs text-text">{{ $event->action }}</td>
                            <td class="px-6 py-3 text-sm text-text">{{ $event->actor?->name ?? 'System' }}</td>
                            <td class="px-6 py-3 text-sm text-muted">{{ $event->targetUser?->name ?? '—' }}</td>
                            <td class="px-6 py-3"><span class="rounded-full {{ $sevChip }} px-2 py-0.5 text-[10px] font-bold">{{ ucfirst($event->severity) }}</span></td>
                            <td class="px-6 py-3 text-xs text-muted max-w-xs truncate">{{ $event->description ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-muted">No security events match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3">{{ $events->links() }}</div>
    </div>
</div>
@endsection