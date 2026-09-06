@props(['label', 'value', 'icon' => null, 'hint' => null, 'tone' => 'text'])

@php
    // Value colour by semantic tone — tokens only, never a raw hex.
    $toneClass = [
        'text'    => 'text-text',
        'primary' => 'text-primary',
        'info'    => 'text-info',
        'accent'  => 'text-accent',
        'muted'   => 'text-muted',
    ][$tone] ?? 'text-text';
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-border bg-surface p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-medium uppercase tracking-wide text-muted">{{ $label }}</p>
        @if ($icon)
            <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-raised text-primary">
                <x-ui.icon :name="$icon" class="h-4 w-4" />
            </span>
        @endif
    </div>
    <p class="mt-1 text-3xl font-extrabold {{ $toneClass }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
    @endif
</div>
