<div class="rbim-assistant" data-assistant data-endpoint="{{ route('assistant.chat') }}">
    <button class="assistant-launcher" type="button" data-assistant-toggle aria-expanded="false" aria-controls="rbim-assistant-panel">
        <span class="assistant-launcher-icon" aria-hidden="true">AI</span>
        <span>Ask RBIM</span>
        <span class="assistant-online-dot" aria-hidden="true"></span>
    </button>

    <section class="assistant-panel flex max-h-[calc(100dvh-100px)] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl " id="rbim-assistant-panel" data-assistant-panel aria-label="RBIM Assistant" hidden>
        <header class="assistant-header">
            <div class="assistant-avatar" aria-hidden="true">AI</div>
            <div>
                <strong>RBIM Assistant</strong>
                <span><i></i> System information only</span>
            </div>
            <button class="assistant-close" type="button" data-assistant-close aria-label="Close assistant">×</button>
        </header>

        <div class="assistant-messages min-h-16 shrink" data-assistant-messages role="log" aria-live="polite">
            <div class="assistant-message assistant-message-bot">
                <div class="assistant-bubble">Hello, {{ auth()->user()->name }}. I can help with RBIM features and the information allowed for your {{ auth()->user()->roleLabel() }} account.</div>
            </div>
        </div>

        <div class="assistant-suggestions" data-assistant-suggestions>
            @foreach (match(auth()->user()->role) {
                App\Models\User::ROLE_MUNICIPAL_LGU => ['Show population summary', 'Show migration totals', 'Summarize submitted RBI forms'],
                App\Models\User::ROLE_BARANGAY => ['Pila ka families sa among barangay?', 'Show migration totals', 'Show pending resident approvals'],
                default => ['Show my document requests', 'What is my account status?'],
            } as $suggestion)
                <button type="button" data-assistant-suggestion>{{ $suggestion }}</button>
            @endforeach
        </div>

        <details class="border-t border-slate-200 bg-slate-50 px-4 py-2 text-sm" data-assistant-catalog>
            <summary class="cursor-pointer font-semibold text-blue-900">Explore questions / Mga mapangutana</summary>
            <div class="mt-2 flex max-h-40 flex-wrap gap-2 overflow-y-auto pb-2">
                @foreach (App\Services\AssistantGuide::questions(auth()->user()) as $question)
                    <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-xs text-slate-800 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-700" data-assistant-question>{{ $question }}</button>
                @endforeach
            </div>
        </details>

        <form class="assistant-form" data-assistant-form>
            <label class="sr-only" for="assistant-message">Ask about the RBIM system</label>
            <textarea id="assistant-message" data-assistant-input rows="1" maxlength="500" placeholder="Ask about RBIM…" required></textarea>
            <button type="submit" data-assistant-send aria-label="Send message">➤</button>
        </form>
        <p class="assistant-privacy">Follow-ups: “ug senior?”, “ug last month?” or “download PDF”. Account permissions always apply.</p>
        <button type="button" class="mb-2 text-xs font-semibold text-blue-900" data-assistant-reset>New conversation</button>
    </section>
</div>

