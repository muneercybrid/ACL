@extends('superadmin.layout.app')

@section('title', 'Superadmin Command Center — ACL')

@section('header')
    <div>
        <h1 class="text-xl font-extrabold tracking-tight text-text">Command Center</h1>
        <p class="text-sm text-muted">Platform overview · Real-time activity · System health</p>
    </div>
@endsection

@section('content')
<div class="mx-auto max-w-7xl space-y-8">

    {{-- Platform Overview Stats --}}
    <section>
        <div class="flex items-center gap-2 mb-4">
            <h2 class="text-lg font-extrabold tracking-tight text-text">Platform Overview</h2>
            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary">Live</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @php
                $cards = [
                    ['label'=>'Institutions','value'=>$platformStats['institutions']['total'],'sub'=>$platformStats['institutions']['active'].' active / '.($platformStats['institutions']['pending_onboarding'] ?? 0).' onboarding','icon'=>'building-library','tone'=>'primary'],
                    ['label'=>'Students','value'=>$platformStats['users']['students'],'sub'=>$platformStats['users']['active_students'].' verified · '.($platformStats['users']['external_learners'] ?? 0).' external','icon'=>'identification','tone'=>'info'],
                    ['label'=>'Staff','value'=>$platformStats['users']['staff'],'sub'=>'+ '.$platformStats['users']['administrators'].' administrators','icon'=>'users','tone'=>'accent'],
                    ['label'=>'Courses','value'=>$platformStats['academic']['courses'],'sub'=>$platformStats['academic']['active_courses'].' active offerings','icon'=>'book-open','tone'=>'info'],
                    ['label'=>'Programmes','value'=>$platformStats['academic']['programmes'],'sub'=>$platformStats['academic']['departments'].' departments · '.$platformStats['academic']['faculties'].' faculties','icon'=>'academic-cap','tone'=>'primary'],
                    ['label'=>'Onboarding','value'=>$platformStats['institutions']['pending_onboarding'],'sub'=>'Institutions pending setup','icon'=>'clipboard-check','tone'=>'warn'],
                ];
            @endphp
            @foreach ($cards as $card)
                <a href="{{ $card['label']==='Institutions' ? route('superadmin.institutions') : ($card['label']==='Students' ? '#' : ($card['label']==='Staff' ? route('superadmin.staff') : ($card['label']==='Courses' ? '#' : ($card['label']==='Programmes' ? route('superadmin.academic') : ($card['label']==='Onboarding' ? route('superadmin.onboarding') : '#'))))) }}"
                   class="group relative rounded-2xl border border-border bg-surface p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md hover:border-primary/30">
                    <div class="flex items-start justify-between">
                        <div class="rounded-xl bg-{{ $card['tone']=='primary' ? 'primary/10' : ($card['tone']=='warn' ? 'amber-50' : ($card['tone']=='info' ? 'sky-50' : 'emerald-50')) }} p-2.5">
                            <x-ui.icon name="{{ $card['icon'] }}" class="h-5 w-5 text-{{ $card['tone']=='primary' ? 'primary' : ($card['tone']=='warn' ? 'amber-600' : 'emerald-600') }}" />
                        </div>
                        <span class="text-2xl font-extrabold text-text leading-none">{{ $card['value'] }}</span>
                    </div>
                    <h3 class="mt-3 text-sm font-bold text-text">{{ $card['label'] }}</h3>
                    <p class="mt-0.5 text-xs text-muted">{{ $card['sub'] }}</p>
                </a>
            @endforeach
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Activity Overview --}}
        <section class="lg:col-span-2">
            <div class="rounded-2xl border border-border bg-surface shadow-sm">
                <div class="flex items-center justify-between px-6 py-4 border-b border-border">
                    <h2 class="text-base font-extrabold text-text">Activity Overview</h2>
                    <a href="{{ route('superadmin.activity') }}" class="text-xs font-semibold text-primary hover:underline">Full feed →</a>
                </div>
                <div class="p-6 space-y-6">
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-widest text-muted mb-3">Registrations</h3>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="rounded-xl bg-raised p-3"><div class="text-2xl font-extrabold text-text">{{ $activity['registrations']['today'] }}</div><div class="text-xs text-muted">Today</div></div>
                            <div class="rounded-xl bg-raised p-3"><div class="text-2xl font-extrabold text-text">{{ $activity['registrations']['this_week'] }}</div><div class="text-xs text-muted">This week</div></div>
                            <div class="rounded-xl bg-raised p-3"><div class="text-2xl font-extrabold text-text">{{ $activity['registrations']['this_month'] }}</div><div class="text-xs text-muted">This month</div></div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-widest text-muted mb-3">JAMB Verification</h3>
                        <div class="grid grid-cols-4 gap-3">
                            <div class="rounded-xl bg-raised p-3"><div class="text-xl font-extrabold text-text">{{ $activity['jamb']['requests_today'] }}</div><div class="text-xs text-muted">Requests today</div></div>
                            <div class="rounded-xl bg-emerald-50 p-3"><div class="text-xl font-extrabold text-emerald-700">{{ $activity['jamb']['successful'] }}</div><div class="text-xs text-muted">Verified</div></div>
                            <div class="rounded-xl bg-red-50 p-3"><div class="text-xl font-extrabold text-red-700">{{ $activity['jamb']['failed'] }}</div><div class="text-xs text-muted">Failed / Invalid</div></div>
                            <div class="rounded-xl bg-amber-50 p-3"><div class="text-xl font-extrabold text-amber-700">{{ $activity['jamb']['manual_required'] }}</div><div class="text-xs text-muted">Manual required</div></div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-widest text-muted mb-3">Content Changes</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-raised p-3"><div class="text-xl font-extrabold text-text">{{ $activity['content_changes']['today'] }}</div><div class="text-xs text-muted">Today</div></div>
                            <div class="rounded-xl bg-raised p-3"><div class="text-xl font-extrabold text-text">{{ $activity['content_changes']['this_week'] }}</div><div class="text-xs text-muted">This week</div></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- System Health --}}
        <section>
            <div class="rounded-2xl border border-border bg-surface shadow-sm">
                <div class="flex items-center justify-between px-6 py-4 border-b border-border">
                    <h2 class="text-base font-extrabold text-text">System Health</h2>
                    <a href="{{ route('superadmin.system') }}" class="text-xs font-semibold text-primary hover:underline">Details →</a>
                </div>
                <div class="p-6 space-y-4">
                    @foreach ($health as $label => $info)
                        <div class="flex items-center justify-between rounded-xl bg-raised px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-{{ $info['status']==='healthy' ? 'emerald-500' : ($info['status']==='degraded' ? 'amber-500' : ($info['status']==='warning' ? 'amber-500' : 'red-500')) }}"></span>
                                <span class="text-sm font-medium text-text">{{ $label }}</span>
                            </div>
                            <span class="text-xs text-muted">{{ $info['message'] }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-border px-6 py-3 bg-raised/50">
                    <p class="text-xs text-muted">All systems operational. Last check: {{ now()->format('H:i:s') }}</p>
                </div>
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent Activity Feed --}}
        <section>
            <div class="rounded-2xl border border-border bg-surface shadow-sm">
                <div class="flex items-center justify-between px-6 py-4 border-b border-border">
                    <h2 class="text-base font-extrabold text-text">Recent Activity</h2>
                    <a href="{{ route('superadmin.activity') }}" class="text-xs font-semibold text-primary hover:underline">Full feed →</a>
                </div>
                <div class="divide-y divide-border">
                    @forelse ($recentActivity as $event)
                        <a href="{{ $event->resource_type ? '#' : '#' }}" class="flex items-start gap-3 px-6 py-3 transition hover:bg-raised/50">
                            <div class="mt-0.5 h-2 w-2 flex-shrink-0 rounded-full bg-{{ $event->severity === 'high' ? 'red-400' : ($event->severity === 'medium' ? 'amber-400' : 'emerald-400') }}"></div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-text truncate">{{ $event->description ?? $event->action }}</p>
                                <p class="text-xs text-muted truncate">{{ $event->actor?->name ?? 'System' }} · {{ $event->created_at?->format('M d H:i') }} · {{ $event->organization?->name ?? 'Platform' }}</p>
                            </div>
                        </a>
                    @empty
                        <div class="px-6 py-8 text-sm text-muted">No recent audit events.</div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Institutions Needing Attention --}}
        <section>
            <div class="rounded-2xl border border-border bg-surface shadow-sm">
                <div class="flex items-center justify-between px-6 py-4 border-b border-border">
                    <h2 class="text-base font-extrabold text-text">Institutions Requiring Attention</h2>
                    <a href="{{ route('superadmin.institutions') }}" class="text-xs font-semibold text-primary hover:underline">All ›</a>
                </div>
                <div class="divide-y divide-border">
                    @forelse ($institutionsNeedingAttention as $item)
                        <a href="{{ route('superadmin.institutions.show', $item['organization']) }}" class="flex items-center gap-3 px-6 py-3 transition hover:bg-raised/50">
                            <span class="h-2 w-2 flex-shrink-0 rounded-full bg-{{ $item['severity']==='high' ? 'red-400' : ($item['severity']==='medium' ? 'amber-400' : 'sky-400') }}"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-text">{{ $item['organization']->name }}</p>
                                <p class="text-xs text-muted">{{ implode(' · ', $item['issues']) }}</p>
                            </div>
                            <span class="text-xs font-bold text-muted">{{ $item['organization']->status ?? 'active' }}</span>
                        </a>
                    @empty
                        <div class="px-6 py-8 text-sm text-muted">No institutions currently require attention.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    {{-- Active Alerts --}}
    @if ($alerts->isNotEmpty())
    <section>
        <h2 class="text-lg font-extrabold tracking-tight text-text mb-4">Active Alerts</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @foreach ($alerts->take(3) as $alert)
                <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="rounded-full bg-{{ $alert->severity === 'critical' ? 'red-100 text-red-700' : ($alert->severity === 'warning' ? 'amber-100 text-amber-700' : 'sky-100 text-sky-700') }} px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide">{{ $alert->severity }}</span>
                        <span class="text-xs text-muted">{{ $alert->created_at->format('M d, Y H:i') }}</span>
                    </div>
                    <h3 class="text-base font-bold text-text mb-1">{{ $alert->title }}</h3>
                    <p class="text-sm text-muted mb-3">{{ $alert->message ?? '' }}</p>
                    <form action="{{ route('superadmin.alerts.acknowledge', $alert) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-primary-fg transition hover:bg-primary/90">Acknowledge</button>
                    </form>
                </div>
            @endforeach
        </div>
    </section>
    @endif

</div>
@endsection