{{-- Signed-in user block + logout, shared by the sidebar and the mobile drawer. --}}
<div class="flex items-center gap-3">
    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg border border-border bg-raised font-semibold text-primary">
        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
    </div>
    <div class="min-w-0">
        <p class="truncate text-sm font-semibold text-text">{{ auth()->user()->name }}</p>
        <p class="truncate text-[11px] text-muted">{{ auth()->user()->email }}</p>
    </div>
</div>
<form method="POST" action="{{ route('logout') }}" class="mt-3">
    @csrf
    <button class="press flex w-full items-center justify-center gap-2 rounded-lg border border-border py-1.5 text-xs font-medium text-muted transition hover:border-primary/50 hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring">
        <x-ui.icon name="arrow-right-on-rectangle" class="h-4 w-4" />
        Log out
    </button>
</form>
