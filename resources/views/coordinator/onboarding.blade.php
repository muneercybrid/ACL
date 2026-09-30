@extends('layouts.app')

@section('title', 'Set up your coordinator account — ACL')

@section('content')
<div class="mx-auto max-w-xl px-4 py-10 sm:px-6">

    <header class="mb-8 text-center">
        <h1 class="text-2xl font-extrabold tracking-tight text-text">Set up your account</h1>
        <p class="mt-2 text-sm text-muted">
            Before you can start, tell ACL who you are. The address
            <strong class="font-semibold text-text">{{ $account->email }}</strong>
            was generated for this role and is retired once you finish — please
            use an address of your own.
        </p>
    </header>

    <form method="POST" action="{{ route('coordinator.activate.store', $account->id) }}"
          class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        @csrf

        <label class="mb-4 block">
            <span class="text-sm font-medium text-text">Full name</span>
            <input type="text" name="name" value="{{ old('name', $account->name) }}" required
                   autocomplete="name"
                   class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
        </label>

        <label class="mb-4 block">
            <span class="text-sm font-medium text-text">Email address</span>
            <input type="email" name="email" value="{{ old('email') }}" required
                   autocomplete="email"
                   class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
            <span class="mt-1 block text-xs text-muted">
                Your own address. The generated one will no longer work.
            </span>
        </label>

        <label class="mb-4 block">
            <span class="text-sm font-medium text-text">Phone <span class="text-muted">(optional)</span></span>
            <input type="tel" name="phone" value="{{ old('phone') }}"
                   autocomplete="tel"
                   class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
        </label>

        <label class="mb-4 block">
            <span class="text-sm font-medium text-text">Password</span>
            <input type="password" name="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
            <span class="mt-1 block text-xs text-muted">
                At least 12 characters, with an upper and lower case letter and a number.
            </span>
        </label>

        <label class="mb-5 block">
            <span class="text-sm font-medium text-text">Confirm password</span>
            <input type="password" name="password_confirmation" required autocomplete="new-password"
                   class="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2.5 text-sm text-text transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring">
        </label>

        <button type="submit"
                class="press w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
            Finish setup
        </button>
    </form>

    @error('name')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
    @error('email')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
    @error('phone')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
    @error('password')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
@endsection