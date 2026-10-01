@extends('layouts.auth')

@section('title', 'Superadmin sign in — ACL')

@section('content')
<div class="flex min-h-full items-center justify-center px-4">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <h1 class="text-xl font-bold text-text">ACL Command Center</h1>
            <p class="mt-1 text-sm text-muted">Superadmin sign in</p>
        </div>

        <div class="rounded-xl border border-border bg-surface p-6 shadow-sm">
            {{-- The admin palette, so this screen is never mistaken for the
                 student login it sits beside. --}}
            <div class="mb-5 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2">
                <p class="text-xs font-semibold text-primary">Restricted area</p>
                <p class="mt-0.5 text-xs text-muted">
                    Only accounts holding the platform Superadmin role can sign in here.
                    All attempts are recorded.
                </p>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('superadmin.auth.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-text">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                           autocomplete="username" autocapitalize="none" spellcheck="false"
                           class="w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-ring focus:ring-2 focus:ring-ring/20">
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-text">Password</label>
                    <input id="password" name="password" type="password" required
                           autocomplete="current-password"
                           class="w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-ring focus:ring-2 focus:ring-ring/20">
                </div>

                <button type="submit" class="w-full rounded-lg border border-primary bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">
                    Sign in to Command Center
                </button>
            </form>
        </div>

        <p class="mt-4 text-center text-xs text-muted">
            <a href="{{ route('login') }}" class="hover:text-text">Student or staff sign in</a>
        </p>
    </div>
</div>
@endsection
