@extends('superadmin.layout.app')
@section('title', 'Failed Jobs — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">Failed Jobs</h1><p class="text-sm text-muted">Background job failures · queue status</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-border bg-raised text-left text-xs uppercase tracking-widest text-muted">
                    <th class="px-6 py-3">ID</th><th class="px-6 py-3">Failed At</th><th class="px-6 py-3">Connection</th><th class="px-6 py-3">Queue</th><th class="px-6 py-3">Payload</th><th class="px-6 py-3">Exception (first line)</th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                    @forelse ($failedJobs as $job)
                        @php $payload = json_decode($job->payload ?? '{}', true); @endphp
                        <tr class="hover:bg-raised/50 transition">
                            <td class="px-6 py-3 text-xs text-muted">{{ $job->id }}</td>
                            <td class="px-6 py-3 text-xs text-muted whitespace-nowrap">{{ $job->failed_at }}</td>
                            <td class="px-6 py-3 text-xs text-muted">{{ $job->connection }}</td>
                            <td class="px-6 py-3 text-xs text-muted">{{ $job->queue ?? 'default' }}</td>
                            <td class="px-6 py-3 text-xs text-muted max-w-[160px] truncate">{{ $payload['displayName'] ?? 'Unknown job' }}</td>
                            <td class="px-6 py-3 text-xs text-red-700 max-w-md truncate">{{ explode("\n", $job->exception)[0] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-muted">No failed jobs. The queue is healthy.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $failedJobs->links() }}
    </div>
</div>
@endsection