@extends('layouts.public')

@section('title', 'ACL — Anyone Can Learn')

@section('content')

{{-- Hero --}}
<section class="relative overflow-hidden">
    <div class="pointer-events-none absolute inset-0 -z-10"
         style="background:
            radial-gradient(60% 50% at 15% 0%, color-mix(in srgb, var(--color-primary) 14%, transparent), transparent 70%),
            radial-gradient(50% 40% at 100% 10%, color-mix(in srgb, var(--color-info) 12%, transparent), transparent 70%);">
    </div>
    <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:py-24">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full border border-border bg-surface px-3 py-1 text-xs font-medium text-muted">
                <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                Open learning for Nigerian institutions
            </span>
            <h1 class="mt-5 text-4xl font-extrabold leading-tight tracking-tight text-text sm:text-5xl">
                Anyone can learn.<br>
                <span class="text-primary">Your courses follow your enrolment.</span>
            </h1>
            <p class="mt-5 max-w-xl text-lg text-muted">
                Sign in and every course your programme, level and semester entitle you to is already waiting — no purchase, no hunting. Learn at your pace and track every lesson.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="{{ route('login') }}"
                   class="press rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
                    Login to your dashboard
                </a>
                <a href="#courses"
                   class="press rounded-lg border border-border bg-surface px-6 py-3 text-sm font-semibold text-text transition hover:border-primary/50 focus:outline-none focus:ring-2 focus:ring-ring">
                    Explore courses
                </a>
            </div>
            <p class="mt-4 text-xs text-muted">Federal, state &amp; private universities, polytechnics and colleges of education.</p>
        </div>
        {{-- Illustration. Swap for a real photo: drop public/images/hero-students.jpg and use <img> here. --}}
        <div class="relative">
            <div class="mx-auto aspect-[4/3] w-full max-w-md rounded-2xl border border-border bg-surface p-6 shadow-sm">
                <svg viewBox="0 0 400 300" class="h-full w-full" role="img" aria-label="Students learning together">
                    <rect width="400" height="300" rx="16" fill="var(--color-raised)"/>
                    <circle cx="200" cy="120" r="70" fill="color-mix(in srgb, var(--color-primary) 18%, transparent)"/>
                    <path d="M120 120 L200 92 L280 120 L200 148 Z" fill="var(--color-primary)"/>
                    <path d="M160 134 L160 168 Q200 190 240 168 L240 134" fill="none" stroke="var(--color-accent)" stroke-width="6"/>
                    <line x1="280" y1="120" x2="280" y2="160" stroke="var(--color-accent)" stroke-width="4"/>
                    <circle cx="280" cy="164" r="6" fill="var(--color-info)"/>
                    <rect x="120" y="210" width="160" height="16" rx="3" fill="var(--color-primary)"/>
                    <rect x="132" y="226" width="136" height="14" rx="3" fill="var(--color-info)"/>
                    <rect x="144" y="240" width="112" height="12" rx="3" fill="var(--color-accent)"/>
                </svg>
            </div>
        </div>
    </div>
</section>

{{-- How it works --}}
<section id="how" class="border-t border-border bg-surface/40">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-center text-2xl font-bold tracking-tight text-text sm:text-3xl">How ACL works</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-muted">Access is derived from your institutional record — not sold.</p>
        <div class="mt-10 grid gap-6 sm:grid-cols-3">
            @foreach ([
                ['1', 'Enrol at your institution', 'Your department registers you by programme, level and semester — the way it already does.'],
                ['2', 'Your courses appear', 'ACL derives your entitlements from that record. The right courses are simply there when you sign in.'],
                ['3', 'Learn and track', 'Work through lessons at your pace; ACL remembers what you have completed.'],
            ] as [$n, $t, $d])
                <div class="reveal rounded-2xl border border-border bg-surface p-6" style="transition-delay: {{ $loop->index * 90 }}ms">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 font-bold text-primary">{{ $n }}</span>
                    <h3 class="mt-4 font-semibold text-text">{{ $t }}</h3>
                    <p class="mt-2 text-sm text-muted">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
{{-- Vision & Mission (animated cards) --}}
<section id="vision" class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
    <div class="grid gap-6 md:grid-cols-2">
        <article class="reveal group relative overflow-hidden rounded-2xl border border-border bg-surface p-8">
            <div class="absolute right-0 top-0 h-24 w-24 -translate-y-8 translate-x-8 rounded-full bg-primary/10 transition group-hover:scale-125"></div>
            <span class="text-xs font-semibold uppercase tracking-widest text-primary">Our Vision</span>
            <h3 class="mt-3 text-xl font-bold text-text">Learning that belongs to everyone</h3>
            <p class="mt-3 text-muted">A Nigeria where every enrolled student — in any institution, in any town — opens their courses the moment they belong to a programme, with nothing between them and the material.</p>
        </article>
        <article class="reveal group relative overflow-hidden rounded-2xl border border-border bg-surface p-8" style="transition-delay: 120ms">
            <div class="absolute right-0 top-0 h-24 w-24 -translate-y-8 translate-x-8 rounded-full bg-info/10 transition group-hover:scale-125"></div>
            <span class="text-xs font-semibold uppercase tracking-widest text-info">Our Mission</span>
            <h3 class="mt-3 text-xl font-bold text-text">Tie access to enrolment, not to money</h3>
            <p class="mt-3 text-muted">To build open, institution-aware learning infrastructure that derives a student's courses from their real academic record — and to keep it free, transparent and owned by the community it serves.</p>
        </article>
    </div>
</section>

{{-- Courses teaser --}}
<section id="courses" class="border-t border-border bg-surface/40">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-text sm:text-3xl">Built for every faculty</h2>
                <p class="mt-2 text-muted">Not just computing — the sciences, arts, management, engineering, health and more.</p>
            </div>
            <a href="{{ route('login') }}" class="hidden shrink-0 text-sm font-semibold text-primary hover:underline sm:inline">Sign in to view yours &rarr;</a>
        </div>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (['Sciences', 'Arts & Humanities', 'Management', 'Engineering', 'Health Sciences', 'Education', 'Law', 'Agriculture'] as $i => $cat)
                <div class="reveal rounded-xl border border-border bg-surface p-5" style="transition-delay: {{ $i * 60 }}ms">
                    <div class="h-1.5 w-10 rounded-full bg-primary"></div>
                    <p class="mt-4 font-semibold text-text">{{ $cat }}</p>
                    <p class="mt-1 text-xs text-muted">Courses by semester &amp; level</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
{{-- Closing CTA --}}
<section class="mx-auto max-w-6xl px-4 py-20 text-center sm:px-6">
    <h2 class="text-3xl font-extrabold tracking-tight text-text">Ready when you are.</h2>
    <p class="mx-auto mt-3 max-w-xl text-muted">Sign in with the details your institution issued and pick up where your programme left off.</p>
    <a href="{{ route('login') }}"
       class="press mt-8 inline-block rounded-lg bg-primary px-8 py-3 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
        Login
    </a>
</section>

{{-- Reveal-on-scroll (progressive enhancement). .js hid the cards; reveal on view. --}}
<script>
    (function () {
        var els = document.querySelectorAll('.reveal');
        if (!('IntersectionObserver' in window) || !els.length) {
            els.forEach(function (el) { el.classList.add('is-in'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) { entry.target.classList.add('is-in'); io.unobserve(entry.target); }
            });
        }, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 });
        els.forEach(function (el) { io.observe(el); });
    })();
</script>
@endsection
