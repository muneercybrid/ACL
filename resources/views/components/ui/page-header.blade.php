@props(['title', 'subtitle' => null])

{{-- Page title block for the topbar. Pass an <x-slot:actions> for page-level buttons. --}}
<div class="flex items-center justify-between gap-4">
    <div class="min-w-0">
        <h1 class="truncate text-lg font-bold text-text">{{ $title }}</h1>
        @if ($subtitle)
            <p class="truncate text-xs text-muted">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-shrink-0 items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
