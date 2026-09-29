<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ACL — Anyone Can Learn')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.svg') }}">
    @include('partials.theme-script')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>x-cloak{display:none!important}</style>
</head>
<body class="bg-bg font-display text-text antialiased">
<div x-data="{ navOpen: false }" class="flex min-h-dvh">

    @php
        $nav = [
            ['label' => 'Dashboard',      'route' => 'dashboard',   'icon' => 'squares-2x2', 'active' => request()->routeIs('dashboard')],
            ['label' => 'My Courses',     'route' => 'student.my-courses', 'icon' => 'book-open', 'active' => request()->routeIs('student.my-courses')],
            ['label' => 'Profile',        'route' => 'student.profile', 'icon' => 'user', 'active' => request()->routeIs('student.profile')],
            ['label' => 'ACLi',           'route' => 'acli.chat',   'icon' => 'sparkles', 'active' => request()->routeIs('acli.*')],
        ];
    @endphp

    <x-app.sidebar :items="$nav" />
    <x-app.mobile-nav :items="$nav" />

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-16 items-center justify-between gap-4 border-b border-border bg-surface/70 px-4 backdrop-blur sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" @click="navOpen = true" aria-label="Open navigation"
                        class="press inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg border border-border text-muted transition hover:border-primary/50 hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring md:hidden">
                    <x-ui.icon name="bars-3" class="h-5 w-5" />
                </button>
                <div class="min-w-0">@yield('header')</div>
            </div>
            <div class="flex flex-shrink-0 items-center gap-2">
                <x-theme-toggle />
            </div>
        </header>
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            {{-- Session messages.
                 These were not rendered anywhere in this layout, so every
                 with('success') in a controller was silently discarded: the
                 student was redirected to their dashboard and told nothing, not
                 even when the verification email had failed to send. The
                 warning level matters most -- it is how a partial failure is
                 reported without claiming the whole thing succeeded. --}}
            @foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger', 'status' => 'info'] as $key => $style)
                @if (session($key))
                    <div class="mb-4 rounded-lg border border-{{ $style }} bg-{{ $style }}-bg px-4 py-3 text-sm text-{{ $style }}">{{ session($key) }}</div>
                @endif
            @endforeach

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-danger bg-danger-bg px-4 py-3 text-sm text-danger">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
        <footer class="border-t border-border px-6 py-4 text-xs text-muted">
            &copy; {{ now()->year }} ACL — Anyone Can Learn.
        </footer>
    </div>

</div>
@stack('scripts')
</body>
</html>
