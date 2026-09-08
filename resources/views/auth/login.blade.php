@extends('layouts.auth')

@section('title', 'Sign in — ACL')

@section('content')
<div class="w-full max-w-md">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-extrabold tracking-tight text-text">Welcome back</h1>
        <p class="mt-2 text-sm text-muted">Sign in to reach your courses and pick up where you left off.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 text-sm text-primary" role="status">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}"
          class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        @csrf

        @error('email')
            <p class="mb-4 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 text-xs text-primary" role="alert">{{ $message }}</p>
        @enderror

        <label class="mb-4 block">
            <span class="text-sm font-medium text-text">Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text placeholder-muted transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
        </label>

        <label class="mb-5 block">
            <span class="text-sm font-medium text-text">Password</span>
            <input type="password" name="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text placeholder-muted transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
        </label>

        <div class="mb-5 flex items-center justify-between gap-4">
            <label class="flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="remember" value="1" class="rounded border-border text-primary focus:ring-ring">
                Remember me
            </label>

            <a href="{{ route('password.request') }}"
               class="text-sm font-medium text-primary underline-offset-4 hover:underline focus:outline-none focus:ring-2 focus:ring-ring">
                Forgot password?
            </a>
        </div>

        <button type="submit"
                class="press w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
            Login
        </button>
    </form>

    <p class="mt-6 text-center text-xs text-muted">Access is provided through your institution.</p>
</div>
@endsection
