@extends('superadmin.layout.app')

@section('title', $organization->name . ' — Institution — Superadmin')

@section('header')
    <div class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary font-bold text-sm">{{ strtoupper(substr($organization->name, 0, 2)) }}</div>
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-extrabold tracking-tight text-text">{{ $organization->name }}</h1>
                @if ($organization->is_active)
                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">Active</span>
                @else
                    <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-red-700">Suspended</span>
                @endif
            </div>
            <p class="text-sm text-muted">{{ $organization->type ?? 'University' }} · {{ $organization->code ?? '' }} · {{ $organization->state ?? '' }}</p>
        </div>
    </div>
@endsection

@section('content')
<div class="mx-auto max-w-7xl space-y-8">

    {{-- Stats row --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        @php
            $statCards = [
                ['label'=>'Students','value'=>$stats['students'],'icon'=>'identification','color'=>'primary'],
                ['label'=>'Staff','value'=>$stats['staff'],'icon'=>'users','color'=>'emerald'],
                ['label'=>'Admins','value'=>$stats['administrators'],'icon'=>'shield-check','color'=>'amber'],
                ['label'=>'Faculties','value'=>$stats['faculties'],'icon'=>'building-library','color'=>'sky'],
                ['label'=>'Departments','value'=>$stats['departments'],'icon'=>'folder','color'=>'teal'],
            ];
        @endphp
        @foreach ($statCards as $card)
            <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                <div class="text-2xl font-extrabold text-text">{{ $card['value'] }}</div>
                <div class="text-xs text-muted mt-1">{{ $card['label'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main content: tabs --}}
        <div class="lg:col-span-2 space-y-6" x-data="{ tab: 'overview' }">
            <div class="flex gap-1 overflow-x-auto rounded-xl bg-surface p-1 shadow-sm border border-border">
                @foreach (['overview','administrators','staff','students','audit'] as $t)
                    <button @click="tab='{{ $t }}'" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition whitespace-nowrap"
                            :class="tab==='{{ $t }}' ? 'bg-primary text-primary-fg shadow-sm' : 'text-muted hover:bg-raised'">{{ ucfirst($t) }}</button>
                @endforeach
            </div>

            {{-- Overview tab --}}
            <div x-show="tab==='overview'" class="space-y-4">
                <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
                    <h3 class="text-base font-extrabold text-text mb-4">Institution Information</h3>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <dt class="text-muted">Name</dt><dd class="font-medium text-text">{{ $organization->name }}</dd>
                        <dt class="text-muted">Type</dt><dd class="font-medium text-text">{{ $organization->type ?? '—' }}</dd>
                        <dt class="text-muted">Code</dt><dd class="font-medium text-text">{{ $organization->code ?? '—' }}</dd>
                        <dt class="text-muted">State</dt><dd class="font-medium text-text">{{ $organization->state ?? '—' }}</dd>
                        <dt class="text-muted">Website</dt><dd class="font-medium text-text">{{ $organization->website ? \Illuminate\Support\Str::limit($organization->website, 40) : '—' }}</dd>
                        <dt class="text-muted">Email</dt><dd class="font-medium text-text">{{ $organization->email ?? '—' }}</dd>
                        <dt class="text-muted">Description</dt><dd class="font-medium text-text">{{ $organization->description ?? '—' }}</dd>
                        <dt class="text-muted">Created</dt><dd class="font-medium text-text">{{ $organization->created_at instanceof \Carbon\Carbon ? $organization->created_at->format('M d, Y H:i') : ($organization->created_at ?? '—') }}</dd>
                    </dl>
                </div>

                <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
                    <h3 class="text-base font-extrabold text-text mb-4">Onboarding Status</h3>
                    @if ($onboarding->status)
                        @php
                            $steps = [
                                ['key'=>'institution_info','label'=>'Institution Information','done'=>($onboarding->progress['institution_info'] ?? false)],
                                ['key'=>'admin_profile','label'=>'Administrator Account','done'=>($onboarding->progress['admin_profile'] ?? false)],
                                ['key'=>'academic_structure','label'=>'Academic Structure','done'=>($onboarding->progress['academic_structure'] ?? false)],
                                ['key'=>'staff_setup','label'=>'Staff Setup','done'=>($onboarding->progress['staff_setup'] ?? false)],
                                ['key'=>'content_setup','label'=>'Content Setup','done'=>($onboarding->progress['content_setup'] ?? false)],
                            ];
                        @endphp
                        <div class="mb-4 flex items-center gap-2">
                            <div class="h-2.5 flex-1 rounded-full bg-raised overflow-hidden">
                                <div class="h-full rounded-full bg-primary transition-all duration-500" style="width:{{ $onboarding->getCompletionPercentage() }}%"></div>
                            </div>
                            <span class="text-xs font-bold text-primary">{{ $onboarding->getCompletionPercentage() }}%</span>
                        </div>
                        <div class="space-y-2">
                            @foreach ($steps as $step)
                                <div class="flex items-center gap-3 rounded-xl px-3 py-2 {{ $step['done'] ? 'bg-emerald-50' : 'bg-raised' }}">
                                    @if ($step['done'])
                                        <x-ui.icon name="check-circle" class="h-5 w-5 text-emerald-600" />
                                    @else
                                        <x-ui.icon name="x-circle" class="h-5 w-5 text-muted/50" />
                                    @endif
                                    <span class="text-sm font-medium {{ $step['done'] ? 'text-emerald-800' : 'text-muted' }}">{{ $step['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-muted">Current status: <strong class="text-text">{{ ucfirst(str_replace('_', ' ', $onboarding->status)) }}</strong>
                            @if ($onboarding->completed_at) · Completed {{ $onboarding->completed_at->format('M d, Y') }}@endif</p>
                    @else
                        <p class="text-sm text-muted">No onboarding record exists.</p>
                    @endif
                </div>

                <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
                    <h3 class="text-base font-extrabold text-text mb-4">Academic Structure</h3>
                    @if ($organization->faculties->count())
                        <div class="space-y-3">
                            @foreach ($organization->faculties as $faculty)
                                <div class="rounded-xl bg-raised p-4">
                                    <div class="flex items-center gap-2">
                                        <x-ui.icon name="building-library" class="h-4 w-4 text-primary" />
                                        <span class="text-sm font-bold text-text">{{ $faculty->name }}</span>
                                        @if ($faculty->code) <span class="text-xs text-muted">· {{ $faculty->code }}</span> @endif
                                    </div>
                                    @if ($faculty->departments->count())
                                        <div class="mt-2 grid grid-cols-2 gap-2">
                                            @foreach ($faculty->departments as $dept)
                                                <div class="text-xs text-muted pl-6">{{ $dept->name }} ({{ $dept->academicPrograms->count() }} programmes)</div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-xs text-muted pl-6 mt-1">No departments configured.</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-muted">No faculties configured for this institution yet.</p>
                    @endif
                </div>
            </div>

            {{-- Administrators tab --}}
            <div x-show="tab==='administrators'" class="space-y-4">
                <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-extrabold text-text">Institution Administrators</h3>
                    </div>
                    @if ($administrators->count())
                        <div class="divide-y divide-border">
                            @foreach ($administrators as $membership)
                                <div class="flex items-center gap-3 py-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary text-xs font-bold">
                                        {{ strtoupper(substr($membership->user?->name ?? '?', 0, 2)) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-text">{{ $membership->user?->name ?? 'Unknown' }}</p>
                                        <p class="text-xs text-muted">{{ $membership->user?->email ?? '' }} · Matric: {{ $membership->matric_number ?? '—' }}</p>
                                    </div>
                                    <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-bold text-primary">Institution Admin</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-muted">No administrator is currently assigned to this institution.</p>
                    @endif

                    <div class="mt-6 pt-4 border-t border-border">
                        <h4 class="text-sm font-bold text-text mb-3">Assign Administrator</h4>
                        <form action="{{ route('superadmin.institutions.assign-admin', $organization) }}" method="POST" class="flex gap-3">
                            @csrf
                            <select name="user_id" class="flex-1 rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                                <option value="">Select a user…</option>
                                @foreach ($candidateAdmins as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-primary-fg transition hover:bg-primary/90">Assign</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Staff tab --}}
            <div x-show="tab==='staff'" class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
                <h3 class="text-base font-extrabold text-text mb-4">Institution Staff ({{ $staffMembers->count() }})</h3>
                @if ($staffMembers->count())
                    <div class="divide-y divide-border">
                        @foreach ($staffMembers as $membership)
                            <div class="flex items-center gap-3 py-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold">
                                    {{ strtoupper(substr($membership->user?->name ?? '?', 0, 2)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-text">{{ $membership->user?->name ?? 'Unknown' }}</p>
                                    <p class="text-xs text-muted">{{ $membership->user?->email ?? '' }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-muted">No staff members recorded.</p>
                @endif
            </div>

            {{-- Students tab --}}
            <div x-show="tab==='students'" class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-extrabold text-text">Students ({{ $students->count() }})</h3>
                </div>
                @if ($students->count())
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="text-left text-xs uppercase tracking-widest text-muted">
                                <th class="pb-2">Name</th><th class="pb-2">Email</th><th class="pb-2">Programme</th><th class="pb-2">Level</th><th class="pb-2">Matric</th>
                            </tr></thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($students->take(30) as $s)
                                    <tr class="hover:bg-raised/50">
                                        <td class="py-2 font-medium text-text">{{ $s->user?->name ?? '—' }}</td>
                                        <td class="py-2 text-muted">{{ $s->user?->email ?? '' }}</td>
                                        <td class="py-2 text-muted">{{ $s->academicProgram?->name ?? '—' }}</td>
                                        <td class="py-2 text-muted">{{ $s->currentLevel?->name ?? '—' }}</td>
                                        <td class="py-2 text-muted">{{ $s->matric_number ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-muted">No students in this institution.</p>
                @endif
            </div>

            {{-- Audit tab --}}
            <div x-show="tab==='audit'" class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
                <h3 class="text-base font-extrabold text-text mb-4">Institution Audit Log</h3>
                @if ($recentActivity->count())
                    <div class="space-y-3">
                        @foreach ($recentActivity as $event)
                            <div class="flex items-start gap-3">
                                <span class="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-{{ $event->severity==='high'?'red-400':($event->severity==='medium'?'amber-400':'emerald-400') }}"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-text">{{ $event->description ?? $event->action }}</p>
                                    <p class="text-xs text-muted">{{ $event->actor?->name ?? 'System' }} · {{ $event->created_at?->format('M d, Y H:i:s') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-muted">No audit events for this institution yet.</p>
                @endif
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            {{-- Actions --}}
            <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-3">
                <h3 class="text-base font-extrabold text-text mb-2">Actions</h3>
                <form action="{{ route('superadmin.institutions.toggle', $organization) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full rounded-xl px-4 py-2.5 text-sm font-bold transition border {{ $organization->is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}">
                        {{ $organization->is_active ? 'Suspend Institution' : 'Activate Institution' }}
                    </button>
                </form>
                <a href="{{ route('superadmin.onboarding.show', $organization) }}" class="block w-full rounded-xl bg-raised px-4 py-2.5 text-sm font-bold text-text text-center transition hover:bg-raised/80">View Onboarding</a>
            </div>

            {{-- Quick info --}}
            <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-3">
                <h3 class="text-base font-extrabold text-text mb-2">Quick Info</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">ID</dt><dd class="text-text font-mono text-xs">{{ $organization->id }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Slug</dt><dd class="text-text font-mono text-xs">{{ $organization->slug }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Created</dt><dd class="text-text">{{ $organization->created_at?->format('M d, Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Onboarded</dt><dd class="text-text">{{ $organization->onboarded_at instanceof \Carbon\Carbon ? $organization->onboarded_at->format('M d, Y') : ($organization->onboarded_at ?? 'Not yet') }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection