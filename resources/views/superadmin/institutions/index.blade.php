@extends('superadmin.layout.app')
@section('title', 'All Institutions — Superadmin')
@section('header')
    <div>
        <h1 class="text-xl font-extrabold tracking-tight text-text">All Institutions</h1>
        <p class="text-sm text-muted">{{ $institutions->total() }} institutions registered on ACL</p>
    </div>
@endsection
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    {{-- Filters --}}
    <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
        <form action="{{ route('superadmin.institutions') }}" method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold text-muted mb-1">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, slug or code…"
                       class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
            </div>
            <div class="min-w-[130px]">
                <label class="block text-xs font-bold text-muted mb-1">Status</label>
                <select name="status" class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                    <option value="">All</option>
                    <option value="active" {{ ($filters['status'] ?? '')==='active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ ($filters['status'] ?? '')==='inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="min-w-[130px]">
                <label class="block text-xs font-bold text-muted mb-1">Type</label>
                <select name="type" class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                    <option value="">All</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" {{ ($filters['type'] ?? '')===$type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-primary-fg transition hover:bg-primary/90">Filter</button>
            <a href="{{ route('superadmin.institutions') }}" class="text-sm text-muted hover:text-text transition">Clear</a>
        </form>
    </div>

    {{-- Add button --}}
    <div class="flex justify-end">
        <a href="{{ route('superadmin.institutions.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-primary-fg transition hover:bg-primary/90">
            <x-ui.icon name="plus" class="h-4 w-4" /> Add Institution
        </a>
    </div>

    {{-- Table --}}
    <div class="rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-border bg-raised text-left text-xs uppercase tracking-widest text-muted">
                    <th class="px-6 py-3">Institution</th>
                    <th class="px-6 py-3">Type</th>
                    <th class="px-6 py-3 text-center">Students</th>
                    <th class="px-6 py-3 text-center">Staff</th>
                    <th class="px-6 py-3 text-center">Admins</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3">Onboarding</th>
                    <th class="px-6 py-3"></th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                    @forelse ($institutions as $org)
                        <tr class="hover:bg-raised/50 transition">
                            <td class="px-6 py-4">
                                <a href="{{ route('superadmin.institutions.show', $org) }}" class="text-sm font-bold text-text hover:text-primary transition">{{ $org->name }}</a>
                                <div class="text-xs text-muted">{{ $org->code ?? '' }} @if($org->state) · {{ $org->state }} @endif</div>
                            </td>
                            <td class="px-6 py-4 text-xs text-muted capitalize">{{ $org->type ?? '—' }}</td>
                            <td class="px-6 py-4 text-center text-sm font-bold text-text">{{ $org->student_count }}</td>
                            <td class="px-6 py-4 text-center text-sm font-bold text-text">{{ $org->staff_count }}</td>
                            <td class="px-6 py-4 text-center text-sm font-bold text-text">{{ $org->admin_count }}</td>
                            <td class="px-6 py-4">
                                @if ($org->is_active)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">Active</span>
                                @else
                                    <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-red-700">Inactive</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if ($org->onboarding)
                                    <span class="rounded-full bg-{{ $org->onboarding->isCompleted() ? 'emerald-50 text-emerald-700' : 'amber-50 text-amber-700' }} px-2.5 py-0.5 text-xs font-bold">
                                        {{ ucfirst(str_replace('_', ' ', $org->onboarding->status)) }} ({{ $org->onboarding->getCompletionPercentage() }}%)
                                    </span>
                                @else
                                    <span class="text-xs text-muted">No record</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('superadmin.institutions.show', $org) }}" class="text-xs font-bold text-primary hover:underline">View →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-10 text-center text-sm text-muted">No institutions found matching your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $institutions->links() }}
</div>
@endsection