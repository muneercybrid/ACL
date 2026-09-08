@props(['items' => []])

{{--
    Role-aware navigation list, shared by the desktop sidebar and the mobile drawer.
    Each item: ['label' => string, 'route' => routeName, 'icon' => iconName, 'active' => bool]
    or a not-yet-built destination: ['label' => string, 'icon' => iconName, 'soon' => true].

    NOTE: this list is presentation only. It never decides what a user is *allowed*
    to do — authorisation is enforced server-side in policies. Hiding a link is not
    a security control.
--}}
<nav class="space-y-1 text-sm">
    @foreach ($items as $item)
        @if ($item['soon'] ?? false)
            <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-muted">
                <x-ui.icon :name="$item['icon'] ?? 'squares-2x2'" class="h-5 w-5 flex-shrink-0 opacity-70" />
                <span>{{ $item['label'] }}</span>
                <span class="ml-auto rounded border border-border px-1.5 py-0.5 text-[9px] uppercase tracking-wide">soon</span>
            </span>
        @else
            <a href="{{ route($item['route']) }}"
               @if ($item['active'] ?? false) aria-current="page" @endif
               class="flex items-center gap-3 rounded-lg border px-3 py-2 font-medium transition focus:outline-none focus:ring-2 focus:ring-ring
                      {{ ($item['active'] ?? false)
                          ? 'border-border bg-raised text-text'
                          : 'border-transparent text-muted hover:bg-raised hover:text-text' }}">
                <x-ui.icon :name="$item['icon'] ?? 'squares-2x2'" class="h-5 w-5 flex-shrink-0" />
                <span>{{ $item['label'] }}</span>
            </a>
        @endif
    @endforeach
</nav>
