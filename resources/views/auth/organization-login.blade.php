@extends('layouts.auth')

@section('title', $organization->name.' sign in — ACL')

@section('content')
<div class="w-full max-w-md">

    {{-- Back to the list: this is a two-step door, and a dead end on a wrong
         guess would strand the visitor. --}}
    <a href="{{ route('organizations.login') }}"
       class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-muted transition hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring">
        <span aria-hidden="true">←</span> All organizations
    </a>

    <div class="mb-8 text-center">
        {{-- The institution's identity. --}}
        <div aria-hidden="true"
             class="mx-auto mb-4 flex h-24 w-24 items-center justify-center rounded-2xl border border-border bg-raised text-2xl font-extrabold text-primary shadow-sm">
            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($organization->short_name ?: $organization->name, 0, 2)) }}
        </div>

        <h1 class="text-xl font-extrabold tracking-tight text-text">{{ $organization->name }}</h1>
        <p class="mt-1 text-sm text-muted">Staff sign in</p>
    </div>

    <form method="POST" action="{{ route('organizations.login.store', $organization) }}"
          class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        @csrf

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

        <label class="mb-5 flex items-center gap-2 text-sm text-muted">
            <input type="checkbox" name="remember" value="1"
                   class="rounded border-border text-primary focus:ring-ring">
            Remember me
        </label>

        <button type="submit"
                class="press w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
            Sign in
        </button>
    </form>

    {{-- One error slot for every refusal alike. Deliberately not itemised per
         field: a message that named the offending field would reveal whether the
         account exists or belongs here, which is exactly what this door must
         not disclose. --}}
    @error('email')
        <div class="mt-4 rounded-lg border border-red-300 bg-red-50 px-3 py-2.5 text-sm text-red-700" role="alert">
            {{ $message }}
        </div>
    @enderror

    {{-- No sign-up link here. Staff accounts are issued by ACL. --}}
    <p class="mt-6 text-center text-xs text-muted">
        Staff access is issued by ACL. Need an account?
        <a href="{{ route('contact.create') }}"
           class="font-semibold text-primary hover:underline">Contact your ACL administrator</a>.
    </p>
</div>
@endsection