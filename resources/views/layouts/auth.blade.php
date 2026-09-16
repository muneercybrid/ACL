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
<body class="min-h-dvh bg-bg font-display text-text antialiased">
    <div class="flex min-h-dvh flex-col">
        <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-5 sm:px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-2">
                <span class="inline-flex h-8 w-8 overflow-hidden rounded-lg bg-primary">
                    <img src="{{ asset('images/logo.svg') }}" alt="ACL Logo" class="h-full w-full object-contain">
                </span>
                <span class="text-lg font-extrabold tracking-tight text-text">ACL</span>
            </a>
            <x-theme-toggle />
        </div>
        <div class="relative flex flex-1 items-center justify-center px-4 pb-16">
            <div class="pointer-events-none absolute inset-0 -z-10"
                 style="background: radial-gradient(55% 45% at 50% 0%, color-mix(in srgb, var(--color-primary) 12%, transparent), transparent 70%);"></div>
            @yield('content')
        </div>

        {{-- ACLi Floating AI Chat Button --}}
        <div x-data="acliFloat()">
            <button
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
            </button>
            <div x-show="showAcli" @click.away="showAcli = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="fixed bottom-24 right-6 z-50 w-80 overflow-hidden rounded-2xl border border-border bg-surface shadow-2xl" x-cloak>
                <div class="flex items-center justify-between bg-gradient-to-r from-primary to-emerald-500 px-4 py-3 text-white">
                    <div class="flex items-center gap-2">
                        <img src="/images/logo.svg" class="h-6 w-6 filter brightness-0 invert" alt="ACL">
                        <span class="font-bold text-sm">ACLi</span>
                    </div>
                    <button @click="showAcli = false" class="rounded p-1 hover:bg-white/20">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-4">
                    <p class="text-sm text-text">Select text on any page to explain it with ACLi AI.</p>
                    <a href="{{ route('login') }}" class="mt-3 block rounded-lg bg-primary px-4 py-2 text-center text-sm font-semibold text-primary-fg transition hover:opacity-90">
                        Sign in to chat
                    </a>
                </div>
            </div>
        </div>
        <script>
            function acliFloat() {
                return {
                    showAcli: false,
                    init() {
                        document.addEventListener('selectionchange', () => {
                            const text = window.getSelection().toString().trim();
                            if (text.length > 3) {
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
    </div>
</body>
</html>
