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
<body class="min-h-dvh bg-bg font-display text-text antialiased">
    <div class="flex min-h-dvh flex-col">
        <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-5 sm:px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-2">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary font-extrabold text-primary-fg">A</span>
                <span class="text-lg font-extrabold tracking-tight text-text">ACL</span>
            </a>
            <x-theme-toggle />
        </div>
        <div class="relative flex flex-1 items-center justify-center px-4 pb-16">
            <div class="pointer-events-none absolute inset-0 -z-10"
                 style="background: radial-gradient(55% 45% at 50% 0%, color-mix(in srgb, var(--color-primary) 12%, transparent), transparent 70%);"></div>
            @yield('content')
        </div>
    </div>
</body>
</html>
