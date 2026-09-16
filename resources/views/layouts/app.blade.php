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
        // Slice A: one shared nav. Slice B replaces this with a per-role resolver.
        // Only real, existing routes are linked; everything else is honestly marked "soon".
        $nav = [
            ['label' => 'Dashboard',      'route' => 'dashboard', 'icon' => 'squares-2x2', 'active' => request()->routeIs('dashboard')],
            ['label' => 'My Courses',     'icon' => 'book-open',     'soon' => true],
            ['label' => 'Learning Paths', 'icon' => 'academic-cap',  'soon' => true],
            ['label' => 'Certificates',   'icon' => 'rectangle-stack','soon' => true],
            ['label' => 'Community',      'icon' => 'user-group',    'soon' => true],
        ];
    @endphp

    <x-app.sidebar :items="$nav" />
    <x-app.mobile-nav :items="$nav" />

    {{-- ACLi Floating AI Chat Button --}}
    <div x-data="acliFloat()">
        <button
            id="acli-float-btn"
            @click="showAcli = true"
            class="press fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-primary to-emerald-500 text-white shadow-lg shadow-primary/30 transition hover:scale-110 hover:shadow-xl"
            title="Open ACLi AI Assistant"
            aria-label="Open ACLi AI Assistant"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="5"/>
                <line x1="12" y1="1" x2="12" y2="3"/>
                <line x1="12" y1="21" x2="12" y2="23"/>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                <line x1="1" y1="12" x2="3" y2="12"/>
                <line x1="21" y1="12" x2="23" y2="12"/>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
            </svg>
            <span class="absolute -right-1 -top-1 flex h-3.5 w-3.5">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                <span class="relative inline-flex h-3.5 w-3.5 rounded-full bg-emerald-500"></span>
            </span>
        </button>

        {{-- Highlight tooltip --}}
        <div x-show="showAcli" @click.away="showAcli = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="fixed bottom-24 right-6 z-50 w-80 overflow-hidden rounded-2xl border border-border bg-surface shadow-2xl" x-cloak>
            <div class="flex items-center justify-between bg-gradient-to-r from-primary to-emerald-500 px-4 py-3 text-white">
                <div class="flex items-center gap-2">
                    <img src="/images/logo.svg" class="h-6 w-6 filter brightness-0 invert" alt="ACL">
                    <span class="font-bold text-sm">ACLi</span>
                </div>
                <button @click="showAcli = false; highlightMode = false" class="rounded p-1 hover:bg-white/20">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-4">
                <template x-if="!highlightMode">
                    <div x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <p class="text-sm text-text">Select text on any page, then click here to explain it.</p>
                        <div class="mt-3 flex gap-2">
                            <button @click="highlightMode = true" class="flex-1 rounded-lg border border-border bg-bg px-3 py-2 text-xs font-semibold text-text transition hover:border-primary/30">
                                ✏️ Highlight & Explain
                            </button>
                            <a href="{{ route('acli.chat') }}" class="flex-1 rounded-lg bg-primary px-3 py-2 text-center text-xs font-semibold text-primary-fg transition hover:opacity-90">
                                💬 Chat
                            </a>
                        </div>
                    </div>
                </template>
                <template x-if="highlightMode">
                    <div x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <p class="mb-2 text-sm font-semibold text-text">Select text to explain</p>
                        <div class="rounded-lg border border-dashed border-border bg-bg p-3 text-xs text-muted" x-show="selectedText.length === 0" x-text="'No text selected yet'"></div>
                        <div class="rounded-lg border border-border bg-bg p-3 text-xs text-primary" x-show="selectedText.length > 0" x-text="selectedText.length > 100 ? selectedText.substring(0, 100) + '...' : selectedText"></div>
                        <div class="mt-3 flex gap-2">
                            <button @click="submitHighlight()" class="flex-1 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-primary-fg transition hover:opacity-90">
                                Explain Selected
                            </button>
                            <button @click="highlightMode = false" class="rounded-lg border border-border bg-bg px-3 py-2 text-xs font-semibold text-text transition hover:bg-raised">
                                Back
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ACLi Floating AI JS --}}
    <script>
        function acliFloat() {
            return {
                showAcli: false,
                highlightMode: false,
                selectedText: '',
                init() {
                    document.addEventListener('selectionchange', () => {
                        const text = window.getSelection().toString().trim();
                        if (text.length > 3 && !this.highlightMode) {
                            this.selectedText = text;
                        }
                    });
                },
                submitHighlight() {
                    if (!this.selectedText) return;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route('acli.chat.send') }}';
                    form.style.display = 'none';
                    const csrf = '{{ csrf_token() }}';
                    const msg = document.createElement('input');
                    msg.type = 'hidden'; msg.name = 'message'; msg.value = 'Explain this: ' + this.selectedText;
                    form.appendChild(msg);
                    const ctx = document.createElement('input');
                    ctx.type = 'hidden'; ctx.name = 'context[selected_text]'; ctx.value = this.selectedText;
                    form.appendChild(ctx);
                    const tok = document.createElement('input');
                    tok.type = 'hidden'; tok.name = '_token'; tok.value = csrf;
                    form.appendChild(tok);
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }
    </script>

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
            @yield('content')
        </main>
        <footer class="border-t border-border px-6 py-4 text-xs text-muted">
            &copy; {{ now()->year }} ACL — Anyone Can Learn.
        </footer>
    </div>

</div>
</body>
</html>
