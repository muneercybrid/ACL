@extends('layouts.app')

@section('title', 'Verify your email')

@section('content')
<div class="mx-auto max-w-md px-4 py-16 text-center">
    <x-ui.card class="p-8">
        <h1 class="text-2xl font-extrabold text-text">Verify your email</h1>
        <p class="mt-3 text-sm text-muted">
            We sent a verification link to <strong class="text-text">{{ auth()->user()->email }}</strong>.
            Click the link to activate your account.
        </p>

        @if (session('status'))
            <p class="mt-4 rounded-lg border border-primary/30 bg-primary/5 px-3 py-3 text-sm text-primary">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('register.verification.send') }}" class="mt-6">
            @csrf
            <button class="w-full rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-primary-fg transition hover:bg-primary/90">Resend verification email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button class="w-full rounded-xl border border-border bg-surface px-6 py-3 text-sm font-semibold text-muted transition hover:bg-raised">Sign out</button>
        </form>
    </x-ui.card>
</div>
@endsection
