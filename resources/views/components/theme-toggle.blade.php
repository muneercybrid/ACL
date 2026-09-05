{{-- Light/dark toggle. Persists the choice; when cleared, follows the system. --}}
<button
    type="button"
    x-data="{
        dark: document.documentElement.classList.contains('dark'),
        toggle() {
            this.dark = !this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            localStorage.setItem('acl-theme', this.dark ? 'dark' : 'light');
        }
    }"
    @click="toggle()"
    :aria-pressed="dark.toString()"
    aria-label="Toggle dark mode"
    class="press inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border text-muted transition hover:border-primary/50 hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring"
>
    {{-- Moon (shown in light mode: click to go dark) --}}
    <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>
    </svg>
    {{-- Sun (shown in dark mode: click to go light) --}}
    <svg x-show="dark" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m14.66 6.66-1.42-1.42M6.76 6.76 5.34 5.34m12.32 0-1.42 1.42M6.76 17.24l-1.42 1.42M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/>
    </svg>
</button>
