@extends('layouts.auth')

@section('title', 'Create your ACL account — Anyone Can Learn')

@section('content')
<div class="w-full max-w-5xl py-8 sm:py-12">

    <div class="mx-auto mb-10 max-w-2xl text-center">
        <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-xl font-extrabold text-primary-fg shadow-sm">
            A
        </div>

        <h1 class="text-3xl font-extrabold tracking-tight text-text sm:text-4xl">
            Welcome to ACL
        </h1>

        <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-muted sm:text-base">
            Choose how you want to use ACL and we'll take you through the
            right registration process.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">

        {{-- University Student --}}
        <a href="{{ route('register.student') }}"
           class="group rounded-2xl border border-border bg-surface p-6 shadow-sm transition
                  hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-md
                  focus:outline-none focus:ring-2 focus:ring-ring">

            <div class="flex items-start justify-between gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="1.8"
                         class="h-6 w-6"
                         aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 14.25 3.75 9.75 12 5.25l8.25 4.5L12 14.25Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M7.5 12.2v4.05c0 1.55 2.01 2.8 4.5 2.8s4.5-1.25 4.5-2.8V12.2"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M20.25 10.25v5"/>
                    </svg>
                </div>

                <span class="text-muted transition-transform group-hover:translate-x-1"
                      aria-hidden="true">
                    →
                </span>
            </div>

            <h2 class="mt-5 text-lg font-bold text-text">
                University Student
            </h2>

            <p class="mt-2 text-sm leading-6 text-muted">
                Register as a student at a Nigerian university, verify your
                academic identity, access your institution's courses, and
                track your learning progress.
            </p>

            <div class="mt-5 text-sm font-semibold text-primary">
                Register as a student →
            </div>
        </a>

        {{-- External Learner --}}
        <a href="{{ route('register.external') }}"
           class="group rounded-2xl border border-border bg-surface p-6 shadow-sm transition
                  hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-md
                  focus:outline-none focus:ring-2 focus:ring-ring">

            <div class="flex items-start justify-between gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-info/10 text-info">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="1.8"
                         class="h-6 w-6"
                         aria-hidden="true">
                        <circle cx="12" cy="12" r="8.5"/>
                        <path stroke-linecap="round" d="M3.5 12h17"/>
                        <path stroke-linecap="round"
                              d="M12 3.5c2.2 2.3 3.3 5.15 3.3 8.5S14.2 18.2 12 20.5"/>
                        <path stroke-linecap="round"
                              d="M12 3.5c-2.2 2.3-3.3 5.15-3.3 8.5S9.8 18.2 12 20.5"/>
                    </svg>
                </div>

                <span class="text-muted transition-transform group-hover:translate-x-1"
                      aria-hidden="true">
                    →
                </span>
            </div>

            <h2 class="mt-5 text-lg font-bold text-text">
                External Learner
            </h2>

            <p class="mt-2 text-sm leading-6 text-muted">
                Learn independently through professional programmes,
                practical skills, certifications, and courses beyond the
                university curriculum.
            </p>

            <div class="mt-5 text-sm font-semibold text-primary">
                Start learning →
            </div>
        </a>

        {{-- Tutor --}}
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm">

            <div class="flex items-start justify-between gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-accent">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="1.8"
                         class="h-6 w-6"
                         aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4.5 5.25A2.25 2.25 0 0 1 6.75 3h10.5a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 17.25 21H6.75a2.25 2.25 0 0 1-2.25-2.25V5.25Z"/>
                        <path stroke-linecap="round" d="M8 7.5h8"/>
                        <path stroke-linecap="round" d="M8 11h8"/>
                        <path stroke-linecap="round" d="M8 14.5h5"/>
                    </svg>
                </div>

                <span class="rounded-full bg-raised px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-muted">
                    Coming soon
                </span>
            </div>

            <h2 class="mt-5 text-lg font-bold text-text">
                Tutor
            </h2>

            <p class="mt-2 text-sm leading-6 text-muted">
                Create your own courses, share your expertise, and teach
                learners through ACL.
            </p>

            <div class="mt-5 text-sm font-semibold text-muted">
                Tutor registration is coming soon
            </div>
        </div>

    </div>

    <div class="mt-8 text-center">
        <p class="text-sm text-muted">
            Already have an ACL account?
            <a href="{{ route('login') }}"
               class="font-semibold text-primary underline-offset-4 hover:underline
                      focus:outline-none focus:ring-2 focus:ring-ring">
                Sign in
            </a>
        </p>
    </div>

</div>
@endsection
