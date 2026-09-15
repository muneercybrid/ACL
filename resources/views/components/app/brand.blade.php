@props(['version' => 'v0.2'])

<a href="{{ route('dashboard') }}" class="flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-ring rounded-lg">
    <span class="inline-flex h-8 w-8 overflow-hidden rounded-lg bg-primary">
        <img src="{{ asset('images/logo.svg') }}" alt="ACL Logo" class="h-full w-full object-contain">
    </span>
    <span class="text-lg font-extrabold tracking-tight text-text">ACL</span>
    <span class="ml-1 text-[10px] font-medium text-muted">{{ $version }}</span>
</a>
