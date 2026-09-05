<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ACL — Anyone Can Learn')</title>
    @include('partials.theme-script')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-bg font-display text-text antialiased">
<div class="flex min-h-dvh">

    <aside class="hidden w-64 flex-col border-r border-border bg-surface md:flex">
        <div class="flex h-16 items-center gap-2 border-b border-border px-6">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary font-extrabold text-primary-fg">A</span>
            <span class="text-lg font-extrabold tracking-tight text-text">ACL</span>
            <span class="ml-auto text-[10px] font-medium text-muted">v0.1</span>
        </div>

        <nav class="flex-1 space-y-1 px-3 py-4 text-sm">
            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 rounded-lg border border-border bg-raised px-3 py-2 font-medium text-text">
                Dashboard
            </a>
            @foreach (['My Courses', 'Learning Paths', 'Certificates', 'Community'] as $label)
                <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-muted">
                    {{ $label }}
                    <span class="ml-auto rounded border border-border px-1.5 py-0.5 text-[9px] uppercase tracking-wide">soon</span>
                </span>
            @endforeach
        </nav>

        <div class="border-t border-border p-4">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-raised font-semibold text-primary">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-text">{{ auth()->user()->name }}</p>
                    <p class="truncate text-[11px] text-muted">{{ auth()->user()->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button class="press w-full rounded-lg border border-border py-1.5 text-xs font-medium text-muted transition hover:border-primary/50 hover:text-primary">
                    Log out
                </button>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-16 items-center justify-between gap-4 border-b border-border bg-surface/70 px-6 backdrop-blur">
            <div class="min-w-0">@yield('header')</div>
            <div class="flex items-center gap-2">
                <x-theme-toggle />
            </div>
        </header>
        <main class="flex-1 overflow-y-auto p-6 lg:p-8">
            @yield('content')
        </main>
        <footer class="border-t border-border px-6 py-4 text-xs text-muted">
            &copy; {{ now()->year }} ACL — Anyone Can Learn.
        </footer>
    </div>

</div>
</body>
</html>
