@extends('superadmin.layout.app')
@section('title', 'System Health — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">System Health</h1><p class="text-sm text-muted">Database · Queue · Cache · Storage · Application</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @php
            $healthCards = [
                ['label'=>'Database','value'=>$dbStatus,'color'=>$dbStatus==='healthy'?'emerald':'red','msg'=>$dbMessage],
                ['label'=>'Queue','value'=>$failedJobs.' failed','color'=>$failedJobs>0?'amber':'emerald','msg'=>$pendingJobs.' pending'],
                ['label'=>'Cache','value'=>$cacheStatus,'color'=>$cacheStatus==='healthy'?'emerald':'red'],
                ['label'=>'Storage','value'=>round($diskUsage, 1).'% used','color'=>$diskUsage>90?'red':($diskUsage>75?'amber':'emerald')],
            ];
        @endphp
        @foreach ($healthCards as $card)
            <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-{{ $card['color'] }}-500"></span>
                    <span class="text-xs font-bold uppercase tracking-widest text-muted">{{ $card['label'] }}</span>
                </div>
                <div class="text-lg font-extrabold text-text">{{ $card['value'] }}</div>
                @if (isset($card['msg']))<div class="text-xs text-muted mt-1">{{ $card['msg'] }}</div>@endif
            </div>
        @endforeach
    </div>

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <h2 class="text-base font-extrabold text-text mb-4">Application Environment</h2>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm">
            @foreach ($app as $key => $value)
                <dt class="text-muted uppercase text-xs tracking-wide">{{ $key }}</dt>
                <dd class="font-mono text-xs text-text">{{ $value }}</dd>
            @endforeach
        </dl>
    </div>

    @if ($recentFailedJobs->count())
        <div class="rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-border">
                <h2 class="text-base font-extrabold text-text">Recent Failed Jobs ({{ $failedJobs }} total)</h2>
                <a href="{{ route('superadmin.system.jobs') }}" class="text-xs font-bold text-primary hover:underline">View all →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase tracking-widest text-muted border-b border-border">
                        <th class="px-6 py-2">Failed At</th><th class="px-6 py-2">Job</th><th class="px-6 py-2">Queue</th><th class="px-6 py-2">Error</th>
                    </tr></thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($recentFailedJobs as $job)
                            <tr class="hover:bg-raised/50">
                                <td class="px-6 py-2 text-xs text-muted">{{ $job['failed_at'] }}</td>
                                <td class="px-6 py-2 text-xs text-text font-mono">{{ $job['job_name'] }}</td>
                                <td class="px-6 py-2 text-xs text-muted">{{ $job['queue'] ?? 'default' }}</td>
                                <td class="px-6 py-2 text-xs text-red-700 max-w-xs truncate">{{ $job['exception_message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection