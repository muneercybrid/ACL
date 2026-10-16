@extends('superadmin.layout.app')

@section('title', 'ACLi Payment Settings — ACL')

@section('header')
    <div>
        <h1 class="text-xl font-extrabold tracking-tight text-text">ACLi Payment Settings</h1>
        <p class="text-sm text-muted">Configure the paid ACLi AI gate</p>
    </div>
@endsection

@section('content')
<form method="POST" action="{{ route('superadmin.acli.payment.update') }}" class="mx-auto max-w-6xl space-y-6">
    @csrf

    @if (session('success'))
        <div class="rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- Payment Enable/Disable Toggle --}}
    <section class="rounded-2xl border border-border bg-surface shadow-sm">
        <div class="flex items-center justify-between px-6 py-4 border-b border-border">
            <h2 class="text-base font-extrabold text-text">Payment Gate</h2>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $settings->isPaymentEnabled() ? 'bg-success/20 text-success' : 'bg-warning/20 text-warning' }}">
                {{ $settings->isPaymentEnabled() ? 'Enabled' : 'Disabled' }}
            </span>
        </div>
        <div class="p-6">
            <div class="flex items-start justify-between gap-6">
                <div>
                    <h3 class="font-bold text-text">Enable ACLi as a paid feature</h3>
                    <p class="mt-1 text-sm text-muted">
                        When disabled, every student can use ACLi AI features for free. When enabled,
                        a student must hold an active entitlement before the AI answers.
                    </p>
                </div>
                <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                    {{-- The hidden field makes an unchecked box post 0, so
                         turning the gate off is an explicit value rather
                         than a missing key that validation would drop. --}}
                    <input type="hidden" name="payment_enabled" value="0">
                    <input type="checkbox" name="payment_enabled" value="1"
                        @checked($settings->isPaymentEnabled())
                        class="peer sr-only">
                    <span class="h-6 w-11 rounded-full bg-muted transition peer-checked:bg-primary peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/40"></span>
                    <span class="pointer-events-none absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>
                </label>
            </div>
        </div>
    </section>

    {{-- Pricing Configuration --}}
    <section class="rounded-2xl border border-border bg-surface shadow-sm">
        <div class="px-6 py-4 border-b border-border">
            <h2 class="text-base font-extrabold text-text">Pricing Configuration</h2>
        </div>
        <div class="space-y-5 p-6">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <label for="payment_type" class="mb-2 block text-sm font-semibold text-text">Payment type</label>
                    <select id="payment_type" name="payment_type"
                        class="w-full rounded-lg border border-border bg-raised px-3 py-2 text-sm text-text focus:border-primary focus:outline-none">
                        @foreach (['one_time' => 'One-time payment', 'monthly' => 'Monthly subscription', 'yearly' => 'Yearly subscription'] as $value => $label)
                            <option value="{{ $value }}" @selected($settings->payment_type === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="price" class="mb-2 block text-sm font-semibold text-text">Price</label>
                    <input type="number" id="price" name="price" step="0.01" min="0"
                        value="{{ $settings->price }}"
                        class="w-full rounded-lg border border-border bg-raised px-3 py-2 text-sm text-text focus:border-primary focus:outline-none">
                </div>

                <div>
                    <label for="currency" class="mb-2 block text-sm font-semibold text-text">Currency</label>
                    <select id="currency" name="currency"
                        class="w-full rounded-lg border border-border bg-raised px-3 py-2 text-sm text-text focus:border-primary focus:outline-none">
                        @foreach (['NGN', 'USD', 'GBP', 'EUR'] as $code)
                            <option value="{{ $code }}" @selected($settings->currency === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label for="grace_period_days" class="mb-2 block text-sm font-semibold text-text">Payment schedule (grace period)</label>
                    <input type="number" id="grace_period_days" name="grace_period_days" min="0" max="365"
                        value="{{ $settings->grace_period_days }}"
                        class="w-full rounded-lg border border-border bg-raised px-3 py-2 text-sm text-text focus:border-primary focus:outline-none">
                    <p class="mt-1 text-xs text-muted">
                        How long a student keeps access after a payment lapses. 0 disables the grace period.
                    </p>
                </div>

                <div>
                    <label for="description" class="mb-2 block text-sm font-semibold text-text">Admin note</label>
                    <textarea id="description" name="description" rows="3"
                        class="w-full resize-none rounded-lg border border-border bg-raised px-3 py-2 text-sm text-text focus:border-primary focus:outline-none">{{ $settings->description }}</textarea>
                </div>
            </div>
        </div>
    </section>

    {{-- Live preview of what a student is shown --}}
    <section class="rounded-2xl border border-border bg-surface shadow-sm">
        <div class="px-6 py-4 border-b border-border">
            <h2 class="text-base font-extrabold text-text">What the student sees</h2>
        </div>
        <div class="p-6">
            <div id="plan-preview" class="rounded-xl border border-border bg-raised p-5">
                <p class="text-sm font-semibold text-text" id="preview-plan-name">ACLi AI License</p>
                <p class="mt-1 text-2xl font-extrabold text-primary" id="preview-price">NGN 0.00</p>
                <p class="mt-1 text-xs text-muted" id="preview-schedule">One-time payment</p>
            </div>
            <p class="mt-3 text-xs text-muted">
                Saved settings are read at request time by <span class="font-semibold">AcliEntitlementService</span>,
                so this page and the gate cannot disagree.
            </p>
        </div>
    </section>

    <div class="flex justify-end gap-3">
        <a href="{{ route('superadmin.dashboard') }}"
            class="rounded-lg border border-border bg-surface px-5 py-2.5 text-sm font-semibold text-text transition hover:bg-muted">Cancel</a>
        <button type="submit"
            class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary/90">Save settings</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
    (function () {
        var type = document.getElementById('payment_type');
        var price = document.getElementById('price');
        var currency = document.getElementById('currency');
        var name = document.getElementById('preview-plan-name');
        var amount = document.getElementById('preview-price');
        var schedule = document.getElementById('preview-schedule');

        if (!type || !price || !currency) return;

        var plans = {
            one_time: { name: 'ACLi AI License', label: 'one-time payment' },
            monthly: { name: 'ACLi AI Monthly', label: 'per month' },
            yearly:  { name: 'ACLi AI Yearly',  label: 'per year' }
        };

        function render() {
            var plan = plans[type.value] || plans.one_time;
            var value = parseFloat(price.value || '0').toFixed(2);
            name.textContent = plan.name;
            amount.textContent = currency.value + ' ' + value;
            schedule.textContent = plan.label;
        }

        [type, price, currency].forEach(function (el) { el.addEventListener('input', render); });
        render();
    })();
</script>
@endpush
