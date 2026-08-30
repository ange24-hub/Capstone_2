<div class="rbim-assistant" data-assistant data-endpoint="{{ route('assistant.chat') }}">
    <button class="assistant-launcher" type="button" data-assistant-toggle aria-expanded="false" aria-controls="rbim-assistant-panel">
        <span class="assistant-launcher-icon" aria-hidden="true">AI</span>
        <span>Ask RBIM</span>
        <span class="assistant-online-dot" aria-hidden="true"></span>
    </button>

    <section class="assistant-panel" id="rbim-assistant-panel" data-assistant-panel aria-label="RBIM Assistant" hidden>
        <header class="assistant-header">
            <div class="assistant-avatar" aria-hidden="true">AI</div>
            <div>
                <strong>RBIM Assistant</strong>
                <span><i></i> System information only</span>
            </div>
            <button class="assistant-close" type="button" data-assistant-close aria-label="Close assistant">×</button>
        </header>

        <div class="assistant-messages" data-assistant-messages role="log" aria-live="polite">
            <div class="assistant-message assistant-message-bot">
                <div class="assistant-bubble">Hello, {{ auth()->user()->name }}. I can help with RBIM features and the information allowed for your {{ auth()->user()->roleLabel() }} account.</div>
            </div>
        </div>

        <div class="assistant-suggestions" data-assistant-suggestions>
            @foreach (match(auth()->user()->role) {
                App\Models\User::ROLE_MUNICIPAL_LGU => ['Summarize submitted RBI forms', 'Show migration totals'],
                App\Models\User::ROLE_BARANGAY => ['Show pending resident approvals', 'Summarize our registry'],
                default => ['Show my document requests', 'What is my account status?'],
            } as $suggestion)
                <button type="button" data-assistant-suggestion>{{ $suggestion }}</button>
            @endforeach
        </div>

        <form class="assistant-form" data-assistant-form>
            <label class="sr-only" for="assistant-message">Ask about the RBIM system</label>
            <textarea id="assistant-message" data-assistant-input rows="1" maxlength="500" placeholder="Ask about RBIM…" required></textarea>
            <button type="submit" data-assistant-send aria-label="Send message">➤</button>
        </form>
        <p class="assistant-privacy">Answers stay within RBIM and follow your account permissions.</p>
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

    const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        root.classList.toggle('is-open', open);
        if (open) setTimeout(() => input.focus(), 50);
    };

    const addMessage = (text, kind, actions = []) => {
        const row = document.createElement('div');
        row.className = `assistant-message assistant-message-${kind}`;
        const bubble = document.createElement('div');
        bubble.className = 'assistant-bubble';
        bubble.textContent = text;
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
        suggestions.hidden = true;
        const typing = addMessage('Checking RBIM…', 'bot');
        typing.classList.add('is-typing');

        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ message: clean }),
            });

            if (!response.ok) throw new Error('Request failed');
            const data = await response.json();
            typing.remove();
            addMessage(data.reply, 'bot', data.actions || []);
            showSuggestions(data.suggestions || []);
        } catch (error) {
            typing.remove();
            addMessage('I could not reach RBIM right now. Please try again.', 'bot');
        } finally {
            send.disabled = false;
            suggestions.hidden = false;
            input.focus();
        }
    };

    toggle.addEventListener('click', () => setOpen(panel.hidden));
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
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) setOpen(false);
    });
})();
</script>
