@extends('layouts.app')
@section('content')
<div class="max-w-3xl mx-auto py-12">
  <div class="rounded-2xl border border-border bg-surface p-8 shadow-sm text-center">
    <h1 class="text-3xl font-extrabold mb-2">Congratulations, {{ $student->user->name ?? 'Student' }}!</h1>
    <p class="text-base text-muted mb-6">Your account has been successfully verified.</p>

    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 mb-6 text-left">
      <h2 class="text-xs font-bold uppercase tracking-wider text-muted mb-1">Programme of Study</h2>
      <p class="text-xl font-extrabold text-text">{{ $academicProgramme?->name ?? $institutionRecord?->academicProgram?->name ?? 'Not Assigned — Review Required' }}</p>
      <p class="text-xs text-muted mt-1">Level {{ $student->level ?? 'N/A' }} — Verified Institution</p>
    </div>

    <a href="{{ route('student.course.register') }}" class="inline-flex items-center rounded-xl bg-green-600 px-6 py-3 text-base font-bold text-white shadow hover:bg-green-700 transition">Proceed to Course Registration</a>
  </div>
</div>
@endsection