<script>
(() => {
    const root = document.querySelector('[data-assistant]');
    if (!root) return;

    const toggle = root.querySelector('[data-assistant-toggle]');
    const close = root.querySelector('[data-assistant-close]');
    const panel = root.querySelector('[data-assistant-panel]');
    const form = root.querySelector('[data-assistant-form]');
    const input = root.querySelector('[data-assistant-input]');
    const send = root.querySelector('[data-assistant-send]');
    const messages = root.querySelector('[data-assistant-messages]');
    const suggestions = root.querySelector('[data-assistant-suggestions]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let previousQuestion = null;
    const reset = root.querySelector('[data-assistant-reset]');

    const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        root.classList.toggle('is-open', open);
        if (open) setTimeout(() => input.focus(), 50);
    };

    const addMessage = (text, kind, actions = [], mode = null) => {
        const row = document.createElement('div');
        row.className = `assistant-message assistant-message-${kind}`;
        const bubble = document.createElement('div');
        bubble.className = 'assistant-bubble';
        bubble.textContent = text;
        if (kind === 'bot' && mode) {
            const source = document.createElement('div');
            source.className = 'mb-2 text-xs font-semibold text-slate-600';
            source.textContent = mode === 'local_ai'
                ? 'Database summary with local AI explanation'
                : 'Database summary';
            bubble.prepend(source);
        }
        row.appendChild(bubble);

        if (actions.length) {
            const links = document.createElement('div');
            links.className = 'assistant-actions';
            actions.forEach((action) => {
                const link = document.createElement('a');
                link.href = action.url;
                link.textContent = action.label;
                links.appendChild(link);
            });
            bubble.appendChild(links);
        }

        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
        return row;
    };

    const showSuggestions = (items = []) => {
        suggestions.replaceChildren();
        items.slice(0, 3).forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.assistantSuggestion = '';
            button.textContent = item;
            suggestions.appendChild(button);
        });
    };

    const ask = async (message) => {
        const clean = message.trim();
        if (!clean || send.disabled) return;

        addMessage(clean, 'user');
        input.value = '';
        input.style.height = '';
        send.disabled = true;
        reset.disabled = true;
        panel.setAttribute('aria-busy', 'true');
        suggestions.hidden = true;
        const typing = addMessage('Checking RBIM…', 'bot');
        typing.classList.add('is-typing');

        const abort = new AbortController();
        const timeout = setTimeout(() => abort.abort(), 35000);
        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                signal: abort.signal,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ message: clean, previous_question: previousQuestion }),
            });

            if (!response.ok) {
                const problem = new Error('Request failed');
                problem.status = response.status;
                throw problem;
            }
            const data = await response.json();
            previousQuestion = data.previous_question || null;
            typing.remove();
            if (data.interpreted_question) addMessage('Using: ' + data.interpreted_question, 'bot');
            addMessage(data.reply, 'bot', data.actions || [], data.facts ? data.mode : null);
            showSuggestions(data.suggestions || []);
        } catch (error) {
            previousQuestion = null;
            typing.remove();
            const message = error.status === 429
                ? 'Too many questions in a short time. Please wait a minute and try again.'
                : [401, 419].includes(error.status)
                    ? 'Your session has expired. Refresh the page and sign in again.'
                    : 'I could not reach RBIM right now. Please try again with a complete question.';
            addMessage(message, 'bot');
        } finally {
            clearTimeout(timeout);
            send.disabled = false;
            reset.disabled = false;
            panel.removeAttribute('aria-busy');
            suggestions.hidden = false;
            input.focus();
        }
    };

    toggle.addEventListener('click', () => setOpen(panel.hidden));
    reset.addEventListener('click', () => {
        if (send.disabled) return;
        previousQuestion = null;
        messages.replaceChildren();
        addMessage('New conversation. Ask a complete question to start a new topic.', 'bot');
        input.value = '';
        input.style.height = '';
        root.querySelector('[data-assistant-catalog]').open = false;
        input.focus();
    });
    close.addEventListener('click', () => setOpen(false));
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        ask(input.value);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    input.addEventListener('input', () => {
        input.style.height = '';
        input.style.height = `${Math.min(input.scrollHeight, 92)}px`;
    });
    suggestions.addEventListener('click', (event) => {
        const button = event.target.closest('[data-assistant-suggestion]');
        if (button) ask(button.textContent);
    });
    root.querySelector('[data-assistant-catalog]').addEventListener('click', (event) => {
        const button = event.target.closest('[data-assistant-question]');
        if (button && !send.disabled) {
            root.querySelector('[data-assistant-catalog]').open = false;
            ask(button.textContent);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) setOpen(false);
    });
})();
</script>
