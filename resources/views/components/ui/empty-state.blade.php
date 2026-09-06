@props(['icon' => 'book-open', 'title', 'message' => null])

{{-- Empty/zero-data state. Pass an <x-slot:action> for a primary CTA. --}}
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-border bg-surface p-10 text-center']) }}>
    <span class="mx-auto mb-4 inline-flex h-12 w-12 items-center justify-center rounded-full border border-border bg-raised text-muted">
        <x-ui.icon :name="$icon" class="h-6 w-6" />
    </span>
    <p class="font-semibold text-text">{{ $title }}</p>
    @if ($message)
        <p class="mx-auto mt-2 max-w-md text-sm text-muted">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-5 flex justify-center">{{ $action }}</div>
    @endisset
</div>
