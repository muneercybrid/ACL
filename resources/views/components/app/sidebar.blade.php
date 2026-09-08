@props(['items' => []])

{{-- Desktop sidebar. Hidden below md; the mobile drawer (<x-app.mobile-nav>) takes over there. --}}
<aside class="hidden w-64 flex-shrink-0 flex-col border-r border-border bg-surface md:flex">
    <div class="flex h-16 items-center border-b border-border px-6">
        <x-app.brand />
    </div>
    <div class="custom-scrollbar flex-1 overflow-y-auto px-3 py-4">
        <x-app.nav-list :items="$items" />
    </div>
    <div class="border-t border-border p-4">
        <x-app.user-menu />
    </div>
</aside>
