@extends('superadmin.layout.app')
@section('title', 'Live Activity — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">Live Activity Feed</h1><p class="text-sm text-muted">Every administrative event across the platform</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-4xl space-y-4">
    <div class="rounded-2xl border border-border bg-surface shadow-sm divide-y divide-border">
        @forelse ($activities as $event)
            @php
                // Full literal class strings (Tailwind JIT safe).
                $sevChip = match ($event->severity) {
                    'high'   => 'bg-danger-bg text-danger',
                    'medium' => 'bg-warning-bg text-warning',
                    default  => 'bg-success-bg text-success',
                };
                $sevDot  = match ($event->severity) {
                    'high'   => 'bg-danger',
                    'medium' => 'bg-warning',
                    default  => 'bg-success',
                };
            @endphp
            <div class="flex items-start gap-4 px-6 py-4 transition hover:bg-raised/50">
                <div class="mt-1 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full {{ $sevChip }}">
                    <span class="h-2.5 w-2.5 rounded-full {{ $sevDot }}"></span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-0.5">
                        <p class="text-sm font-semibold text-text">{{ $event->description ?? $event->action }}</p>
                        <span class="font-mono text-[10px] text-muted">{{ $event->action }}</span>
                    </div>
                    <p class="text-xs text-muted">
                        {{ $event->actor?->name ?? 'System' }} @if ($event->actor_role)· {{ $event->actor_role }}@endif
                        · {{ $event->created_at?->format('M d, Y H:i:s') }}
                        @if ($event->organization)· {{ $event->organization->name }}@endif
                        @if ($event->targetUser)· → {{ $event->targetUser->name }}@endif
                        @if ($event->resource_type)· {{ class_basename($event->resource_type) }}#{{ $event->resource_id ?? '' }}@endif
                    </p>
                </div>
                <a href="{{ route('superadmin.audit.show', $event) }}" class="shrink-0 text-xs font-bold text-primary hover:underline">Detail →</a>
            </div>
        @empty
            <div class="px-6 py-12 text-center text-sm text-muted">No events recorded yet.</div>
        @endforelse
    </div>
</div>
@endsection