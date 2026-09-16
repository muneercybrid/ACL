<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ACL Command Center — Superadmin')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
    @include('partials.theme-script')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>x-cloak{display:none!important}</style>
</head>
<body class="bg-bg font-display text-text antialiased">
<div x-data="{ navOpen: false, searchOpen: false }" class="flex min-h-dvh">

    @php
        $navGroups = [
            [
                'heading' => 'Command Center',
                'items' => [
                    ['label' => 'Overview',     'route' => 'superadmin.dashboard', 'icon' => 'squares-2x2', 'active' => request()->routeIs('superadmin.dashboard')],
                    ['label' => 'Alerts',       'route' => 'superadmin.alerts',    'icon' => 'bell',         'active' => request()->routeIs('superadmin.alerts')],
                    ['label' => 'Global Search','route' => 'superadmin.search',     'icon' => 'magnifying-glass', 'active' => request()->routeIs('superadmin.search')],
                ],
            ],
            [
                'heading' => 'Institutions',
                'items' => [
                    ['label' => 'All Institutions',  'route' => 'superadmin.institutions',  'icon' => 'building-library', 'active' => request()->routeIs('superadmin.institutions*')],
                    ['label' => 'Onboarding',        'route' => 'superadmin.onboarding',     'icon' => 'clipboard-check',  'active' => request()->routeIs('superadmin.onboarding*')],
                    ['label' => 'Staff Directory',   'route' => 'superadmin.staff',          'icon' => 'users',            'active' => request()->routeIs('superadmin.staff*')],
                ],
            ],
            [
                'heading' => 'Users',
                'items' => [
                    ['label' => 'All Users',        'route' => 'superadmin.users',   'icon' => 'identification', 'active' => request()->routeIs('superadmin.users*')],
                ],
            ],
            [
                'heading' => 'Academic',
                'items' => [
                    ['label' => 'Academic Explorer', 'route' => 'superadmin.academic',   'icon' => 'academic-cap',  'active' => request()->routeIs('superadmin.academic*')],
                    ['label' => 'Roles & Permissions','route' => 'superadmin.roles',    'icon' => 'key',           'active' => request()->routeIs('superadmin.roles*')],
                ],
            ],
            [
                'heading' => 'Learning',
                'items' => [
                    ['label' => 'AI Activity',     'route' => 'superadmin.ai',       'icon' => 'sparkles',  'active' => request()->routeIs('superadmin.ai*')],
                ],
            ],
            [
                'heading' => 'Verification',
                'items' => [
                    ['label' => 'JAMB Monitoring', 'route' => 'superadmin.jamb',     'icon' => 'shield-check', 'active' => request()->routeIs('superadmin.jamb*')],
                    ['label' => 'Registrations',   'route' => 'superadmin.registrations', 'icon' => 'document-text', 'active' => request()->routeIs('superadmin.registrations*')],
                ],
            ],
            [
                'heading' => 'Security',
                'items' => [
                    ['label' => 'Auth & Security', 'route' => 'superadmin.security', 'icon' => 'lock-closed',   'active' => request()->routeIs('superadmin.security*')],
                    ['label' => 'Audit Logs',      'route' => 'superadmin.audit',    'icon' => 'document-magnifying-glass', 'active' => request()->routeIs('superadmin.audit*')],
                ],
            ],
            [
                'heading' => 'System',
                'items' => [
                    ['label' => 'Reports',    'route' => 'superadmin.reports',  'icon' => 'chart-bar',      'active' => request()->routeIs('superadmin.reports*')],
                    ['label' => 'System Health','route' => 'superadmin.system',  'icon' => 'wrench-screwdriver','active' => request()->routeIs('superadmin.system*')],
                ],
            ],
        ];
    @endphp

    {{-- Desktop sidebar --}}
    <aside class="hidden w-64 flex-shrink-0 flex-col border-r border-border bg-surface md:flex">
        <div class="flex h-16 items-center border-b border-border px-5">
            <a href="{{ route('superadmin.dashboard') }}" class="flex items-center gap-2.5">
                <img src="{{ asset('images/logo.svg') }}" alt="ACL" class="h-8 w-8 rounded-lg object-contain bg-primary/10 p-1 filter brightness-0 invert">
                <div class="min-w-0">
                    <span class="block text-sm font-bold text-text truncate">ACL Command Center</span>
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-primary">Superadmin</span>
                </div>
            </a>
        </div>
        <div class="custom-scrollbar flex-1 overflow-y-auto px-3 py-4 space-y-5">
            @foreach ($navGroups as $group)
                <div>
                    <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-widest text-muted">{{ $group['heading'] }}</p>
                    <nav class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            <a href="{{ route($item['route']) }}"
                               @if ($item['active']) aria-current="page" @endif
                               class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-ring
                                      {{ $item['active']
                                          ? 'bg-primary/10 text-primary'
                                          : 'text-muted hover:bg-raised hover:text-text' }}">
                                <x-ui.icon :name="$item['icon']" class="h-4 w-4 flex-shrink-0" />
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </nav>
                </div>
            @endforeach
        </div>
        <div class="border-t border-border p-4">
            <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-primary-fg text-xs font-bold">
                    {{ strtoupper(substr(auth()->user()->name ?? 'S', 0, 2)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-text">{{ auth()->user()->name ?? 'Superadmin' }}</p>
                    <p class="truncate text-xs text-muted">Platform Admin</p>
                </div>
            </div>
        </div>
    </aside>

    {{-- Mobile nav drawer --}}
    <div x-show="navOpen" x-transition:enter="transition-opacity ease-out duration-200" x-transition:leave="transition-opacity ease-in duration-150"
         class="fixed inset-0 z-50 bg-black/50 md:hidden" @click.self="navOpen = false" style="display: none">
        <aside class="absolute left-0 top-0 bottom-0 w-72 overflow-y-auto bg-surface border-r border-border" @click.away="navOpen = false">
            <div class="flex h-16 items-center border-b border-border px-5">
                <span class="text-sm font-bold text-text">Command Center</span>
            </div>
            <div class="px-3 py-4 space-y-5">
                @foreach ($navGroups as $group)
                    <div>
                        <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-widest text-muted">{{ $group['heading'] }}</p>
                        <nav class="space-y-0.5">
                            @foreach ($group['items'] as $item)
                                <a href="{{ route($item['route']) }}"
                                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
                                          {{ $item['active'] ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-raised hover:text-text' }}">
                                    <x-ui.icon :name="$item['icon']" class="h-4 w-4 flex-shrink-0" />
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </nav>
                    </div>
                @endforeach
            </div>
        </aside>
    </div>

    {{-- Main content --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-16 items-center justify-between gap-4 border-b border-border bg-surface/70 px-4 backdrop-blur sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" @click="navOpen = true" aria-label="Open navigation"
                        class="press inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg border border-border text-muted transition hover:border-primary/50 hover:text-primary focus:outline-none focus:ring-2 focus:ring-ring md:hidden">
                    <x-ui.icon name="bars-3" class="h-5 w-5" />
                </button>

                {{-- Global search bar --}}
                <form action="{{ route('superadmin.search') }}" method="GET" class="hidden sm:block">
                    <div class="relative">
                        <x-ui.icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted" />
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search users, institutions, events…"
                               class="w-72 rounded-xl border border-border bg-raised py-2 pl-9 pr-4 text-sm text-text placeholder-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                               autocomplete="off">
                    </div>
                </form>

                <div class="min-w-0">@yield('header')</div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('superadmin.dashboard') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition hover:bg-primary/20">
                    <x-ui.icon name="command-bracket" class="h-3.5 w-3.5" />
                    Command Center
                </a>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-raised px-3 py-1.5 text-xs font-semibold text-muted transition hover:bg-raised/80 hover:text-text">
                    Student View
                </a>
                <x-theme-toggle />
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            @if (session('success'))
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="border-t border-border px-6 py-4 text-xs text-muted">
            &copy; {{ now()->year }} ACL — Anyone Can Learn · Command Center
        </footer>
    </div>
</div>
@stack('scripts')
</body>
</html>