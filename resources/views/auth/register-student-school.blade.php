@extends('layouts.auth')

@section('title', 'Complete Student Registration — ACL')

@section('content')
<div class="w-full max-w-2xl py-8 sm:py-12">

    <div class="mb-8 text-center">
        <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-xl font-extrabold text-primary-fg shadow-sm">
            A
        </div>

        <div class="mb-3 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted">
            <span>ACL Registration</span>
            <span aria-hidden="true">•</span>
            <span>University Student</span>
        </div>

        <h1 class="text-3xl font-extrabold tracking-tight text-text sm:text-4xl">
            Complete your account
        </h1>

        <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-muted sm:text-base">
            You are verified for your matched institution. Create your sign-in details.
        </p>
    </div>

    <div class="mb-6 rounded-2xl border border-border bg-surface p-4 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-fg">
                ✓
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-text">
                    Identity verified
                </p>
                <p class="mt-0.5 text-xs text-muted">
                    {{ $verification->verified_name }} — {{ $verification->verified_programme }}
                </p>
            </div>

            <span class="hidden rounded-full bg-raised px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-muted sm:inline-flex">
                Step 2
            </span>
        </div>
    </div>

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm sm:p-8">

        <div class="mb-6">
            <h2 class="text-lg font-bold text-text">
                Institution &amp; sign-in details
            </h2>

            <p class="mt-2 text-sm leading-6 text-muted">
                Your verified JAMB details are applied automatically. Your institution is fixed from the verified record and cannot be changed here.
            </p>
        </div>

        <form method="POST" action="{{ route('register.student.complete') }}">
            @csrf

            <div>
                <p class="block text-sm font-semibold text-text">
                    University
                </p>
                <p class="mt-2 rounded-xl border border-border bg-raised/50 px-4 py-3 text-sm font-semibold text-text">
                    {{ $verification->organization->name }}
                </p>
            </div>

            <div class="mt-5">
                <label for="school_registration_number"
                       class="block text-sm font-semibold text-text">
                    School Registration Number
                </label>

                <div class="mt-2">
                    <input
                        id="school_registration_number"
                        name="school_registration_number"
                        type="text"
                        value="{{ old('school_registration_number') }}"
                        maxlength="100"
                        required
                        autocomplete="off"
                        placeholder="e.g. DUN/CS/2026/001"
                        class="block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition placeholder:text-muted focus:border-ring focus:ring-2 focus:ring-ring/20"
                    >
                </div>

                <p class="mt-2 text-xs leading-5 text-muted">
                    Your matriculation or school registration number, used to
                    identify you within your institution.
                </p>

                @error('school_registration_number')
                    <p class="mt-2 text-sm font-medium text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="mt-5">
                <label for="email"
                       class="block text-sm font-semibold text-text">
                    Email address
                </label>

                <div class="mt-2">
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        placeholder="you@example.com"
                        class="block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition placeholder:text-muted focus:border-ring focus:ring-2 focus:ring-ring/20"
                    >
                </div>

                @error('email')
                    <p class="mt-2 text-sm font-medium text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="mt-5">
                <label for="password"
                       class="block text-sm font-semibold text-text">
                    Password
                </label>

                <div class="mt-2">
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="At least 8 characters"
                        class="block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition placeholder:text-muted focus:border-ring focus:ring-2 focus:ring-ring/20"
                    >
                </div>

                @error('password')
                    <p class="mt-2 text-sm font-medium text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="mt-5">
                <label for="password_confirmation"
                       class="block text-sm font-semibold text-text">
                    Confirm password
                </label>

                <div class="mt-2">
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20"
                    >
                </div>
            </div>

            <button
                type="submit"
                class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-primary px-4 py-3.5 text-sm font-bold text-primary-fg shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
            >
                Create my student account
                <span class="ml-2" aria-hidden="true">→</span>
            </button>
        </form>

        <a
            href="{{ route('register.student.confirm') }}"
            class="mt-3 inline-flex w-full items-center justify-center rounded-xl border border-border bg-bg px-4 py-3 text-sm font-semibold text-text transition hover:bg-raised focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
        >
            ← Back to verified details
        </a>
    </div>

    <div class="mt-6 text-center">
        <p class="text-xs leading-5 text-muted">
            Your verified JAMB details are stored securely and were never
            entered on this page.
        </p>
    </div>

</div>
@endsection
