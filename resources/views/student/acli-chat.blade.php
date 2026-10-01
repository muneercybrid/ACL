@extends('layouts.app')

@section('title', 'ACLi — AI Assistant')

@section('content')
{{-- Capped rather than full-viewport. A chat pinned to 100dvh pushes the page
     header off and leaves the transcript running to the bottom of the screen
     with no end in sight. The box stays a box. --}}
<div class="mx-auto flex h-[calc(100dvh-9rem)] max-h-[42rem] min-h-[26rem] max-w-6xl flex-col px-4 sm:px-6">
    <!-- Header -->
    <div class="mb-4 flex items-center justify-between border-b border-border py-4 shrink-0">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-emerald-500 shadow-md overflow-hidden">
                <img src="{{ asset('images/logo.svg') }}" alt="ACL" class="h-6 w-6 object-contain filter brightness-0 invert">
            </div>
            <div>
                <h1 class="text-lg font-extrabold text-text">ACLi</h1>
                <p class="text-xs text-muted">AI assistant for ACL — Anyone Can Learn</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" id="conv-toggle" aria-label="Show previous chats" aria-expanded="false"
                    class="rounded-lg border border-border bg-raised p-2 text-text transition hover:bg-bg sm:hidden">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
            </button>
            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700 uppercase tracking-wide">Online</span>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700 shrink-0">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="relative flex flex-1 gap-4 overflow-hidden rounded-2xl border border-border bg-surface shadow-sm min-h-0">
        <!-- Sidebar -->
        {{-- Reachable on a phone. Previously `hidden sm:flex`, which meant no history
     list at all on the device most students actually use. It slides in from
     the left on small screens and is a permanent column from sm up. --}}
        <aside id="conv-sidebar"
               class="absolute inset-y-0 left-0 z-20 w-72 max-w-[85vw] shrink-0 -translate-x-full flex-col border-r border-border bg-surface transition-transform duration-200 sm:static sm:w-64 sm:translate-x-0">
            <div class="p-3">
                <button onclick="showNewConversation()" class="flex w-full items-center gap-2 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-primary-fg transition hover:opacity-90">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    New chat
                </button>
            </div>
            <div id="conversations-list" class="flex-1 overflow-y-auto px-2">
                @foreach ($conversations ?? [] as $conversation)
                    <a href="#" onclick="loadConversation({{ $conversation->id }}); return false;" class="block rounded-lg px-3 py-2 text-sm text-text transition hover:bg-raised">
                        <p class="truncate font-medium">{{ $conversation->title }}</p>
                        <p class="text-xs text-muted">{{ $conversation->messages_count }} messages</p>
                    </a>
                @endforeach
            </div>
        </aside>

        <!-- Chat area -->
        <main class="flex flex-1 flex-col min-h-0">
            <!-- Messages -->
            <div id="messages" class="flex flex-1 flex-col gap-3 overflow-y-auto p-4 min-h-0">
                @forelse ($messages ?? [] as $message)
                    <div class="flex gap-3 {{ $message->role === 'user' ? 'justify-end' : '' }}">
                        @if ($message->role === 'user')
                            <div class="max-w-xl rounded-2xl bg-primary px-4 py-3 text-sm text-primary-fg">
                                {{ $message->content }}
                            </div>
                        @else
                            <div class="max-w-xl rounded-2xl border border-border bg-bg/60 px-4 py-3 text-sm text-text">
                                {!! nl2br(e($message->content)) !!}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="flex flex-1 items-center justify-center text-center">
                        <div>
                            <p class="text-sm font-semibold text-text">How can I help you today?</p>
                            <p class="mt-1 text-xs text-muted">Ask a question, explain a concept, or generate study materials.</p>
                        </div>
                    </div>
                @endforelse

                @if ($isTyping ?? false)
                    <div class="flex gap-3">
                        <div class="flex items-center gap-1 rounded-xl border border-border bg-bg/60 px-4 py-3">
                            <span class="h-2 w-2 animate-bounce rounded-full bg-muted"></span>
                            <span class="h-2 w-2 animate-bounce rounded-full bg-muted" style="animation-delay: 0.1s"></span>
                            <span class="h-2 w-2 animate-bounce rounded-full bg-muted" style="animation-delay: 0.2s"></span>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Input -->
            <form id="chat-form" action="{{ route('acli.chat.send') }}" method="POST" class="border-t border-border bg-surface p-4">
                @csrf
                <input type="hidden" name="conversation_id" id="active-conversation-id" value="{{ $activeConversationId ?? '' }}">
                <div class="flex items-end gap-3">
                    <textarea id="message-input" name="message" rows="1" placeholder="Ask anything about your courses..."
                        class="flex-1 resize-none rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text placeholder-muted transition focus:border-ring focus:ring-2 focus:ring-ring/20"
                        style="min-height: 44px; max-height: 160px;"></textarea>
                    <button type="submit" id="send-btn" class="mb-0.5 inline-flex shrink-0 items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-bold text-primary-fg shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    </button>
                </div>
            </form>
        </main>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('chat-form');
    const input = document.getElementById('message-input');
    const messagesEl = document.getElementById('messages');
    const activeConvId = document.getElementById('active-conversation-id');

    // Auto-resize textarea
    input.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 160) + 'px';
    });

    // Auto-focus
    input.focus();

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const message = input.value.trim();
        if (!message) return;

        const btn = document.getElementById('send-btn');
        btn.disabled = true;
        input.disabled = true;

        // Add user message
        appendMessage('user', message);
        input.value = '';
        input.style.height = 'auto';
        scrollToBottom();

        // Show typing indicator
        const typingEl = showTyping();
        scrollToBottom();

        try {
            // The textarea is cleared above so the user sees their message in the
            // transcript immediately, but FormData reads the live DOM -- building
            // it after the clear submitted an empty `message`, which Laravel
            // rejected with "The message field is required." Set the captured text
            // explicitly so what was captured is what gets sent.
            const formData = new FormData(form);
            formData.set('message', message);
            const response = await fetch('{{ route('acli.chat.stream') }}', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            removeTyping(typingEl);

            if (! response.ok) {
                // Laravel reports failures on `message` (validation, CSRF, 403/419/500),
                // while the orchestrator reports on `error` (provider/entitlement). Read
                // both, and fall back to the status line only when neither is present --
                // previously only `error` was read, so every Laravel-shaped failure
                // collapsed into "An error occurred." with no diagnostic.
                const data = await response.json().catch(() => ({}));
                const detail = data.error || data.message
                    || (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                    || `Request failed (HTTP ${response.status}). Please try again.`;
                appendMessage('assistant', detail);
                if (response.status === 401) {
                    window.location.href = '{{ route('login') }}';
                }
                if (response.status === 419) {
                    appendMessage('assistant', 'Your session expired. Reload the page and try again.');
                }
            } else {
                // Stream the reply as it arrives. Waiting for the whole body
                // meant a long answer sat behind three bouncing dots and looked
                // broken; now text appears as it is produced.
                removeTyping(typingEl);
                const streamed = await streamReply(response);
                if (streamed === null) {
                    const data = await response.json().catch(() => ({}));
                    appendMessage('assistant', data.message || '');
                }
                if (activeConvId && streamed && streamed.conversation_id) {
                    activeConvId.value = streamed.conversation_id;
                }
            }
        } catch (err) {
            removeTyping(typingEl);
            appendMessage('assistant', 'Connection error. Please check your connection and try again.');
        }

        btn.disabled = false;
        input.disabled = false;
        input.focus();
    });

    function appendMessage(role, content) {
        const wrapper = document.createElement('div');
        wrapper.className = 'flex gap-3 ' + (role === 'user' ? 'justify-end' : '');
        const bubble = document.createElement('div');
        bubble.className = 'max-w-xl rounded-2xl px-4 py-3 text-sm ' +
            (role === 'user' ? 'bg-primary text-primary-fg' : 'border border-border bg-bg/60 text-text');
        bubble.innerHTML = formatContent(content);
        wrapper.appendChild(bubble);
        messagesEl.appendChild(wrapper);
        scrollToBottom();
    }

    /**
     * Reads a streamed reply, re-rendering as each chunk lands.
     *
     * Falls back to a single render if the server sent ordinary JSON rather
     * than a stream, so this is an improvement and never a new way to fail.
     */
    async function streamReply(response) {
        if (! response.body || ! response.body.getReader) return null;

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let raw = '';
        let bubbleEl = null;

        while (true) {
            const { value, done } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value, { stream: true });
            raw += chunk;
            buffer += chunk;

            let boundary;
            while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                const frame = buffer.slice(0, boundary);
                buffer = buffer.slice(boundary + 2);

                if (! frame.trim() || frame.startsWith(':')) continue;

                let payload;
                try {
                    payload = JSON.parse(frame.replace(/^data:\s*/, ''));
                } catch (err) {
                    continue;
                }

                if (payload.conversation_id) {
                    if (activeConvId) activeConvId.value = payload.conversation_id;
                }

                if (typeof payload.message === 'string') {
                    if (! bubbleEl) bubbleEl = appendStreaming();
                    bubbleEl.innerHTML = formatContent(payload.message);
                    scrollToBottom();
                }

                if (payload.error) {
                    if (! bubbleEl) bubbleEl = appendStreaming();
                    bubbleEl.innerHTML = formatContent(payload.error);
                    scrollToBottom();
                }
            }
        }

        if (! bubbleEl) {
            // No frame ever arrived. The body is probably a JSON refusal -- a
            // missing entitlement or an expired session -- which the frame
            // parser above cannot see, so without this the chat just goes
            // silent and looks broken.
            const text = await response.text().catch(() => '');

            if (text.trim()) {
                try {
                    const parsed = JSON.parse(text);
                    if (parsed.error || parsed.message) {
                        appendMessage('assistant', parsed.error || parsed.message);
                    }
                } catch (e) {
                    appendMessage('assistant', 'ACLi did not return a reply. Please try again.');
                }
            }
        } else {
            addFeedback(bubbleEl);
        }

        return { conversation_id: activeConvId ? activeConvId.value : null };
    }

    function appendStreaming() {
        const wrapper = document.createElement('div');
        wrapper.className = 'flex gap-3';
        const bubble = document.createElement('div');
        bubble.className = 'max-w-xl rounded-2xl border border-border bg-bg/60 px-4 py-3 text-sm text-text';
        wrapper.appendChild(bubble);
        messagesEl.appendChild(wrapper);
        scrollToBottom();
        return bubble;
    }

    /** Copy, like and dislike, reported to the superadmin audit trail. */
    function addFeedback(bubbleEl) {
        if (bubbleEl.dataset.feedback === '1') return;
        bubbleEl.dataset.feedback = '1';

        const bar = document.createElement('div');
        bar.className = 'mt-2 flex items-center gap-1 border-t border-border pt-2';

        const buttons = [
            ['Copy', 'copy'],
            ['Like', 'like'],
            ['Dislike', 'dislike'],
        ];

        buttons.forEach(function (pair) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = pair[0];
            btn.className = 'rounded px-2 py-0.5 text-xs text-muted transition hover:bg-raised hover:text-text';

            btn.addEventListener('click', async function () {
                const verdict = pair[1];

                if (verdict === 'copy') {
                    try {
                        await navigator.clipboard.writeText(bubbleEl.innerText);
                        btn.textContent = 'Copied';
                        setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
                    } catch (e) {
                        btn.textContent = 'Press Ctrl+C';
                    }
                    return;
                }

                try {
                    await fetch('{{ route('acli.chat.feedback') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            conversation_id: activeConvId ? activeConvId.value : null,
                            content: bubbleEl.innerText.slice(0, 4000),
                            verdict: verdict,
                        }),
                    });
                } catch (e) { /* feedback must never break the chat */ }

                bar.querySelectorAll('button').forEach(function (b) {
                    b.classList.remove('bg-raised', 'text-primary');
                });
                btn.classList.add('bg-raised', 'text-primary');
            });

            bar.appendChild(btn);
        });

        bubbleEl.appendChild(bar);
    }

    function showTyping() {
        const wrapper = document.createElement('div');
        wrapper.className = 'flex gap-3';
        wrapper.id = 'typing-indicator';
        wrapper.innerHTML = '<div class="flex items-center gap-1 rounded-xl border border-border bg-bg/60 px-4 py-3">' +
            '<span class="h-2 w-2 animate-bounce rounded-full bg-muted"></span>' +
            '<span class="h-2 w-2 animate-bounce rounded-full bg-muted" style="animation-delay: 0.1s"></span>' +
            '<span class="h-2 w-2 animate-bounce rounded-full bg-muted" style="animation-delay: 0.2s"></span>' +
            '</div>';
        messagesEl.appendChild(wrapper);
        return wrapper;
    }

    function removeTyping(el) {
        if (el) el.remove();
    }

    /**
     * Renders a readable subset of markdown, safely.
     *
     * The previous version understood only bold, so a table arrived as a wall of
     * pipe characters. Tables are what ACLi actually produces when comparing
     * things, so they are handled first and properly.
     *
     * Everything is escaped before any tag is produced, so a model cannot inject
     * markup into the page by answering with angle brackets.
     */
    function escapeHtml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function formatInline(text) {
        return text
            .replace(/`([^`]+)`/g, '<code class="rounded bg-bg px-1 py-0.5 font-mono text-xs">$1</code>')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
    }

    function renderTable(rows) {
        const cells = rows.map(function (row) {
            return row.map(function (cell) {
                return cell.trim().replace(/^\||\|$/g, '').trim();
            });
        });

        // Drop the |---|---| separator row the model uses for alignment.
        const body = cells.filter(function (row) {
            return !row.every(function (c) { return /^:?-{2,}:?$/.test(c); });
        });

        if (! body.length) return '';

        const head = body.shift();

        let html = '<div class="my-2 overflow-x-auto"><table class="w-full border-collapse text-xs">' +
            '<thead><tr>' + head.map(function (c) {
                return '<th class="border border-border bg-raised px-2 py-1.5 text-left font-semibold">' +
                    formatInline(escapeHtml(c)) + '</th>';
            }).join('') + '</tr></thead><tbody>' +
            body.map(function (row) {
                return '<tr>' + row.map(function (c) {
                    return '<td class="border border-border px-2 py-1.5 align-top">' +
                        formatInline(escapeHtml(c)) + '</td>';
                }).join('') + '</tr>';
            }).join('') + '</tbody></table></div>';

        return html;
    }

    function formatContent(content) {
        const escaped = escapeHtml(String(content || ''));
        const lines = escaped.split(/\r?\n/);
        let html = '';
        let i = 0;

        while (i < lines.length) {
            let line = lines[i];

            // A table is a run of consecutive lines that start with a pipe.
            if (/^\s*\|/.test(line)) {
                const rows = [];
                while (i < lines.length && /^\s*\|/.test(lines[i])) {
                    rows.push(lines[i]);
                    i++;
                }
                html += renderTable(rows);
                continue;
            }

            // Fenced code.
            if (/^\s*```/.test(line)) {
                const code = [];
                i++;
                while (i < lines.length && !/^\s*```/.test(lines[i])) { code.push(lines[i]); i++; }
                i++;
                html += '<pre class="my-2 overflow-x-auto rounded-lg bg-raised p-3 text-xs"><code>' +
                    code.join('\n') + '</code></pre>';
                continue;
            }

            if (/^\s*#{1,6}\s+/.test(line)) {
                const level = line.match(/^\s*(#{1,6})\s+/)[1].length;
                const sizes = {1:'text-base',2:'text-sm',3:'text-sm',4:'text-xs',5:'text-xs',6:'text-xs'};
                html += '<p class="mt-2 font-semibold ' + (sizes[level] || 'text-xs') + '">' +
                    formatInline(line.replace(/^\s*#{1,6}\s+/, '')) + '</p>';
            } else if (/^\s*[-*+]\s+/.test(line)) {
                const items = [];
                while (i < lines.length && /^\s*[-*+]\s+/.test(lines[i])) {
                    items.push('<li>' + formatInline(lines[i].replace(/^\s*[-*+]\s+/, '')) + '</li>');
                    i++;
                }
                html += '<ul class="my-1 list-disc pl-5">' + items.join('') + '</ul>';
                continue;
            } else if (line.trim() === '') {
                html += '';
            } else {
                html += '<p class="my-1">' + formatInline(line) + '</p>';
            }

            i++;
        }

        return html;
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // Expose for sidebar
    window.showNewConversation = function () {
        // Started a real conversation rather than reloading, which discarded
        // the transcript the student was reading.
        window.location.href = '{{ route('acli.conversation.new') }}';
    };

    window.loadConversation = function (id) {
        window.location.href = '{{ route('acli.conversation.show', ['conversation' => '__ID__']) }}'.replace('__ID__', id);
    };
})();
</script>
@endsection

    <script>
        (function () {
            var sidebar = document.getElementById('conv-sidebar');
            var toggle = document.getElementById('conv-toggle');
            if (! sidebar || ! toggle) return;

            var backdrop = document.createElement('div');
            backdrop.className = 'absolute inset-0 z-10 hidden bg-black/30 sm:hidden';
            backdrop.addEventListener('click', close);
            sidebar.parentElement.insertBefore(backdrop, sidebar);

            function open() {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
                toggle.setAttribute('aria-expanded', 'true');
            }

            function close() {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
            }

            toggle.addEventListener('click', function () {
                sidebar.classList.contains('-translate-x-full') ? open() : close();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') close();
            });

            // A chosen conversation should close the drawer behind it.
            sidebar.addEventListener('click', function (e) {
                if (e.target.closest('a')) close();
            });

            window.closeConversationDrawer = close;
        })();
    </script>

    {{-- Fetches a chapter's full explanation the first time it is asked for,
