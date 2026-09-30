@extends('layouts.app')

@section('title', 'Email confirmed — ACL')

@section('content')
<div class="mx-auto max-w-3xl py-12">
  <div class="rounded-2xl border border-border bg-surface p-8 text-center shadow-sm">
    {{-- This page is the confirmation receipt. The dashboard's unverified
         banner keys off email_verified_at, so it disappears once this link is
         followed. --}}
    <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-3xl text-primary">✓</div>

    <h1 class="mb-2 text-3xl font-extrabold text-text">
      @if ($student)
        Congratulations, {{ $student->user->name }}!
      @else
        This link is no longer valid
      @endif
    </h1>

    <p class="mb-6 text-base text-muted">
      @if ($student)
        @if ($alreadyConfirmed ?? false)
          Your email address was already confirmed. Everything is in order.
        @else
          Your account has been successfully verified, and the reminder has been
          removed from your dashboard.
        @endif
      @else
        We could not find the account this link referred to. It may have been
        replaced by a newer verification email — please use the most recent one
        in your inbox.
      @endif
    </p>

    @if ($student)
      <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-left">
        <h2 class="mb-1 text-xs font-bold uppercase tracking-wider text-muted">Programme of Study</h2>
        <p class="text-xl font-extrabold text-text">{{ $academicProgramme?->name ?? $institutionRecord?->academicProgram?->name ?? 'Not Assigned — Review Required' }}</p>
        <p class="mt-1 text-xs text-muted">Level {{ $student->level ?? 'N/A' }} — Verified Institution</p>
      </div>

      <div class="flex flex-wrap items-center justify-center gap-3">
        <a href="{{ route('login') }}"
           class="inline-flex items-center rounded-xl bg-primary px-6 py-3 text-base font-bold text-primary-fg shadow transition hover:bg-accent">
          Go to dashboard
        </a>
        <a href="{{ route('student.course.register') }}"
           class="inline-flex items-center rounded-xl border border-border bg-surface px-6 py-3 text-base font-bold text-text shadow transition hover:border-primary">
          Course registration
        </a>
      </div>
    @else
      <a href="{{ route('login') }}"
         class="inline-flex items-center rounded-xl bg-primary px-6 py-3 text-base font-bold text-primary-fg shadow transition hover:bg-accent">
        Go to login
      </a>
    @endif
  </div>
</div>
@endsection
