@extends('superadmin.layout.app')
@section('title', 'Curriculum Structures — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">Curriculum Structures</h1><p class="text-sm text-muted">All academic structure versions · Source · Scope</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
        <form action="{{ route('superadmin.academic.structures') }}" method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="min-w-[220px] flex-1">
                <label class="block text-xs font-bold text-muted mb-1">Programme</label>
                <select name="programme_id" class="w-full rounded-xl border border-border bg-raised px-4 py-2 text-sm text-text focus:border-primary focus:outline-none">
                    <option value="">All programmes</option>
                    @foreach ($programmes as $p)
                        <option value="{{ $p->id }}" {{ ($filters['programme_id'] ?? '')==$p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[140px]">
                <label class="block text-xs font-bold text-muted mb-1">Scope</label>
                <select name="scope" class="w-full rounded-xl border border-border bg-raised px-4 py-2 text-sm text-text focus:border-primary focus:outline-none">
                    <option value="">All</option>
                    @foreach (['nuc','institution','programme','external'] as $s)
                        <option value="{{ $s }}" {{ ($filters['scope'] ?? '')===$s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-primary-fg transition hover:bg-primary/90">Filter</button>
        </form>
    </div>

    <div class="rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-border bg-raised text-left text-xs uppercase tracking-widest text-muted">
                    <th class="px-6 py-3">Version</th><th class="px-6 py-3">Programme</th><th class="px-6 py-3">Academic Session</th><th class="px-6 py-3">Scope</th><th class="px-6 py-3">Source</th><th class="px-6 py-3">Courses</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                    @forelse ($versions as $version)
                        <tr class="hover:bg-raised/50 transition">
                            <td class="px-6 py-4 font-semibold text-text">{{ $version->version_label }}</td>
                            <td class="px-6 py-4 text-sm text-text">{{ $version->programme?->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-xs text-muted">{{ $version->academicSession?->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-xs text-muted">{{ $version->scope ?? '—' }}</td>
                            <td class="px-6 py-4 text-xs text-muted">{{ $version->source_type ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm font-bold text-text">{{ $version->curriculumCourses->count() }}</td>
                            <td class="px-6 py-4">
                                @if ($version->is_active)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">Active</span>
                                @else
                                    <span class="rounded-full bg-raised px-2.5 py-0.5 text-xs font-bold text-muted capitalize">{{ $version->verification_status ?? 'inactive' }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('superadmin.academic.programme', $version->programme) }}" class="text-xs font-bold text-primary hover:underline">View →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-10 text-center text-sm text-muted">No curriculum structures found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $versions->links() }}
    </div>
</div>
@endsection