@extends('layouts.auth')

@section('title', 'Confirm JAMB Details — ACL')

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
            Confirm your JAMB details
        </h1>

        <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-muted sm:text-base">
            JAMB verification was successful. Please review the information below before continuing.
        </p>
    </div>

    <div class="mb-6 rounded-2xl border border-primary/30 bg-primary/10 p-6 shadow-sm">
        <div class="flex items-start gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary text-xl font-bold text-primary-fg">
                ✓
            </div>

            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wider text-primary">
                    Congratulations, you are JAMB verified
                </p>

                <p class="mt-1 text-sm leading-6 text-text">
                    Your academic identity was successfully verified against the JAMB Matriculation List.
                </p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm sm:p-8">
        <div class="mb-6">
            <h2 class="text-lg font-bold text-text">
                Verified information
            </h2>

            <p class="mt-2 text-sm leading-6 text-muted">
                Confirm that the information below belongs to you. These academic details were obtained from JAMB and cannot be edited here.
            </p>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-border bg-raised/50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">
                    Full name
                </p>
                <p class="mt-1 text-base font-bold text-text">
                    {{ $verification->verified_name }}
                </p>
            </div>

            <div class="rounded-xl border border-border bg-raised/50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">
                    Institution
                </p>
                <p class="mt-1 text-base font-bold text-text">
                    {{ $verification->verified_institution }}
                </p>
            </div>

            <div class="rounded-xl border border-border bg-raised/50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">
                    Programme
                </p>
                <p class="mt-1 text-base font-bold text-text">
                    {{ $verification->verified_programme }}
                </p>
            </div>

            <div class="rounded-xl border border-border bg-raised/50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">
                    JAMB examination
                </p>
                <p class="mt-1 text-base font-bold text-text">
                    {{ $verification->jamb_exam_year }}
                    {{ $verification->jamb_exam_type }}
                </p>
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-border bg-bg p-4">
            <div class="flex gap-3">
                <div class="mt-0.5 shrink-0 text-info" aria-hidden="true">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        class="h-5 w-5"
                    >
                        <circle cx="12" cy="12" r="9"/>
                        <path stroke-linecap="round" d="M12 10.5v5"/>
                        <path stroke-linecap="round" d="M12 7.5h.01"/>
                    </svg>
                </div>

                <div>
                    <p class="text-sm font-semibold text-text">
                        Please check carefully
                    </p>

                    <p class="mt-1 text-xs leading-5 text-muted">
                        If these details are yours, confirm them to continue with ACL registration. If they are not correct, go back and verify again using the correct JAMB information.
                    </p>
                </div>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('register.student.confirm.continue') }}"
            class="mt-6"
        >
            @csrf

            <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-xl bg-primary px-4 py-3.5 text-sm font-bold text-primary-fg shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
            >
                Confirmed & Continue
                <span class="ml-2" aria-hidden="true">→</span>
            </button>
        </form>

        <a
            href="{{ route('register.student') }}"
            class="mt-3 inline-flex w-full items-center justify-center rounded-xl border border-border bg-bg px-4 py-3 text-sm font-semibold text-text transition hover:bg-raised focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
        >
            ← Verify Again
        </a>
    </div>

    <div class="mt-6 text-center">
        <p class="text-xs leading-5 text-muted">
            Your JAMB registration number is not displayed here. ACL keeps sensitive verification information protected on the server.
        </p>
    </div>
</div>
@endsection
