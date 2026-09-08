@extends('layouts.auth')

@section('title', 'Choose a new password — ACL')

@section('content')
<div class="w-full max-w-md">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-extrabold tracking-tight text-text">Choose a new password</h1>
        <p class="mt-2 text-sm text-muted">
            Set a new password for your ACL account.
        </p>
    </div>

    <form method="POST" action="{{ route('password.update') }}"
          class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 text-xs text-primary" role="alert">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <label class="mb-4 block">
            <span class="text-sm font-medium text-text">Email</span>
            <input
                type="email"
                name="email"
                value="{{ old('email', $email) }}"
                required
                autofocus
                autocomplete="email"
                class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text placeholder-muted transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring"
            >
        </label>

        <label class="mb-4 block">
            <span class="text-sm font-medium text-text">New password</span>
            <input
                type="password"
                name="password"
                required
                autocomplete="new-password"
                minlength="8"
                class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text placeholder-muted transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring"
            >
        </label>

        <label class="mb-5 block">
            <span class="text-sm font-medium text-text">Confirm new password</span>
            <input
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                minlength="8"
                class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text placeholder-muted transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring"
            >
        </label>

        <button type="submit"
                class="press w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
            Reset password
        </button>
    </form>
</div>
@endsection
