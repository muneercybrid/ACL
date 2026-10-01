@extends('layouts.auth')

@section('title', 'Invitation — ACL')

@section('content')
<div class="flex min-h-full items-center justify-center px-4">
    <div class="w-full max-w-lg">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-extrabold tracking-tight text-text">Congratulations</h1>
            <p class="mt-2 text-sm text-muted">You have been invited as <strong class="text-text">{{ $roleName }}</strong> of <strong class="text-text">{{ $organization?->name ?? 'this institution' }}</strong>.</p>
        </div>

        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
            <p class="text-xs text-muted mb-4">This link expires after 7 days and can only be used once. Choose your credentials to activate your account.</p>

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('invitation.store', $invitation->token) }}" class="space-y-4">
                @csrf

                <div>
                    <label for="name" class="mb-1 block text-sm font-medium text-text">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus class="w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-ring focus:ring-2 focus:ring-ring/20">
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-text">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required class="w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-ring focus:ring-2 focus:ring-ring/20">
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-text">Choose a password</label>
                    <input id="password" name="password" type="password" required class="w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-ring focus:ring-2 focus:ring-ring/20">
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium text-text">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text focus:border-ring focus:ring-2 focus:ring-ring/20">
                </div>

                <button type="submit" class="w-full rounded-lg border border-primary bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">
                    Activate account and sign in
                </button>
            </form>

            <p class="mt-4 text-center text-xs text-muted">
                Already have an account? <a href="{{ route('login') }}" class="hover:text-text">Sign in here</a> — or use the <a href="{{ route('organizations.login.show', $organization ?? 1) }}" class="hover:text-text">institution login</a>.
            </p>
        </div>
    </div>
</div>
@endsection
