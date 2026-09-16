@extends('superadmin.layout.app')
@section('title', 'Audit Event — Superadmin')
@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('superadmin.audit') }}" class="text-muted hover:text-text transition"><x-ui.icon name="arrow-left" class="h-5 w-5" /></a>
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-text">Audit Event #{{ $log->id }}</h1>
            <p class="text-sm text-muted">{{ $log->action }} · {{ $log->created_at?->format('M d, Y H:i:s') }}</p>
        </div>
    </div>
@endsection
@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    {{-- Severity & result badges --}}
    @php
        $sevChip = match ($log->severity) {
            'high', 'critical' => 'bg-danger-bg text-danger',
            'medium' => 'bg-warning-bg text-warning',
            default  => 'bg-success-bg text-success',
        };
        $resChip = $log->result === 'success'
            ? 'bg-success-bg text-success'
            : 'bg-danger-bg text-danger';
    @endphp
    <div class="flex flex-wrap items-center gap-3">
        <span class="rounded-full {{ $sevChip }} px-3 py-1 text-sm font-bold">{{ ucfirst($log->severity) }}</span>
        <span class="rounded-full {{ $resChip }} px-3 py-1 text-sm font-bold">{{ ucfirst($log->result) }}</span>
    </div>

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <h3 class="text-base font-extrabold text-text mb-4">Event Context</h3>
        <dl class="space-y-3 text-sm">
            <div class="flex items-start gap-4"><dt class="w-36 text-muted shrink-0">Action</dt><dd class="font-mono text-text">{{ $log->action }}</dd></div>
            <div class="flex items-start gap-4"><dt class="w-36 text-muted shrink-0">Description</dt><dd class="text-text">{{ $log->description ?? '—' }}</dd></div>
            <div class="flex items-start gap-4"><dt class="w-36 text-muted shrink-0">Actor</dt><dd class="text-text">{{ $log->actor?->name ?? 'System' }} @if($log->actor) <span class="text-muted">({{ $log->actor?->email }})</span> @endif</dd></div>
            <div class="flex items-start gap-4"><dt class="w-36 text-muted shrink-0">Actor Role</dt><dd class="text-text">{{ $log->actor_role ?? '—' }}</dd></div>
            @if ($log->actingAs)
                <div class="flex items-start gap-4"><dt class="w-36 text-muted shrink-0">Acting As</dt><dd class="text-text">{{ $log->actingAs->name }}</dd></div>
            @endif
            <div class="flex items-start gap-4"><dt class="w-36 text-muted shrink-0">Target User</dt><dd class="text-text">{{ $log->targetUser?->name ?? '—' }}</dd></div>
            <div class="flex items-start gap-4"><dt class="w-36 text-muted shrink-0">Resource</dt><dd class="text-text">{{ class_basename($log->resource_type ?? '') }} #{{ $log->resource_id ?? '—' }}</dd></div>
            <div class="flex items-start gap-4"><dt class="w-36 text-muted shrink-0">Institution</dt><dd class="text-text">{{ $log->organization?->name ?? 'Platform' }}</dd></div>
            <div class="flex items-start gap-4"><dt class="w-36 text-muted shrink-0">IP Address</dt><dd class="font-mono text-xs text-text">{{ $log->ip_address ?? '—' }}</dd></div>
            <div class="flex items-start gap-4"><dt class="w-36 text-muted shrink-0">Request ID</dt><dd class="font-mono text-xs text-muted">{{ $log->request_id ?? '—' }}</dd></div>
        </dl>
    </div>

    @if ($log->old_values || $log->new_values)
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-base font-extrabold text-text mb-4">Change Details</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-xl bg-raised p-4">
                    <h4 class="text-xs font-bold text-muted uppercase tracking-wide mb-2">Previous Values</h4>
                    @if ($log->old_values)
                        <pre class="text-xs text-text font-mono whitespace-pre-wrap">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre>
                    @else
                        <p class="text-xs text-muted">None</p>
                    @endif
                </div>
                <div class="rounded-xl bg-raised p-4">
                    <h4 class="text-xs font-bold text-muted uppercase tracking-wide mb-2">New Values</h4>
                    @if ($log->new_values)
                        <pre class="text-xs text-text font-mono whitespace-pre-wrap">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                    @else
                        <p class="text-xs text-muted">None</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($log->metadata)
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-base font-extrabold text-text mb-4">Additional Metadata</h3>
            <pre class="rounded-xl bg-raised p-4 text-xs text-text font-mono whitespace-pre-wrap">{{ json_encode($log->metadata, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <h3 class="text-base font-extrabold text-text mb-2">Raw Event</h3>
        <dl class="space-y-1 text-xs text-muted font-mono">
            <dt>ID</dt><dd>{{ $log->id }}</dd>
            <dt>Created</dt><dd>{{ $log->created_at?->toIso8601String() }}</dd>
        </dl>
    </div>
</div>
@endsection