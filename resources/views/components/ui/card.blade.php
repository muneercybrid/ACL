@props(['hover' => false])

{{-- Generic surface card. Set :hover to lift the border on hover (for clickable cards). --}}
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-border bg-surface' . ($hover ? ' transition hover:border-primary/50' : '')]) }}>
    {{ $slot }}
</div>
