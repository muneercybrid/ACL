@extends('layouts.auth')

@section('title', 'Complete Registration — ACL')

@section('content')
<div class="w-full max-w-2xl py-8 sm:py-12">

    <div class="mb-8 text-center">
        <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-xl font-extrabold text-primary-fg shadow-sm">
            A
        </div>
        <div class="mb-3 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted">
            <span>ACL Registration</span>
            <span aria-hidden="true">•</span>
            <span>Complete Account</span>
        </div>
        <h1 class="text-3xl font-extrabold tracking-tight text-text sm:text-4xl">
            Complete your account
        </h1>
        <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-muted sm:text-base">
            Confirm your verified details and create your sign-in details.
        </p>
    </div>

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm sm:p-8">
        <div class="mb-6 rounded-2xl border border-primary/30 bg-primary/10 p-5">
            <p class="text-xs font-bold uppercase tracking-wider text-primary">Verified</p>
            <h2 class="mt-2 text-xl font-bold text-text">{{ $verification->verified_name }}</h2>
            <p class="mt-1 text-sm text-text"><span class="font-semibold">Institution:</span> {{ $verification->verified_institution }}</p>
            <p class="mt-0.5 text-sm text-text"><span class="font-semibold">Programme:</span> {{ $verification->verified_programme }}</p>
        </div>

        <form method="POST" action="{{ route('register.student.complete') }}">
            @csrf

            <div class="mt-5">
                <label for="email" class="block text-sm font-semibold text-text">Email address</label>
                <input id="email" name="email" type="email" required value="{{ old('email') }}" autocomplete="email" placeholder="you@example.com" class="mt-2 block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20" />
                @error('email') <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-5">
                <label for="phone" class="block text-sm font-semibold text-text">Phone number</label>
                <input id="phone" name="phone" type="text" required value="{{ old('phone') }}" placeholder="08012345678" class="mt-2 block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20" />
                @error('phone') <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-5">
                <label for="nationality" class="block text-sm font-semibold text-text">Nationality</label>
                <div class="mt-2">
                    <select id="nationality" name="nationality" required class="block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20">
                        <option value="">Select nationality</option>
                        <option value="Nigerian" @selected(old('nationality') == 'Nigerian')>Nigerian</option>
                        <option value="Other" @selected(old('nationality') == 'Other')>Other</option>
                    </select>
                </div>
                @error('nationality') <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-5">
                <label for="state_id" class="block text-sm font-semibold text-text">State of origin</label>
                <div class="mt-2">
                    <select id="state_id" name="state_id" required class="block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20">
                        <option value="">Select state</option>
                        @foreach ($states as $state)
                            <option value="{{ $state->id }}" @selected(old('state_id') == $state->id)>{{ $state->name }}</option>
                        @endforeach
                    </select>
                </div>
                @error('state_id') <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-5" id="lga-wrapper" style="display:none;">
                <label for="lga_id" class="block text-sm font-semibold text-text">Local Government Area (LGA)</label>
                <div class="mt-2">
                    <select id="lga_id" name="lga_id" required class="block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20">
                        <option value="">Select LGA</option>
                    </select>
                </div>
                @error('lga_id') <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-5">
                <label for="school_registration_number" class="block text-sm font-semibold text-text">School Registration Number</label>
                <input id="school_registration_number" name="school_registration_number" type="text" value="{{ old('school_registration_number') }}" placeholder="Your institution-issued student number" class="mt-2 block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20" />
                @error('school_registration_number') <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-5">
                <label for="password" class="block text-sm font-semibold text-text">Password</label>
                <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters" class="mt-2 block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20" />
                @error('password') <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-5">
                <label for="password_confirmation" class="block text-sm font-semibold text-text">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 block w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20" />
            </div>

            <button type="submit" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-primary px-4 py-3.5 text-sm font-bold text-primary-fg shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2">
                Complete Registration →
            </button>
        </form>

        <div class="mt-6 border-t border-border pt-6">
            <div class="rounded-xl border border-border bg-raised/40 p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-muted mb-3">Alternative sign in</p>
                <a href="{{ route('google.redirect') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-border bg-bg px-4 py-2.5 text-sm font-semibold text-text shadow-sm transition hover:border-primary/30 hover:shadow focus:outline-none focus:ring-2 focus:ring-ring">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0-5.523-4.477-10-10-10S1 4.477 1 10s4.477 10 10 10 10-4.477 10-10z"/><path d="M1 10h20"/><path d="M6 10a9 9 0 0112 0"/></svg>
                    Sign in with Google
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const stateSelect = document.getElementById('state_id');
            const lgaWrapper = document.getElementById('lga-wrapper');
            const lgaSelect = document.getElementById('lga_id');
            const lgaData = @json($lgas ? $lgas->groupBy('state_id') : new \Illuminate\Support\Collection());

            stateSelect.addEventListener('change', function () {
                const stateId = this.value;
                lgaSelect.innerHTML = '<option value="">Select LGA</option>';
                if (stateId && lgaData[stateId]) {
                    lgaData[stateId].forEach(function(lga) {
                        const opt = document.createElement('option');
                        opt.value = lga.id;
                        opt.textContent = lga.name;
                        lgaSelect.appendChild(opt);
                    });
                    lgaWrapper.style.display = 'block';
                } else {
                    lgaWrapper.style.display = 'none';
                }
            });
        });
    </script>
</div>
@endsection
