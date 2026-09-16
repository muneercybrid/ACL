@extends('superadmin.layout.app')
@section('title', 'Institution Onboarding — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">Institution Onboarding</h1><p class="text-sm text-muted">Setup progress for every institution</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @php
            $statusCounts = $onboardings->getCollection()->groupBy('status');
            $buckets = [
                ['label'=>'Pending / Invited','count'=>($statusCounts->get('pending') ?? collect())->count() + ($statusCounts->get('invited') ?? collect())->count(),'color'=>'amber'],
                ['label'=>'In Progress','count'=>($statusCounts->get('started') ?? collect())->count() + ($statusCounts->get('partially_completed') ?? collect())->count(),'color'=>'sky'],
                ['label'=>'Awaiting Review','count'=>($statusCounts->get('awaiting_review') ?? collect())->count(),'color'=>'orange'],
                ['label'=>'Completed','count'=>($statusCounts->get('completed') ?? collect())->count(),'color'=>'emerald'],
            ];
        @endphp
        @foreach ($buckets as $b)
            <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                <div class="text-xl font-extrabold text-{{ $b['color'] }}-700">{{ $b['count'] }}</div>
                <div class="text-xs text-muted">{{ $b['label'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-border bg-raised text-left text-xs uppercase tracking-widest text-muted">
                    <th class="px-6 py-3">Institution</th><th class="px-6 py-3">Administrator</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 w-64">Progress</th><th class="px-6 py-3">Last Updated</th><th class="px-6 py-3"></th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                    @forelse ($onboardings as $onboarding)
                        <tr class="hover:bg-raised/50 transition">
                            <td class="px-6 py-4">
                                <a href="{{ route('superadmin.onboarding.show', $onboarding->organization) }}" class="font-bold text-text hover:text-primary transition">{{ $onboarding->organization?->name ?? 'Deleted org' }}</a>
                            </td>
                            <td class="px-6 py-4 text-xs text-muted">{{ $onboarding->administrator?->name ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <span class="rounded-full bg-{{ $onboarding->isCompleted() ? 'emerald-50 text-emerald-700' : ($onboarding->status === 'suspended' ? 'red-50 text-red-700' : 'amber-50 text-amber-700') }} px-2.5 py-0.5 text-xs font-bold capitalize">{{ str_replace('_', ' ', $onboarding->status) }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 flex-1 rounded-full bg-raised overflow-hidden">
                                        <div class="h-full rounded-full bg-{{ $onboarding->isCompleted() ? 'emerald-500' : 'primary' }}" style="width:{{ $onboarding->getCompletionPercentage() }}%"></div>
                                    </div>
                                    <span class="text-xs font-bold text-muted">{{ $onboarding->getCompletionPercentage() }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs text-muted whitespace-nowrap">{{ $onboarding->updated_at?->format('M d, Y H:i') }}</td>
                            <td class="px-6 py-4 text-right"><a href="{{ route('superadmin.onboarding.show', $onboarding->organization) }}" class="text-xs font-bold text-primary hover:underline">Inspect →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-muted">No onboarding records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $onboardings->links() }}
    </div>
</div>
@endsection