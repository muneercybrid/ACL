@extends('layouts.auth')

@section('title', 'Forgot password — ACL')

@section('content')
<div class="w-full max-w-md">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-extrabold tracking-tight text-text">Reset your password</h1>
        <p class="mt-2 text-sm text-muted">
            Enter your email address and, if an ACL account exists, we'll send you a secure reset link.
        </p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-primary/30 bg-primary/5 px-3 py-3 text-sm text-primary" role="status">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}"
          class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        @csrf

        @error('email')
            <p class="mb-4 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 text-xs text-primary" role="alert">
                {{ $message }}
            </p>
        @enderror

        <label class="mb-5 block">
            <span class="text-sm font-medium text-text">Email</span>
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="email"
                class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text placeholder-muted transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring"
            >
        </label>

        <button type="submit"
                class="press w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
            Send reset link
        </button>

        <div class="mt-5 text-center">
            <a href="{{ route('login') }}"
               class="text-sm font-medium text-primary underline-offset-4 hover:underline focus:outline-none focus:ring-2 focus:ring-ring">
                Back to login
            </a>
        </div>
    </form>
</div>
@endsection
