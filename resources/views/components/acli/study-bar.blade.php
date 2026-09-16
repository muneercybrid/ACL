@props([
    'courseOfferingId' => null,
    'chapterId' => null,
    'lessonId' => null,
    'contextLabel' => 'this page',
    'contextTitle' => null,
])

{{--
    ACLi contextual study bar.

    Rendered on study pages (lesson / chapter / course outline). This is the
    primary AI entry point inside content: it is scoped to the page the
    student is reading, and supports asking about selected text.

    Authorization, entitlement, and provider calls all happen server-side in
    AcliChatController -> AcliOrchestrator. This component is presentation
    only; hiding or showing it is never a security control.
--}}
<div
    x-data="acliStudyBar({
        courseOfferingId: {{ $courseOfferingId ?? 'null' }},
        chapterId: {{ $chapterId ?? 'null' }},
        lessonId: {{ $lessonId ?? 'null' }},
        contextTitle: @js($contextTitle),
    })"
    class="mt-10 rounded-2xl border border-border bg-surface shadow-sm overflow-hidden"
>
    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 border-b border-border bg-raised/30 px-5 py-3">
        <div class="flex items-center gap-3">
            <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg bg-gradient-to-br from-primary to-emerald-500">
                <img src="{{ asset('images/logo.svg') }}" alt="ACLi" class="h-4 w-4 object-contain brightness-0 invert">
            </span>
            <div>
                <p class="text-sm font-extrabold text-text">Ask ACLi</p>
                <p class="text-[11px] text-muted">AI help for {{ $contextLabel }}@if ($contextTitle) · {{ \Illuminate\Support\Str::limit($contextTitle, 60) }}@endif</p>
            </div>
        </div>
        <span class="hidden items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 sm:inline-flex">Free</span>
    </div>

    {{-- Quick actions --}}
    <div class="flex flex-wrap gap-2 border-b border-border px-5 py-3">
        <template x-for="action in quickActions" :key="action.label">
            <button type="button"
                    @click="ask(action.prompt)"
                    :disabled="loading"
                    class="rounded-full border border-border bg-bg px-3 py-1.5 text-xs font-semibold text-text transition hover:border-primary/40 hover:text-primary disabled:opacity-50"
                    x-text="action.label"></button>
        </template>
    </div>

    {{-- Selected text chip --}}
    <div x-show="selectedText.length > 0" x-cloak class="flex items-start gap-2 border-b border-border bg-primary/5 px-5 py-3">
        <span class="mt-0.5 text-[10px] font-bold uppercase tracking-wider text-primary">Selected</span>
        <p class="flex-1 text-xs text-text" x-text="selectedText.length > 220 ? selectedText.substring(0, 220) + '…' : selectedText"></p>
        <button type="button" @click="selectedText = ''" class="text-xs font-semibold text-muted hover:text-text">Clear</button>
    </div>

    {{-- Conversation --}}
    <div x-show="messages.length > 0" x-cloak class="max-h-96 space-y-3 overflow-y-auto px-5 py-4">
        <template x-for="(m, i) in messages" :key="i">
            <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                <div :class="m.role === 'user'
                        ? 'max-w-xl rounded-2xl bg-primary px-4 py-2.5 text-sm text-primary-fg'
                        : 'max-w-2xl rounded-2xl border border-border bg-bg/60 px-4 py-2.5 text-sm text-text'"
                     x-html="m.role === 'user' ? escapeHtml(m.content) : renderMarkdown(m.content)"></div>
            </div>
        </template>
        <div x-show="loading" class="flex justify-start">
            <div class="flex items-center gap-1 rounded-xl border border-border bg-bg/60 px-4 py-3">
                <span class="h-2 w-2 animate-bounce rounded-full bg-muted"></span>
                <span class="h-2 w-2 animate-bounce rounded-full bg-muted" style="animation-delay:.1s"></span>
                <span class="h-2 w-2 animate-bounce rounded-full bg-muted" style="animation-delay:.2s"></span>
            </div>
        </div>
    </div>

    {{-- Error --}}
    <div x-show="error" x-cloak class="border-t border-red-200 bg-red-50 px-5 py-3 text-xs text-red-700" x-text="error"></div>

    {{-- Input --}}
    <form @submit.prevent="ask(input)" class="border-t border-border p-4">
        <div class="flex items-end gap-3">
            <textarea
                x-model="input"
                rows="1"
                @input="autosize($event)"
                @keydown.enter.exact.prevent="ask(input)"
                placeholder="Ask anything about {{ $contextLabel }}…"
                class="flex-1 resize-none rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text placeholder-muted transition focus:border-ring focus:ring-2 focus:ring-ring/20"
                style="min-height:44px;max-height:140px;"
            ></textarea>
            <button type="submit" :disabled="loading || input.trim().length === 0"
                    class="mb-0.5 inline-flex shrink-0 items-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-bold text-primary-fg transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                <span class="hidden sm:inline">Ask</span>
            </button>
        </div>
        <p class="mt-2 text-[11px] text-muted">Highlight any text on this page to ask about it specifically.</p>
    </form>
