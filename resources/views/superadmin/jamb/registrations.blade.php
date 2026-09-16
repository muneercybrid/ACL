@extends('superadmin.layout.app')
@section('title', 'Registration Monitoring — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">Registration Monitoring</h1><p class="text-sm text-muted">All student registrations & verification states</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
        <form action="{{ route('superadmin.registrations') }}" method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold text-muted mb-1">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="JAMB number, name or email…"
                       class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none">
            </div>
            <div class="min-w-[160px]">
                <label class="block text-xs font-bold text-muted mb-1">Status</label>
                <select name="status" class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none">
                    <option value="">All statuses</option>
                    @foreach (['verified','not_found','invalid_input','provider_timeout','provider_unavailable','temporary_failure','manual_verification_required','ambiguous','pending'] as $s)
                        <option value="{{ $s }}" {{ ($filters['status'] ?? '')===$s ? 'selected' : '' }}>{{ str_replace('_', ' ', ucfirst($s)) }}</option>
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
                    <th class="px-6 py-3">Created</th><th class="px-6 py-3">User</th><th class="px-6 py-3">JAMB Number</th><th class="px-6 py-3">Verified Name</th><th class="px-6 py-3">Institution</th><th class="px-6 py-3">Programme</th><th class="px-6 py-3">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                    @forelse ($registrations as $reg)
                        <tr class="hover:bg-raised/50 transition">
                            <td class="px-6 py-3 text-xs text-muted whitespace-nowrap">{{ $reg->created_at?->format('M d, Y H:i') }}</td>
                            <td class="px-6 py-3 text-sm text-text">{{ $reg->user?->name ?? '—' }}</td>
                            <td class="px-6 py-3 font-mono text-xs text-text">{{ $reg->jamb_registration_number ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm text-text">{{ $reg->verified_name ?? '—' }}</td>
                            <td class="px-6 py-3 text-xs text-muted max-w-[200px] truncate">{{ $reg->verified_institution ?? '—' }}</td>
                            <td class="px-6 py-3 text-xs text-muted">{{ $reg->verified_programme ?? '—' }}</td>
                            <td class="px-6 py-3">
                                @php
                                    $status = $reg->status ?? 'unknown';
                                    $color = $status === 'verified' ? 'emerald' : (in_array($status, ['provider_timeout','provider_unavailable','temporary_failure']) ? 'orange' : ($status === 'manual_verification_required' ? 'amber' : ($status === 'pending' ? 'sky' : 'red')));
                                @endphp
                                <span class="rounded-full bg-{{ $color }}-50 text-{{ $color }}-700 px-2 py-0.5 text-[10px] font-bold capitalize">{{ str_replace('_', ' ', $status) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-10 text-center text-sm text-muted">No registrations match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $registrations->links() }}
    </div>
</div>
@endsection