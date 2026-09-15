@extends('layouts.auth')

@section('title', 'University Student Registration — ACL')

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
                                @selected((string) old('jamb_exam_year') === (string) $year)
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
                        required
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        placeholder="Enter your JAMB registration number"
                        class="block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm uppercase text-text outline-none transition placeholder:text-muted focus:border-ring focus:ring-2 focus:ring-ring/20"
                    >
                </div>

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
                id="verify-btn"
                type="submit"
                class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-bold text-primary-fg shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
            >
                <span id="verify-btn-text">Verify JAMB Registration Number</span>
                <span id="verify-btn-spinner" class="hidden ml-2">
                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
            </button>
        </form>
    </div>

    <!-- Loading modal/popup with visible circling animation -->
    <div id="loading-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="loading-title">
        <div class="bg-surface rounded-2xl p-8 max-w-md mx-4 text-center shadow-2xl border border-primary/10">
            <!-- Multiple circling rings animation with orbiting dots - colorful -->
            <div class="relative h-32 w-32 mx-auto mb-4" id="circling-animation">
                <!-- Outer ring - slow spin with gradient -->
                <svg class="absolute inset-0 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="animation-duration: 3s;">
                    <defs>
                        <linearGradient id="outerRing" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#10B981"/>
                            <stop offset="50%" stop-color="#34D399"/>
                            <stop offset="100%" stop-color="#6EE7B7"/>
                        </linearGradient>
                    </defs>
                    <circle cx="12" cy="12" r="11" stroke="url(#outerRing)" stroke-width="3" stroke-linecap="round" stroke-dasharray="65 5"></circle>
                </svg>
                <!-- Middle ring - medium spin reverse with gradient -->
                <svg class="absolute inset-0 animate-spin-reverse" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="animation-duration: 2s;">
                    <defs>
                        <linearGradient id="middleRing" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#059669"/>
                            <stop offset="50%" stop-color="#10B981"/>
                            <stop offset="100%" stop-color="#34D399"/>
                        </linearGradient>
                    </defs>
                    <circle cx="12" cy="12" r="9" stroke="url(#middleRing)" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="50 8"></circle>
                </svg>
                <!-- Inner ring - fast spin with gradient -->
                <svg class="absolute inset-0 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="animation-duration: 1s;">
                    <defs>
                        <linearGradient id="innerRing" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#065F46"/>
                            <stop offset="50%" stop-color="#059669"/>
                            <stop offset="100%" stop-color="#10B981"/>
                        </linearGradient>
                    </defs>
                    <circle cx="12" cy="12" r="7" stroke="url(#innerRing)" stroke-width="3" stroke-linecap="round" stroke-dasharray="40 10"></circle>
                </svg>
                <!-- Orbiting colored dots -->
                <div class="absolute inset-0 animate-spin" style="animation-duration: 2.5s;">
                    <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 rounded-full" style="background: linear-gradient(135deg, #10B981, #34D399); box-shadow: 0 0 8px #10B981;"></div>
                    <div class="absolute top-1/2 right-0 -translate-x-1/2 -translate-y-1/2 w-3 h-3 rounded-full" style="background: linear-gradient(135deg, #34D399, #6EE7B7); box-shadow: 0 0 8px #34D399;"></div>
                    <div class="absolute bottom-0 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 rounded-full" style="background: linear-gradient(135deg, #059669, #10B981); box-shadow: 0 0 8px #059669;"></div>
                    <div class="absolute top-1/2 left-0 -translate-x-1/2 -translate-y-1/2 w-3 h-3 rounded-full" style="background: linear-gradient(135deg, #065F46, #059669); box-shadow: 0 0 8px #065F46;"></div>
                </div>
                <!-- Center magnifying glass icon with pulse -->
                <div class="absolute inset-0 flex items-center justify-center">
                    <svg class="h-12 w-12 text-primary animate-pulse" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
            <h3 id="loading-title" class="text-lg font-bold text-text">Verifying JAMB Registration</h3>
            <p class="mt-2 text-sm text-muted" id="loading-status">Contacting servers...</p>
            <p class="mt-4 text-xs text-muted">Fetching details, please wait...</p>
            <!-- Progress indicator -->
            <div class="mt-6 w-full h-1.5 bg-border rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-primary via-green-400 to-emerald-300 rounded-full animate-ping" style="width: 30%;"></div>
            </div>
        </div>
    </div>

    <!-- Result modal with filled form format -->
    <div id="result-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50" role="dialog" aria-modal="true" aria-labelledby="result-title">
        <div class="bg-surface rounded-2xl p-6 max-w-lg mx-4 shadow-xl max-h-[85vh] overflow-y-auto">
            <div id="result-icon" class="inline-flex items-center justify-center h-14 w-14 rounded-full mb-4 mx-auto"></div>
            <h3 id="result-title" class="text-lg font-bold text-text text-center"></h3>
            <div id="result-form" class="mt-4 text-left hidden"></div>
            <p id="result-message" class="mt-2 text-sm text-muted text-center hidden"></p>
            <div id="result-actions" class="mt-6 flex gap-3 justify-center"></div>
        </div>
    </div>

    <style>
        @keyframes spin-reverse {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }
        .animate-spin-reverse {
            animation: spin-reverse 1.5s linear infinite;
        }
        /* Staggered pulsing dots */
        .pulse-dots span {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            margin: 0 2px;
            animation: pulse 1.4s infinite ease-in-out both;
        }
        .pulse-dots span:nth-child(1) { animation-delay: -0.32s; }
        .pulse-dots span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes pulse {
            0%, 80%, 100% { transform: scale(0); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('jamb-verification-form');
            const verifyBtn = document.getElementById('verify-btn');
            const verifyBtnText = document.getElementById('verify-btn-text');
            const verifyBtnSpinner = document.getElementById('verify-btn-spinner');
            const loadingModal = document.getElementById('loading-modal');
            const loadingStatus = document.getElementById('loading-status');
            const resultModal = document.getElementById('result-modal');
            const resultIcon = document.getElementById('result-icon');
            const resultTitle = document.getElementById('result-title');
            const resultForm = document.getElementById('result-form');
            const resultMessage = document.getElementById('result-message');
            const resultActions = document.getElementById('result-actions');

            let statusIndex = 0;
            const statusMessages = [
                'Contacting servers...',
                'Fetching details...',
                'Processing response...',
                'Almost done...'
            ];
            let statusInterval;

            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                if (verifyBtn.disabled) return;

                // Show loading state
                verifyBtn.disabled = true;
                verifyBtnText.textContent = 'Verifying...';
                verifyBtnSpinner.classList.remove('hidden');
                loadingModal.classList.remove('hidden');
                loadingModal.classList.add('flex');

                // Cycle through status messages
                statusIndex = 0;
                loadingStatus.textContent = statusMessages[0];
                statusInterval = setInterval(() => {
                    statusIndex = (statusIndex + 1) % statusMessages.length;
                    loadingStatus.textContent = statusMessages[statusIndex];
                }, 3000);

                const formData = new FormData(form);

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    clearInterval(statusInterval);
                    const data = await response.json();

                    // Hide loading modal
                    loadingModal.classList.add('hidden');
                    loadingModal.classList.remove('flex');

                    if (data.outcome === 'verified') {
                        // Success - show filled form format
                        resultIcon.className = 'inline-flex items-center justify-center h-14 w-14 rounded-full bg-emerald-100 mb-4 mx-auto';
                        resultIcon.innerHTML = '<svg class="h-8 w-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                        resultTitle.textContent = 'Congratulations! Your JAMB status is verified.';
                        resultMessage.classList.add('hidden');
                        resultForm.classList.remove('hidden');
                        resultForm.innerHTML = `
                            <div class="space-y-3">
                                <div class="rounded-xl border border-border bg-raised/50 p-4">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-muted">Full Name</label>
                                    <p class="mt-1 text-base font-bold text-text">${escapeHtml(data.name)}</p>
                                </div>
                                <div class="rounded-xl border border-border bg-raised/50 p-4">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-muted">Institution</label>
                                    <p class="mt-1 text-base font-bold text-text">${escapeHtml(data.institution)}</p>
                                </div>
                                <div class="rounded-xl border border-border bg-raised/50 p-4">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-muted">Programme</label>
                                    <p class="mt-1 text-base font-bold text-text">${escapeHtml(data.programme)}</p>
                                </div>
                            </div>
                        `;
                        resultActions.innerHTML = `
                            <a href="${data.redirect}" class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-bold text-primary-fg shadow-sm transition hover:opacity-90">
                                Continue to Complete Registration →
                            </a>
                        `;
                    } else if (data.outcome === 'already_registered') {
                        resultIcon.className = 'inline-flex items-center justify-center h-14 w-14 rounded-full bg-amber-100 mb-4 mx-auto';
                        resultIcon.innerHTML = '<svg class="h-8 w-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
                        resultTitle.textContent = 'Already Registered';
                        resultForm.classList.add('hidden');
                        resultMessage.classList.remove('hidden');
                        resultMessage.textContent = data.message;
                        resultActions.innerHTML = `
                            <a href="{{ route('login') }}" class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-bold text-primary-fg shadow-sm transition hover:opacity-90">
                                Sign In
                            </a>
                        `;
                    } else {
                        // Error
                        resultIcon.className = 'inline-flex items-center justify-center h-14 w-14 rounded-full bg-red-100 mb-4 mx-auto';
                        resultIcon.innerHTML = '<svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
                        resultTitle.textContent = 'Verification Failed';
                        resultForm.classList.add('hidden');
                        resultMessage.classList.remove('hidden');
                        resultMessage.textContent = data.message;
                        resultActions.innerHTML = `
                            <button type="button" onclick="closeResultModal()" class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl border border-border bg-bg px-4 py-3 text-sm font-semibold text-text transition hover:bg-raised">
                                Try Again
                            </button>
                        `;
                    }

                    resultModal.classList.remove('hidden');
                    resultModal.classList.add('flex');

                } catch (error) {
                    clearInterval(statusInterval);
                    loadingModal.classList.add('hidden');
                    loadingModal.classList.remove('flex');

                    resultIcon.className = 'inline-flex items-center justify-center h-14 w-14 rounded-full bg-red-100 mb-4 mx-auto';
                    resultIcon.innerHTML = '<svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
                    resultTitle.textContent = 'Error';
                    resultForm.classList.add('hidden');
                    resultMessage.classList.remove('hidden');
                    resultMessage.textContent = 'An unexpected error occurred. Please try again.';
                    resultActions.innerHTML = `
                        <button type="button" onclick="closeResultModal()" class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl border border-border bg-bg px-4 py-3 text-sm font-semibold text-text transition hover:bg-raised">
                            Try Again
                        </button>
                    `;

                    resultModal.classList.remove('hidden');
                    resultModal.classList.add('flex');
                }
            });

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function closeResultModal() {
                resultModal.classList.add('hidden');
                resultModal.classList.remove('flex');
                verifyBtn.disabled = false;
                verifyBtnText.textContent = 'Verify JAMB Registration Number';
                verifyBtnSpinner.classList.add('hidden');
            }

            // Expose globally for onclick
            window.closeResultModal = closeResultModal;
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