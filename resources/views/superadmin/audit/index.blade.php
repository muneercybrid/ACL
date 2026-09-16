@extends('superadmin.layout.app')
@section('title', 'Audit Explorer — Superadmin')
@section('header')
    <div>
        <h1 class="text-xl font-extrabold tracking-tight text-text">Audit Explorer</h1>
        <p class="text-sm text-muted">Every administrative event · Actor · Target · Before/After</p>
    </div>
@endsection
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
        <form action="{{ route('superadmin.audit') }}" method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-bold text-muted mb-1">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Description or action…" class="rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none w-64">
            </div>
            <div>
                <label class="block text-xs font-bold text-muted mb-1">Action</label>
                <select name="action" class="rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none w-48">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" {{ ($filters['action'] ?? '')===$action ? 'selected' : '' }}>{{ ucfirst($action) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-muted mb-1">Severity</label>
                <select name="severity" class="rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none w-36">
                    <option value="">All</option>
                    @foreach (['info','low','medium','high','critical'] as $s)
                        <option value="{{ $s }}" {{ ($filters['severity'] ?? '')===$s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-primary-fg transition hover:bg-primary/90">Filter</button>
        </form>
    </div>

    <div class="rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-border bg-raised text-left text-xs uppercase tracking-widest text-muted">
                    <th class="px-6 py-3">Time</th><th class="px-6 py-3">Action</th><th class="px-6 py-3">Actor</th><th class="px-6 py-3">Target</th><th class="px-6 py-3">Institution</th><th class="px-6 py-3">Severity</th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                    @forelse ($logs as $log)
                        @php
                            $sevChip = match ($log->severity) {
                                'high'   => 'bg-danger-bg text-danger',
                                'medium' => 'bg-warning-bg text-warning',
                                'critical' => 'bg-danger-bg text-danger',
                                default  => 'bg-success-bg text-success',
                            };
                        @endphp
                        <tr class="hover:bg-raised/50 transition">
                            <td class="px-6 py-3 text-xs text-muted">{{ $log->created_at?->format('M d H:i') }}</td>
                            <td class="px-6 py-3"><a href="{{ route('superadmin.audit.show', $log) }}" class="font-medium text-text hover:text-primary transition">{{ $log->action }}</a></td>
                            <td class="px-6 py-3 text-xs text-muted">{{ $log->actor?->name ?? 'System' }}</td>
                            <td class="px-6 py-3 text-xs text-muted">{{ $log->targetUser?->name ?? ($log->resource_type ?? '—') }}</td>
                            <td class="px-6 py-3 text-xs text-muted">{{ $log->organization?->name ?? 'Platform' }}</td>
                            <td class="px-6 py-3"><span class="rounded-full {{ $sevChip }} px-2 py-0.5 text-xs font-bold">{{ ucfirst($log->severity) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-muted">No audit events match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
</div>
@endsection