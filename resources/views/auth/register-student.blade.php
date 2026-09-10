@extends('layouts.auth')

@section('title', 'University Student Registration — ACL')

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
            Create your student account
        </h1>

        <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-muted sm:text-base">
            Verify your university identity and create your ACL student account.
        </p>
    </div>

    <div class="mb-6 rounded-2xl border border-border bg-surface p-4 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-fg">
                1
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-text">
                    Verify your student identity
                </p>
                <p class="mt-0.5 text-xs text-muted">
                    Select your JAMB examination year and enter your registration number.
                </p>
            </div>

            <span class="hidden rounded-full bg-raised px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-muted sm:inline-flex">
                Step 1
            </span>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-xl border border-primary/30 bg-primary/10 p-4 text-sm text-text">
            {{ session('success') }}
        </div>
    @endif

    @if (!empty($verification['verified']))
        <div class="mb-6 rounded-2xl border border-primary/30 bg-primary/10 p-6">
            <p class="text-xs font-bold uppercase tracking-wider text-primary">
                Congratulations, you are JAMB verified
            </p>

            <h2 class="mt-2 text-xl font-bold text-text">
                {{ $verification['name'] ?: 'Verified candidate' }}
            </h2>

            @if ($verification['institution'])
                <p class="mt-2 text-sm text-text">
                    <span class="font-semibold">Institution:</span>
                    {{ $verification['institution'] }}
                </p>
            @endif

            @if ($verification['programme'])
                <p class="mt-1 text-sm text-text">
                    <span class="font-semibold">Programme:</span>
                    {{ $verification['programme'] }}
                </p>
            @endif

            <p class="mt-4 text-xs leading-5 text-muted">
                Your verification is stored securely on ACL. The next account-creation step will use the server-side verification token rather than trusting browser-submitted academic information.
            </p>
        </div>
    @endif

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm sm:p-8">

        <div class="mb-6">
            <h2 class="text-lg font-bold text-text">
                JAMB verification
            </h2>

            <p class="mt-2 text-sm leading-6 text-muted">
                Enter your JAMB registration details exactly as they appear
                on your JAMB documents.
            </p>
        </div>

        <form id="jamb-verification-form" method="POST" action="{{ route('register.student.verify') }}">
            @csrf

            <div>
                <label for="jamb_exam_year"
                       class="block text-sm font-semibold text-text">
                    JAMB Examination Year
                </label>

                <div class="mt-2">
                    <select
                        id="jamb_exam_year"
                        name="jamb_exam_year"
                        required
                        class="block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20"
                    >
                        <option value="">Select examination year</option>

                        @foreach ($years as $year)
                            <option
                                value="{{ $year }}"
                                @selected((string) old('jamb_exam_year', $selectedYear) === (string) $year)
                            >
                                {{ $year }} Unified Tertiary Matriculation Examination (UTME)
                            </option>
                        @endforeach
                    </select>
                </div>

                @error('jamb_exam_year')
                    <p class="mt-2 text-sm font-medium text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="mt-5">
                <label for="jamb_exam_type"
                       class="block text-sm font-semibold text-text">
                    Examination Type
                </label>

                <div class="mt-2">
                    <select
                        id="jamb_exam_type"
                        name="jamb_exam_type"
                        required
                        class="block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20"
                    >
                        <option value="UTME" @selected(old('jamb_exam_type', 'UTME') === 'UTME')>
                            UTME
                        </option>
                    </select>
                </div>

                @error('jamb_exam_type')
                    <p class="mt-2 text-sm font-medium text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="mt-5">
                <label for="jamb_registration_number"
                       class="block text-sm font-semibold text-text">
                    JAMB Registration Number
                </label>

                <div class="mt-2">
                    <input
                        id="jamb_registration_number"
                        name="jamb_registration_number"
                        type="text"
                        value="{{ old('jamb_registration_number') }}"
                        maxlength="15"
                        minlength="1"
                        required
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        placeholder="Enter your JAMB registration number"
                        class="block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm uppercase text-text outline-none transition placeholder:text-muted focus:border-ring focus:ring-2 focus:ring-ring/20"
                    >
                </div>

                <p class="mt-2 text-xs leading-5 text-muted">
                    Up to 15 letters and numbers. Older JAMB registration
                    numbers may be shorter than newer formats.
                </p>

                @error('jamb_registration_number')
                    <p class="mt-2 text-sm font-medium text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="mt-6 rounded-xl border border-border bg-raised/60 p-4">
                <div class="flex gap-3">
                    <div class="mt-0.5 shrink-0 text-info" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.8"
                             class="h-5 w-5">
                            <circle cx="12" cy="12" r="9"/>
                            <path stroke-linecap="round" d="M12 10.5v5"/>
                            <path stroke-linecap="round" d="M12 7.5h.01"/>
                        </svg>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-text">
                            Why do we need this?
                        </p>

                        <p class="mt-1 text-xs leading-5 text-muted">
                            ACL uses your JAMB information to verify your
                            academic identity and connect your account to the
                            correct institution and programme.
                        </p>
                    </div>
                </div>
            </div>

            <button
                id="jamb-verify-button"
                type="submit"
                class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-bold text-primary-fg shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
            >
                <span id="jamb-verify-default" class="inline-flex items-center">
                    Verify JAMB Registration Number
                    <span class="ml-2" aria-hidden="true">→</span>
                </span>

                <span id="jamb-verify-loading" class="hidden items-center" aria-live="polite">
                    <svg
                        class="mr-2 h-5 w-5 animate-spin"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                        ></path>
                    </svg>
                    Verifying JAMB...
                </span>
            </button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('jamb-verification-form');
            const button = document.getElementById('jamb-verify-button');
            const defaultState = document.getElementById('jamb-verify-default');
            const loadingState = document.getElementById('jamb-verify-loading');

            if (!form || !button || !defaultState || !loadingState) {
                return;
            }

            form.addEventListener('submit', function () {
                if (button.disabled) {
                    return;
                }

                button.disabled = true;

                defaultState.classList.add('hidden');
                defaultState.classList.remove('inline-flex');

                loadingState.classList.remove('hidden');
                loadingState.classList.add('inline-flex');
            });
        });
    </script>

    <div class="mt-5 text-center">
        <p class="text-xs leading-5 text-muted">
            Your academic information is used only for account verification
            and ACL onboarding.
        </p>
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
