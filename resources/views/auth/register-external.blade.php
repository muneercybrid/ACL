@extends('layouts.auth')

@section('title', 'External Learner Registration — ACL')

@section('content')
<div class="w-full max-w-2xl py-8 sm:py-12">

    <div class="mb-8 text-center">
        <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-xl font-extrabold text-primary-fg shadow-sm">
            <img src="{{ asset('images/logo.svg') }}" alt="ACL Logo" class="h-full w-full" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <span class="hidden text-xl font-extrabold text-primary-fg">A</span>
        </div>

        <div class="mb-3 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted">
            <span>ACL Registration</span>
            <span aria-hidden="true">•</span>
            <span>External Learner</span>
        </div>

        <h1 class="text-3xl font-extrabold tracking-tight text-text sm:text-4xl">
            Create your learning account
        </h1>

        <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-muted sm:text-base">
            Learn independently of any institution — professional programmes,
            external courses and advanced learning paths.
        </p>
    </div>

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm sm:p-8">

        <div class="mb-6">
            <h2 class="text-lg font-bold text-text">
                Coming soon
            </h2>

            <p class="mt-2 text-sm leading-6 text-muted">
                External learner registration is being prepared. Please
                check back soon.
            </p>
        </div>
    </div>

    <div class="mt-6 flex flex-col items-center justify-center gap-3 text-sm sm:flex-row">
        <a href="{{ route('register') }}"
           class="font-semibold text-primary underline-offset-4 hover:underline focus:outline-none focus:ring-2 focus:ring-ring">
            ← Choose another registration type
        </a>

        <span class="hidden text-muted sm:inline" aria-hidden="true">•</span>

        <a href="{{ route('login') }}"
           class="font-semibold text-primary underline-offset-4 hover:underline focus:outline-none focus:ring-2 focus:ring-ring">
            Already have an account? Sign in
        </a>
    </div>

</div>
@endsection
