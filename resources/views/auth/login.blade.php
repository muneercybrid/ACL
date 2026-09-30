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

    @error('google')
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
            {{ $message }}
        </div>
    @enderror

    <form method="POST" action="{{ route('login') }}"
          class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        @csrf

        @error('email')
            <p class="mb-4 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 text-xs text-primary" role="alert">{{ $message }}</p>
        @enderror

        {{-- Role selection. Choosing an area does not grant it: the server
             checks the choice against the account's real role assignments and
             refuses a mismatch, so this is a shortcut rather than a control. --}}
        <fieldset class="mb-5">
            <legend class="mb-2 text-sm font-medium text-text">Sign in as</legend>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                @foreach ($selectableRoles as $role)
                    <label class="relative">
                        <input type="radio" name="role" value="{{ $role }}"
                               class="peer sr-only"
                               @checked(old('role', 'student') === $role)>
                        <span class="flex cursor-pointer items-center justify-center rounded-lg border border-border bg-bg px-3 py-2.5 text-center text-xs font-semibold text-muted transition
                                     hover:border-primary/50 hover:text-primary
                                     peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary
                                     peer-focus-visible:ring-2 peer-focus-visible:ring-ring">
                            @switch($role)
                                @case(\App\Services\Auth\RoleHomeResolver::ROLE_STUDENT)
                                    Student
                                    @break
                                @case(\App\Services\Auth\RoleHomeResolver::ROLE_INSTITUTION_ADMIN)
                                    Institution Admin
                                    @break
                                @case(\App\Services\Auth\RoleHomeResolver::ROLE_LEVEL_COORDINATOR)
                                    Level Coordinator
                                    @break
                                @default
                                    {{ \Illuminate\Support\Str::headline($role) }}
                            @endswitch
                        </span>
                    </label>
                @endforeach
            </div>

            @error('role')
                <p class="mt-2 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">{{ $message }}</p>
            @enderror
        </fieldset

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

    <div class="mt-4 rounded-2xl border border-border bg-surface p-4 shadow-sm">
        <a href="{{ route('google.redirect') }}"
           class="flex w-full items-center justify-center gap-2 rounded-lg border border-border bg-bg px-4 py-2.5 text-sm font-semibold text-text shadow-sm transition hover:border-primary/30 hover:shadow focus:outline-none focus:ring-2 focus:ring-ring">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            Sign in with Google
        </a>
    </div>

    <p class="mt-6 text-center text-xs text-muted">Access is provided through your institution.</p>

    <div class="mt-4 text-center">
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-fg shadow-sm transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring">Get started — Create account</a>
        <p class="mt-2 text-xs text-muted">New to ACL? <a href="{{ route('register.student') }}" class="font-semibold text-primary hover:underline">Register as a student</a>.</p>
    </div>
</div>
@endsection
