@extends('layouts.app')
@section('content')
<div class="max-w-3xl mx-auto py-12">
  <h1 class="text-2xl font-extrabold">Course Registration — Semester Tabs</h1>
  <div class="flex gap-4 my-4">
    <a href="#" class="font-bold text-green-700 underline">Semester 1</a>
    <a href="#" class="font-bold text-muted">Semester 2</a>
  </div>
  <div class="rounded-2xl border bg-surface p-6 shadow-sm">
    <h2 class="font-bold mb-2">Semester 1 — Select Courses</h2>
    <p class="text-sm text-muted">Programme: {{ $programme?->name ?? 'Not Assigned' }} | Level: {{ $student->level ?? 'N/A' }}</p>
    <ul class="list-disc pl-5 mt-3 text-sm">
      @forelse($semester1 as $c)
        <li>{{ $c->course?->course_code ?? $c->course_code }} — {{ $c->course?->title ?? 'Course' }}</li>
      @empty
        <li class="text-muted">No Semester 1 courses selected yet. Use Add Course.</li>
      @endforelse
    </ul>
  </div>
</div>
@endsection
