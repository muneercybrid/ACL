{{-- A student's own assessment for one chapter, generated on first request. --}}
@extends('layouts.app')

@section('title', 'Chapter assessment')

@section('content')
    <div class="py-6">
        <div class="mx-auto max-w-3xl space-y-6">

            <div class="rounded-xl border border-border bg-raised p-5">
                <h3 class="text-base font-semibold text-text">{{ $chapter->title }}</h3>
                @if ($reused)
                    <p class="mt-1 text-sm text-muted">
                        This is the set you were given. Keeping it the same means your result
                        still counts if you come back to it.
                    </p>
                @else
                    <p class="mt-1 text-sm text-muted">
                        These questions were written for you, from this chapter only. Another
                        student on this course receives a different set.
                    </p>
                @endif
            </div>

            @if (count($questions))
                <form method="POST" action="#">
                    @csrf
                    <ol class="space-y-5">
                        @foreach ($questions as $index => $question)
                            <li class="rounded-xl border border-border bg-raised p-5">
                                <p class="text-sm font-medium text-text">
                                    {{ $index + 1 }}. {{ $question['question'] }}
                                </p>

                                <div class="mt-3 space-y-2">
                                    @foreach ($question['options'] as $option)
                                        <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-border bg-bg px-3 py-2 text-sm text-text">
                                            <input type="radio" name="q{{ $question['position'] }}"
                                                   value="{{ $option }}"
                                                   class="mt-0.5 h-4 w-4 border-border text-primary focus:ring-primary">
                                            <span>{{ $option }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    <div class="mt-6">
                        <button type="submit" class="rounded-lg border border-primary bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90">Submit answers</button>
                    </div>
                </form>
            @else
                <div class="rounded-xl border border-dashed border-border bg-raised/40 p-8 text-center">
                    <h3 class="text-base font-semibold text-text">No questions could be prepared</h3>
                    <p class="mt-1 text-sm text-muted">
                        This chapter has no written content yet, so there is nothing to ask you
                        about. Questions are only written from what the chapter actually teaches.
                    </p>
                </div>
            @endif

            <a href="{{ url()->previous() }}" class="inline-flex text-sm font-medium text-primary hover:underline">
                Back to the course
            </a>
        </div>
    </div>
@endsection