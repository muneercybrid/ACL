@extends('superadmin.layout.app')
@section('title', 'AI Activity — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">AI Activity</h1><p class="text-sm text-muted">ACLi usage · Providers · Costs</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    {{-- Summary Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
        @php
            $aiCards = [
                ['label'=>'Requests','value'=>$summary['total_requests'],'color'=>'primary'],
                ['label'=>'Successful','value'=>$summary['successful'],'color'=>'emerald'],
                ['label'=>'Failed','value'=>$summary['failed'],'color'=>'red'],
                ['label'=>'Total Tokens','value'=>number_format($summary['total_tokens']),'color'=>'sky'],
                ['label'=>'Avg Latency','value'=>$summary['avg_latency_ms'].'ms','color'=>'amber'],
                ['label'=>'Est. Cost','$'.$summary['estimated_cost'],'color'=>'amber'],
            ];
        @endphp
        @foreach ($aiCards as $card)
            <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                <div class="text-xl font-extrabold text-text">{{ $card['value'] }}</div>
                <div class="text-xs text-muted mt-1">{{ $card['label'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Requests table --}}
        <div class="lg:col-span-2 rounded-2xl border border-border bg-surface shadow-sm">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-base font-extrabold text-text">Recent Requests</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase tracking-widest text-muted border-b border-border">
                        <th class="px-6 py-2">Time</th><th class="px-6 py-2">User</th><th class="px-6 py-2">Provider</th><th class="px-6 py-2">Model</th><th class="px-6 py-2">Tokens</th><th class="px-6 py-2">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($requests as $req)
                            <tr class="hover:bg-raised/50 transition">
                                <td class="px-6 py-2 text-xs text-muted">{{ $req->created_at?->format('M d H:i') }}</td>
                                <td class="px-6 py-2 text-sm text-text">{{ $req->user?->name ?? '—' }}</td>
                                <td class="px-6 py-2 text-xs text-muted">{{ $req->provider ?? '—' }}</td>
                                <td class="px-6 py-2 text-xs text-muted font-mono">{{ $req->model ?? 'auto' }}</td>
                                <td class="px-6 py-2 text-xs text-muted">{{ number_format($req->total_tokens ?? 0) }}</td>
                                <td class="px-6 py-2">
                                    <span class="rounded-full bg-{{ ($req->status ?? '') === 'success' ? 'emerald-50 text-emerald-700' : 'red-50 text-red-700' }} px-2 py-0.5 text-[10px] font-bold">{{ $req->status ?? 'unknown' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-8 text-sm text-muted text-center">No ACLi requests recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-3">{{ $requests->links() }}</div>
        </div>

        {{-- Provider & model breakdown --}}
        <div class="space-y-4">
            <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                <h3 class="text-sm font-extrabold text-text mb-3">Providers</h3>
                @forelse ($providers as $p)
                    <div class="flex items-center justify-between py-2 border-b border-border last:border-0">
                        <span class="text-sm text-text">{{ $p->provider ?? 'unknown' }}</span>
                        <span class="text-sm font-bold text-text">{{ number_format($p->count) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-muted">No provider data.</p>
                @endforelse
            </div>
            <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                <h3 class="text-sm font-extrabold text-text mb-3">Models Used</h3>
                @forelse ($models as $m)
                    <div class="flex items-center justify-between py-2 border-b border-border last:border-0">
                        <span class="text-xs font-mono text-text">{{ $m->model }}</span>
                        <span class="text-sm font-bold text-text">{{ number_format($m->count) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-muted">No model data.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection