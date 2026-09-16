@extends('layouts.app')

@section('title', 'ACLi — AI Assistant')

@section('content')
<div class="mx-auto flex min-h-[calc(100dvh-4rem)] max-w-6xl flex-col px-4 sm:px-6">
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
            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700 uppercase tracking-wide">Online</span>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700 shrink-0">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="flex flex-1 gap-4 overflow-hidden rounded-2xl border border-border bg-surface shadow-sm min-h-0">
        <!-- Sidebar -->
        <aside class="hidden w-64 shrink-0 flex-col border-r border-border bg-surface sm:flex">
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
                @empty
                    <div class="flex flex-1 items-center justify-center text-center">
                        <div>
                            <p class="text-sm font-semibold text-text">How can I help you today?</p>
                            <p class="mt-1 text-xs text-muted">Ask a question, explain a concept, or generate study materials.</p>
                        </div>
                    </div>
                @endempty

                @foreach ($messages ?? [] as $message)
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
                @endforeach

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
            const formData = new FormData(form);
            const response = await fetch('{{ route('acli.chat.send') }}', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            removeTyping(typingEl);

            if (! response.ok) {
                const data = await response.json().catch(() => ({}));
                appendMessage('assistant', data.error || 'An error occurred.');
                if (response.status === 401) {
                    window.location.href = '{{ route('login') }}';
                }
            } else {
                const data = await response.json();
                appendMessage('assistant', data.message || '');
                if (data.conversation_id && activeConvId) {
                    activeConvId.value = data.conversation_id;
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

    function formatContent(content) {
        return content
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>');
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // Expose for sidebar
    window.showNewConversation = function () {
        // Implement new conversation via API if needed
        window.location.reload();
    };

    window.loadConversation = function (id) {
        // Implement conversation loading via API if needed
        window.location.reload();
    };
})();
</script>
@endsection