@extends('layouts.app')
@section('title', 'Contact — ACL')
@section('content')
<div class="mx-auto max-w-2xl py-10">

    <h1 class="text-2xl font-extrabold text-text">Contact us</h1>
    <p class="mt-1 text-sm text-muted">
        Send us a message and we will reply to the address you provide.
    </p>

    {{-- The address is shown but not relied on. Until the MX record is
         corrected, mail sent to it is accepted by the sender and then
         discarded, so a visitor who uses the address instead of this form
         would get no reply at all. --}}
    <div class="mt-4 rounded-lg border border-border bg-surface px-4 py-3 text-sm text-muted">
        You can also write to
        <span class="font-semibold text-text">{{ $supportAddress }}</span>,
        though the form below is the surest way to reach us right now.
    </div>

    <form method="POST" action="{{ route('contact.store') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-sm font-semibold text-text">Your name</label>
            <input id="name" name="name" type="text" required maxlength="120" autocomplete="name"
                   value="{{ old('name') }}"
                   class="mt-2 w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20">
        </div>

        <div>
            <label for="email" class="block text-sm font-semibold text-text">Your email</label>
            <input id="email" name="email" type="email" required maxlength="190" autocomplete="email"
                   value="{{ old('email') }}"
                   class="mt-2 w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20">
            <p class="mt-1 text-xs text-muted">We reply to this address.</p>
        </div>

        <div>
            <label for="subject" class="block text-sm font-semibold text-text">Subject</label>
            <input id="subject" name="subject" type="text" required maxlength="150"
                   value="{{ old('subject') }}"
                   class="mt-2 w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20">
        </div>

        <div>
            <label for="message" class="block text-sm font-semibold text-text">Message</label>
            <textarea id="message" name="message" rows="6" required maxlength="5000"
                      class="mt-2 w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20">{{ old('message') }}</textarea>
        </div>

        {{-- Honeypot. Hidden from people, filled in by bots. The rule caps the
             length at 0, so a filled field is rejected outright. --}}
        <div class="hidden" aria-hidden="true">
            <label for="website">Leave this field empty</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit"
                class="rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:ring-2 focus:ring-ring">
            Send message
        </button>
    </form>
</div>
@endsection
