<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ACL — Anyone Can Learn')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-abyss text-slate-200 font-display antialiased">
<div class="flex min-h-screen">

    <aside class="hidden md:flex w-64 flex-col border-r border-edge bg-surface/60">
        <div class="h-16 px-6 flex items-center gap-2 border-b border-edge">
            <span class="font-mono text-terminal font-semibold">~/</span>
            <span class="font-extrabold text-white text-lg tracking-tight">ACL</span>
            <span class="ml-auto font-mono text-[10px] text-slate-600">v0.1</span>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2 text-white bg-raised border border-edge">
                <span class="font-mono text-terminal">›</span> Dashboard
            </a>
            @foreach ([['My Courses','soon'],['Learning Paths','soon'],['Labs','soon'],['Community','soon'],['Certificates','soon']] as [$label, $tag])
                <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-slate-500 cursor-not-allowed">
                    <span class="font-mono">›</span> {{ $label }}
                    <span class="ml-auto font-mono text-[9px] uppercase border border-edge rounded px-1.5 py-0.5">{{ $tag }}</span>
                </span>
            @endforeach
        </nav>

        <div class="p-4 border-t border-edge">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-lg bg-raised border border-edge flex items-center justify-center font-mono text-terminal text-sm">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-[11px] font-mono text-slate-500 truncate">{{ auth()->user()->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button class="press w-full rounded-lg border border-edge py-1.5 text-xs font-mono text-slate-400 hover:border-brand/60 hover:text-brand transition">
                    ./logout
                </button>
            </form>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">
        <header class="h-16 px-6 flex items-center justify-between border-b border-edge bg-surface/40 backdrop-blur">
            @yield('header')
        </header>
        <div class="flex-1 p-6 lg:p-8 overflow-y-auto">
            @yield('content')
        </div>
    </main>

</div>
</body>
</html>
