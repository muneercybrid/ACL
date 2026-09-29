@extends('layouts.auth')

@section('title', 'Set your password — ACL')

@section('content')
<div class="w-full max-w-md">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-extrabold tracking-tight text-text">Set your password</h1>
        <p class="mt-2 text-sm text-muted">
            Choose a password of your own. It is used every time you sign in.
        </p>
    </div>

    <form method="POST" action="{{ route('password.set.store') }}"
          class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        @csrf

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 text-xs text-primary" role="alert">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @unless (auth()->user()->force_password_change)
            <label class="mb-4 block">
                <span class="text-sm font-medium text-text">Current password</span>
                <input
                    type="password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                    class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring"
                >
            </label>
        @endunless

        <label class="mb-4 block">
            <span class="text-sm font-medium text-text">New password</span>
            <input
                type="password"
                name="password"
                required
                autofocus
                autocomplete="new-password"
                minlength="{{ $minimum }}"
                aria-describedby="password-help"
                class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring"
            >
            <span id="password-help" class="mt-1 block text-xs text-muted">
                At least {{ $minimum }} characters, with upper and lower case letters and a number.
            </span>
        </label>

        <label class="mb-5 block">
            <span class="text-sm font-medium text-text">Confirm new password</span>
            <input
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                minlength="{{ $minimum }}"
                class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring"
            >
        </label>

        <button type="submit"
                class="press w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
            Save password
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-xs text-muted underline hover:text-text">Sign out instead</button>
    </form>
</div>
@endsection
