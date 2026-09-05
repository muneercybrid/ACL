{{-- Public marketing header. Used by the landing/marketing layout. --}}
<header class="sticky top-0 z-40 border-b border-border bg-bg/80 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4 sm:px-6">
        <a href="{{ url('/') }}" class="flex items-center gap-2">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary font-extrabold text-primary-fg">A</span>
            <span class="text-lg font-extrabold tracking-tight text-text">ACL</span>
            <span class="hidden text-xs font-medium text-muted sm:inline">Anyone Can Learn</span>
        </a>

        <nav class="ml-auto hidden items-center gap-6 text-sm font-medium text-muted md:flex">
            <a href="#courses" class="transition hover:text-primary">Courses</a>
            <a href="#vision" class="transition hover:text-primary">Vision</a>
            <a href="#how" class="transition hover:text-primary">How it works</a>
        </nav>

        <div class="ml-auto flex items-center gap-2 md:ml-0">
            <x-theme-toggle />
            <a href="{{ route('login') }}"
               class="press rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-fg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring">
                Login
            </a>
        </div>
    </div>
</header>