</div>

@once
@push('scripts')
<script>
function acliStudyBar(config) {
    return {
        input: '',
        loading: false,
        error: '',
        messages: [],
        selectedText: '',
        quickActions: [
            { label: 'Explain this', prompt: 'Explain this topic in simple terms suitable for a university student.' },
            { label: 'Summarise', prompt: 'Summarise the key points of this material.' },
            { label: 'Give an example', prompt: 'Give a practical, real-world example of this concept.' },
            { label: 'Practice question', prompt: 'Give me one practice question on this topic with a worked answer.' },
        ],
        init() {
            const capture = () => {
                const text = (window.getSelection()?.toString() || '').trim();
                if (text.length > 3) {
                    this.selectedText = text;
                }
            };
            document.addEventListener('mouseup', capture);
            document.addEventListener('touchend', capture);
        },
        autosize(e) {
            e.target.style.height = 'auto';
            e.target.style.height = Math.min(e.target.scrollHeight, 140) + 'px';
        },
        buildMessage(prompt) {
            const base = (prompt || '').trim();
            if (this.selectedText.length > 0) {
                return base + '\n\nRegarding this selected text:\n"' + this.selectedText + '"';
            }
            return base;
        },
        async ask(prompt) {
            const base = (prompt || '').trim();
            if (!base || this.loading) return;

            const display = base;
            const content = this.buildMessage(base);
            this.selectedText = '';

            this.messages.push({ role: 'user', content: display });
            this.input = '';
            this.loading = true;
            this.error = '';

            const body = new FormData();
            body.append('message', content);
            body.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            if (config.courseOfferingId) body.append('course_offering_id', config.courseOfferingId);
            if (config.chapterId) body.append('chapter_id', config.chapterId);
            if (config.lessonId) body.append('lesson_id', config.lessonId);

            try {
                const res = await fetch('{{ route('acli.chat.send') }}', {
                    method: 'POST',
                    body,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));

                if (!res.ok || !data.success) {
                    this.error = data.error || 'ACLi is temporarily unavailable. Please try again.';
                } else {
                    this.messages.push({ role: 'assistant', content: data.message || '' });
                }
            } catch (e) {
                this.error = 'Connection error. Please check your connection and try again.';
            } finally {
                this.loading = false;
            }
        },
        escapeHtml(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        },
        renderMarkdown(s) {
            let out = this.escapeHtml(s);
            out = out.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            out = out.replace(/`([^`]+)`/g, '<code class="rounded bg-raised px-1 py-0.5 text-[12px]">$1</code>');
            out = out.replace(/\n/g, '<br>');
            return out;
        },
    };
}
</script>
@endpush
@endonce
