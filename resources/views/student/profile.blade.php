@extends('layouts.app')

@section('title', 'My Profile — ACL')

@section('header')
    <x-ui.page-header title="Profile" subtitle="Your verified identity and account details" />
@endsection

@section('content')
<div class="mx-auto max-w-6xl px-4 py-6 sm:px-6">

    <!-- Profile Card -->
    <div class="rounded-2xl border border-border bg-surface shadow-sm overflow-hidden">
        <div class="bg-gradient-to-r from-primary/90 via-emerald-600/90 to-emerald-700/90 px-6 py-8 sm:px-8">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-2xl bg-white/20 shadow-inner overflow-hidden backdrop-blur-sm ring-2 ring-white/30">
                    <img src="{{ asset('images/logo.svg') }}" alt="ACL" class="h-16 w-16 object-contain filter brightness-0 invert">
                </div>
                <div class="min-w-0 flex-1">
                    <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">{{ $user->name }}</h1>
                    <p class="mt-1 text-sm font-medium text-emerald-50/90">{{ $user->email }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm">Student</span>
                        @if ($student && $student->nationality)
                        <span class="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm">{{ $student->nationality }}</span>
                        @endif
                    </div>
                    @if ($verification && $verification->verified_name)
                        <p class="mt-3 text-xs font-medium text-white/70">JAMB Verified: <span class="font-bold text-white">{{ $verification->verified_name }}</span>
                            @if ($verification->verified_institution) · {{ $verification->verified_institution }}@endif
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sections -->
        <div class="grid gap-0 lg:grid-cols-3 lg:gap-0 lg:divide-x lg:divide-border">

            <!-- BIODATA -->
            <div class="p-6">
                <h2 class="text-base font-extrabold text-text mb-4">Biodata</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between border-b border-border pb-2">
                        <dt class="text-muted">Full Name</dt>
                        <dd class="font-semibold text-text">{{ $user->name }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-border pb-2">
                        <dt class="text-muted">Email</dt>
                        <dd class="font-semibold text-text">{{ $user->email }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-border pb-2">
                        <dt class="text-muted">Nationality</dt>
                        <dd class="font-semibold text-text">{{ $student?->nationality ?? 'Not set' }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-border pb-2">
                        <dt class="text-muted">State</dt>
                        <dd class="font-semibold text-text">{{ $student?->state ?? 'Not set' }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-border pb-2">
                        <dt class="text-muted">LGA</dt>
                        <dd class="font-semibold text-text">{{ $student?->lga ?? 'Not set' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted">Account Created</dt>
                        <dd class="font-semibold text-text">{{ $user->created_at?->format('M d, Y') }}</dd>
                    </div>
                </dl>
            </div>

            <!-- ACADEMIC STATUS -->
            <div class="p-6">
                <h2 class="text-base font-extrabold text-text mb-4">Academic Status</h2>
                @if ($verification && $verification->verified_name)
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between border-b border-border pb-2">
                            <dt class="text-muted">Verified Name (JAMB)</dt>
                            <dd class="font-bold text-text">{{ $verification->verified_name }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-border pb-2">
                            <dt class="text-muted">Institution</dt>
                            <dd class="font-bold text-text">{{ $verification->verified_institution ?? 'Not verified' }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-border pb-2">
                            <dt class="text-muted">Programme</dt>
                            <dd class="font-bold text-text">{{ $verification->verified_programme ?? 'Not verified' }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-border pb-2">
                            <dt class="text-muted">Admission Year</dt>
                            <dd class="font-bold text-text">{{ $student?->admission_year ?? 'Not set' }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-border pb-2">
                            <dt class="text-muted">JAMB Status</dt>
                            <dd class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">{{ $verification->jamb_status ?? 'pending' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted">Verification Method</dt>
                            <dd class="font-semibold text-text">{{ $student?->verification_method ?? 'N/A' }}</dd>
                        </div>
                    </dl>
                @else
                    <div class="rounded-xl border border-dashed border-border bg-raised/30 p-5 text-center">
                        <p class="text-sm text-muted">No verified academic record found.</p>
                        <p class="mt-1 text-xs text-muted">Complete JAMB verification to link your identity.</p>
                    </div>
                @endif
            </div>

            <!-- SECURITY -->
            <div class="p-6">
                <h2 class="text-base font-extrabold text-text mb-4">Security</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between border-b border-border pb-2">
                        <dt class="text-muted">Account ID</dt>
                        <dd class="font-mono text-xs text-text">#{{ $user->id }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-border pb-2">
                        <dt class="text-muted">Email Verified</dt>
                        <dd class="font-semibold text-text">{{ $user->email_verified_at ? 'Yes — ' . $user->email_verified_at->format('M d, Y') : 'No' }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-border pb-2">
                        <dt class="text-muted">Last Updated</dt>
                        <dd class="font-semibold text-text">{{ $user->updated_at?->format('M d, Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted">Verification Method</dt>
                        <dd class="font-semibold text-text">{{ $student?->verification_method ?? 'Not verified' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection