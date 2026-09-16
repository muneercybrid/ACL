@extends('superadmin.layout.app')
@section('title', 'Reports — Superadmin')
@section('header')
    <div><h1 class="text-xl font-extrabold tracking-tight text-text">Reports</h1><p class="text-sm text-muted">Generate and export operational reports</p></div>
@endsection
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <h2 class="text-base font-extrabold text-text mb-3">Date Range</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($reportTypes as $type => $label)
                <form action="{{ route('superadmin.reports.export', $type) }}" method="GET" class="rounded-2xl border border-border p-5 transition hover:border-primary/40 hover:shadow-sm">
                    <h3 class="text-sm font-bold text-text mb-2">{{ $label }}</h3>
                    <div class="flex gap-2 mb-3">
                        <input type="date" name="from" value="{{ now()->subDays(30)->format('Y-m-d') }}" class="w-1/2 rounded-lg border border-border bg-raised px-2 py-1.5 text-xs text-text focus:border-primary focus:outline-none">
                        <input type="date" name="to" value="{{ now()->format('Y-m-d') }}" class="w-1/2 rounded-lg border border-border bg-raised px-2 py-1.5 text-xs text-text focus:border-primary focus:outline-none">
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2 text-xs font-bold text-primary-fg transition hover:bg-primary/90">Export CSV</button>
                </form>
            @endforeach
        </div>
    </div>
    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <h2 class="text-base font-extrabold text-text mb-2">About Reports</h2>
        <p class="text-sm text-muted">Reports are generated from live ACL data — never fabricated figures. Expensive report generation uses queued background jobs where appropriate. Export files contain operational data only; credentials and secrets are never included.</p>
    </div>
</div>
@endsection