{{-- Public marketing header. Used by the landing/marketing layout. --}}
<header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-border bg-bg/90 backdrop-blur">
    <div class="mx-auto flex min-h-16 max-w-6xl items-center gap-4 px-4 sm:px-6">
        <a href="{{ url('/') }}" class="flex shrink-0 items-center gap-2" aria-label="ACL home">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary font-extrabold text-primary-fg">A</span>
            <span class="text-lg font-extrabold tracking-tight text-text">ACL</span>
            <span class="hidden text-xs font-medium text-muted sm:inline">Anyone Can Learn</span>
        </a>

        <nav class="ml-auto hidden items-center gap-6 text-sm font-medium text-muted md:flex" aria-label="Public navigation">
            <a href="#courses" class="transition hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring">Learn</a>
            <a href="#how" class="transition hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring">Teach</a>
            <a href="#universities" class="transition hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring">Universities</a>
            <a href="#what-is-acl" class="transition hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring">About</a>
        </nav>

        <div class="ml-auto flex items-center gap-2 md:ml-0">
            <x-theme-toggle />
            <a href="{{ route('login') }}" class="hidden rounded-lg px-3 py-2 text-sm font-semibold text-text transition hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring sm:inline-flex">
                Sign in
            </a>
            <a href="{{ route('login') }}" class="press hidden rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-fg transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring sm:inline-flex">Get started</a>
            <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="public-mobile-nav" aria-label="Toggle navigation" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-border text-text transition hover:border-primary hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring md:hidden">
                <x-ui.icon name="bars-3" x-show="!open" class="h-5 w-5" />
                <x-ui.icon name="x-mark" x-show="open" x-cloak class="h-5 w-5" />
            </button>
        </div>
    </div>

    <div id="public-mobile-nav" x-show="open" x-cloak @click.outside="open = false" class="border-t border-border bg-surface px-4 py-4 md:hidden">
        <nav class="mx-auto flex max-w-6xl flex-col gap-1 text-sm font-semibold text-text" aria-label="Mobile public navigation">
            <a @click="open = false" href="#courses" class="rounded-lg px-3 py-3 hover:bg-raised hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring">Learn</a>
            <a @click="open = false" href="#how" class="rounded-lg px-3 py-3 hover:bg-raised hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring">Teach</a>
            <a @click="open = false" href="#universities" class="rounded-lg px-3 py-3 hover:bg-raised hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring">Universities</a>
            <a @click="open = false" href="#what-is-acl" class="rounded-lg px-3 py-3 hover:bg-raised hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring">About</a>
            <div class="mt-2 flex gap-2 border-t border-border pt-3 sm:hidden">
                <a href="{{ route('login') }}" class="flex-1 rounded-lg border border-border px-3 py-3 text-center hover:border-primary hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring">Sign in</a>
                <a href="{{ route('login') }}" class="press flex-1 rounded-lg bg-primary px-3 py-3 text-center text-primary-fg hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring">Get started</a>
            </div>
        </nav>
    </div>
</header>
