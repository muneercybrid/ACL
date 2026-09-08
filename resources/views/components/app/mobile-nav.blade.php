@props(['items' => []])

{{--
    Mobile slide-over navigation drawer. The `navOpen` state lives on the shell
    root (<div x-data="{ navOpen: false }"> in layouts/app), so the topbar menu
    button and this drawer share it. Hidden at md+ where the sidebar is shown.
--}}
<div x-cloak x-show="navOpen" class="fixed inset-0 z-40 md:hidden" role="dialog" aria-modal="true" aria-label="Navigation">
    <div x-show="navOpen" x-transition.opacity @click="navOpen = false" class="absolute inset-0 bg-black/40"></div>

    <div x-show="navOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
         @keydown.escape.window="navOpen = false"
         class="absolute inset-y-0 left-0 flex w-72 max-w-[80%] flex-col border-r border-border bg-surface">
        <div class="flex h-16 items-center justify-between border-b border-border px-6">
            <x-app.brand />
            <button type="button" @click="navOpen = false" aria-label="Close navigation"
                    class="press inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border text-muted transition hover:border-primary/50 hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring">
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </div>
        <div class="custom-scrollbar flex-1 overflow-y-auto px-3 py-4" @click="navOpen = false">
            <x-app.nav-list :items="$items" />
        </div>
        <div class="border-t border-border p-4">
            <x-app.user-menu />
        </div>
    </div>
</div>
