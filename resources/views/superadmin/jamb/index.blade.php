@extends('superadmin.layout.app')
@section('title', 'JAMB Monitoring — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">JAMB Verification Monitoring</h1><p class="text-sm text-muted">Success rate · Failure types · Provider status · Manual queue</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-xl font-extrabold text-emerald-700">{{ $summary['verified'] }}</div>
            <div class="text-xs text-muted">Verified</div>
        </div>
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-xl font-extrabold text-amber-700">{{ $summary['manual_required'] }}</div>
            <div class="text-xs text-muted">Manual verification required</div>
        </div>
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-xl font-extrabold text-red-700">{{ $summary['not_found'] + $summary['invalid_input'] }}</div>
            <div class="text-xs text-muted">Invalid / not found (student input)</div>
        </div>
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
            <div class="text-xl font-extrabold text-orange-600">{{ $summary['provider_failures'] }}</div>
            <div class="text-xs text-muted">Provider/system failures (not student fault)</div>
        </div>
    </div>

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-extrabold text-text">Success Rate</h2>
            <span class="text-xl font-extrabold text-primary">{{ $successRate }}%</span>
        </div>
        <div class="h-3 w-full rounded-full bg-raised overflow-hidden">
            <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ $successRate }}%"></div>
        </div>
        <p class="mt-2 text-xs text-muted">{{ $summary['total_requests'] }} total verification requests</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-sm font-extrabold text-text mb-3">Status Breakdown</h3>
            <div class="space-y-2">
                @foreach ($statuses as $status)
                    <div class="flex items-center justify-between py-1.5 border-b border-border last:border-0">
                        <span class="text-sm text-text capitalize">{{ ucfirst(str_replace('_', ' ', $status->status ?? 'unknown')) }}</span>
                        <span class="text-sm font-bold text-text">{{ number_format($status->count ?? 0) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <h3 class="text-sm font-extrabold text-text mb-3">Provider Status</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between rounded-xl bg-raised px-4 py-3">
                    <span class="text-sm font-medium text-text">JAMB Web Forms Provider</span>
                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">Operational</span>
                </div>
                <p class="text-xs text-muted">Provider outages are reported separately from invalid student numbers per the platform verification contract.</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-border">
            <h2 class="text-base font-extrabold text-text">Recent Verification Requests</h2>
            <a href="{{ route('superadmin.registrations') }}" class="text-xs font-bold text-primary hover:underline">All registrations →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase tracking-widest text-muted border-b border-border">
                    <th class="px-6 py-2">Time</th><th class="px-6 py-2">JAMB Number</th><th class="px-6 py-2">Name</th><th class="px-6 py-2">Institution</th><th class="px-6 py-2">Programme</th><th class="px-6 py-2">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                    @forelse ($requests as $req)
                        <tr class="hover:bg-raised/50 transition">
                            <td class="px-6 py-2 text-xs text-muted whitespace-nowrap">{{ $req->created_at?->format('M d H:i') }}</td>
                            <td class="px-6 py-2 font-mono text-xs text-text">{{ $req->jamb_registration_number ?? '—' }}</td>
                            <td class="px-6 py-2 text-sm text-text">{{ $req->verified_name ?? '—' }}</td>
                            <td class="px-6 py-2 text-xs text-muted">{{ $req->verified_institution ?? '—' }}</td>
                            <td class="px-6 py-2 text-xs text-muted">{{ $req->verified_programme ?? '—' }}</td>
                            <td class="px-6 py-2">
                                @php
                                    $status = $req->status ?? 'unknown';
                                    $statusChip = match (true) {
                                        $status === 'verified' => 'bg-success-bg text-success',
                                        in_array($status, ['provider_timeout','provider_unavailable','temporary_failure']) => 'bg-warning-bg text-warning',
                                        $status === 'manual_verification_required' => 'bg-warning-bg text-warning',
                                        default => 'bg-danger-bg text-danger',
                                    };
                                @endphp
                                <span class="rounded-full {{ $statusChip }} px-2 py-0.5 text-[10px] font-bold">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-sm text-muted text-center">No verification requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3">{{ $requests->links() }}</div>
    </div>
</div>
@endsection