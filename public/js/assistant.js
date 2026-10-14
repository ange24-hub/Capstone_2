/* Render application-owned text as semantic elements, never as HTML. */
(() => {
    const blocks = (text) => {
        const result = [];
        let current = null;
        const flush = () => { if (current) result.push(current); current = null; };
        const collect = (type, item, extra = {}) => {
            if (current?.type !== type) { flush(); current = { type, items: [], ...extra }; }
            current.items.push(item);
        };
        for (const raw of String(text ?? '').replace(/\r/g, '').split('\n')) {
            const line = raw.trim();
            if (!line) { flush(); continue; }
            const numbered = line.match(/^(\d+)[.)]\s+(.+)$/u);
            if (numbered) { collect('ordered', numbered[2], { start: Number(numbered[1]) }); continue; }
            const bullet = line.match(/^(?:[-*•])\s+(.+)$/u);
            if (bullet) { collect('bullets', bullet[1]); continue; }
            const values = line.split(/\s+\|\s+/u).map(part => part.match(/^([^:]{1,64}):\s+(.+)$/u));
            if (values.every(Boolean)) {
                for (const value of values) collect('metrics', { label: value[1], value: value[2] });
                continue;
            }
            flush();
            const type = /^(?:Based |No resident |No recorded |Some records |Missing |Single.person |Records without |Showing the first |Senior citizens are identified |Gipakita ang unang |Giila ang senior citizens |Base |Walay |Kon walay )/iu.test(line) ? 'note'
                : line.endsWith(':') ? 'heading' : 'paragraph';
            result.push({ type, text: line });
        }
        flush();
        return result;
    };
    const element = (tag, text = null, className = '') => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== null) node.textContent = String(text);
        return node;
    };
    const render = (container, text) => {
        for (const block of blocks(text)) {
            if (['ordered', 'bullets'].includes(block.type)) {
                const list = element(block.type === 'ordered' ? 'ol' : 'ul');
                if (block.type === 'ordered') list.start = block.start;
                block.items.forEach(item => list.appendChild(element('li', item)));
                container.appendChild(list);
            } else if (block.type === 'metrics') {
                const list = element('dl', null, 'assistant-metrics');
                block.items.forEach(item => {
                    const row = element('div', null, 'assistant-metric' + (/^-?[\d,]+(?:\.\d+)?$/u.test(item.value) ? ' is-number' : ''));
                    row.append(element('dt', item.label), element('dd', item.value));
                    list.appendChild(row);
                });
                container.appendChild(list);
            } else {
                container.appendChild(element('p', block.text, block.type === 'note' ? 'assistant-response-note'
                    : block.type === 'heading' ? 'assistant-response-heading' : ''));
            }
        }
    };
    window.RbimAssistantFormat = { blocks, render };

    const root = document.querySelector('[data-assistant]');
    if (!root) return;
    const find = selector => root.querySelector(selector);
    const toggle = find('[data-assistant-toggle]'), close = find('[data-assistant-close]');
    const panel = find('[data-assistant-panel]'), form = find('[data-assistant-form]');
    const input = find('[data-assistant-input]'), send = find('[data-assistant-send]');
    const messages = find('[data-assistant-messages]'), suggestions = find('[data-assistant-suggestions]');
    const suggestionsWrap = find('[data-assistant-suggestions-wrap]');
    const language = find('[data-assistant-language]'), reset = find('[data-assistant-reset]');
    const catalog = find('[data-assistant-catalog]'), search = find('[data-assistant-search]');
    const groups = [...root.querySelectorAll('[data-assistant-question-group]')];
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const wording = (english, bisaya) => language.value === 'ceb' ? bisaya : english;
    let previousQuestion = null;
    let groupState = null;

    const setOpen = open => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        root.classList.toggle('is-open', open);
        if (open) (catalog.open ? search : input).focus(); else toggle.focus();
    };
    const setBrowsing = open => {
        catalog.open = open;
        panel.classList.toggle('is-browsing', open);
    };
    const sourceLabel = data => data.mode === 'database_list' ? wording('Registry names', 'Mga ngalan sa registry')
        : data.mode === 'database_summary' ? wording('Database summary', 'Summary sa database')
        : data.mode === 'system_guidance' ? wording('System guide', 'Giya sa system') : 'RBIM Assistant';

    const addMessage = (text, kind, data = {}) => {
        const row = element('div', null, `assistant-message assistant-message-${kind}`);
        const bubble = element('div', null, 'assistant-bubble');
        row.appendChild(bubble);
        if (kind === 'user' || data.typing) {
            bubble.textContent = text;
        } else {
            bubble.appendChild(element('span', sourceLabel(data), 'assistant-source'));
            if (data.notice) bubble.appendChild(element('p', data.notice, 'assistant-notice'));
            const facts = data.facts;
            if (typeof facts?.scope === 'string') {
                const scope = element('div', null, 'assistant-response-scope');
                scope.appendChild(element('strong', facts.scope));
                const period = facts.month ? wording('Reporting month: ', 'Reporting month: ') + facts.month
                    : facts.as_of ? wording('As of ', 'Kutob sa ') + facts.as_of
                    : facts.year ? wording('Reporting year: ', 'Reporting year: ') + facts.year : null;
                if (period) scope.appendChild(element('small', period));
                bubble.appendChild(scope);
                const lines = String(text ?? '').split('\n');
                const firstLine = lines[0].trim();
                if (firstLine === facts.scope || firstLine.startsWith(facts.scope + ' — ')) text = lines.slice(1).join('\n');
            }
            const content = element('div', null, 'assistant-response-content');
            render(content, text);
            bubble.appendChild(content);
            if (data.interpreted_question) {
                const interpretation = element('details', null, 'assistant-interpretation');
                interpretation.append(element('summary', wording('How this question was understood', 'Giunsa pagsabot ang pangutana')),
                    element('p', data.interpreted_question));
                bubble.appendChild(interpretation);
            }
            const links = element('div', null, 'assistant-actions');
            for (const action of Array.isArray(data.actions) ? data.actions : []) {
                try {
                    const url = new URL(action.url, window.location.href);
                    if (!['http:', 'https:'].includes(url.protocol) || url.origin !== window.location.origin) continue;
                    const link = element('a', action.label);
                    link.href = url.href;
                    links.appendChild(link);
                } catch { /* Ignore malformed action URLs. */ }
            }
            if (links.childElementCount) bubble.appendChild(links);
        }
        messages.appendChild(row);
        if (kind === 'bot' && !data.typing) {
            // Start at the answer's heading; long lists remain scrollable below it.
            messages.scrollTop += row.getBoundingClientRect().top - messages.getBoundingClientRect().top - 18;
        } else {
            messages.scrollTop = messages.scrollHeight;
        }
        return row;
    };
    const showSuggestions = (items = []) => {
        suggestions.replaceChildren();
        for (const item of items.slice(0, 3)) {
            const button = element('button', item);
            button.type = 'button';
            button.dataset.assistantSuggestion = '';
            suggestions.appendChild(button);
        }
        suggestionsWrap.hidden = !suggestions.childElementCount;
    };
    const filterQuestions = () => {
        const query = search.value.trim().toLocaleLowerCase();
        if (query && !groupState) groupState = groups.map(group => group.open);
        let total = 0;
        groups.forEach((group, index) => {
            let count = 0;
            group.querySelectorAll('[data-assistant-question]').forEach(button => {
                button.hidden = !!query && !button.textContent.toLocaleLowerCase().includes(query);
                if (!button.hidden) count++;
            });
            group.hidden = !count;
            group.querySelector('[data-assistant-group-count]').textContent = count;
            if (query) group.open = count > 0;
            else if (groupState) group.open = groupState[index];
            total += count;
        });
        if (!query) groupState = null;
        const status = find('[data-assistant-search-status]');
        status.hidden = !query;
        status.textContent = total ? total + wording(' matching questions', ' ka matching nga pangutana')
            : wording('No matches. Try population, PWD, documents or migration.', 'Walay match. Sulayi ang population, PWD, documents o migration.');
    };
    const updateLabels = () => {
        find('[data-assistant-reset-label]').textContent = wording('New conversation', 'Bag-ong panag-istorya');
        find('[data-assistant-catalog-label]').textContent = wording('Explore questions', 'Mga mapangutana');
        find('[data-assistant-suggestions-label]').textContent = wording('Try a question', 'Sulayi nga pangutana');
        find('[data-assistant-privacy]').textContent = wording('Your account permissions always apply.', 'Ang permissions sa imong account kanunay sundon.');
        input.placeholder = wording('Ask about RBIM…', 'Pangutana bahin sa RBIM…');
        filterQuestions();
    };

    const ask = async message => {
        const clean = message.trim();
        if (!clean || send.disabled) return;
        setBrowsing(false);
        addMessage(clean, 'user');
        input.value = ''; input.style.height = '';
        send.disabled = reset.disabled = language.disabled = true;
        panel.setAttribute('aria-busy', 'true');
        suggestionsWrap.hidden = true;
        const typing = addMessage(wording('Checking RBIM…', 'Gitan-aw ang RBIM…'), 'bot', { typing: true });
        typing.classList.add('is-typing');
        const abort = new AbortController();
        const timeout = setTimeout(() => abort.abort(), 35000);
        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST', signal: abort.signal,
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ message: clean, previous_question: previousQuestion, language: language.value }),
            });
            if (!response.ok) { const problem = new Error('Request failed'); problem.status = response.status; throw problem; }
            const data = await response.json();
            if (['en', 'ceb'].includes(data.language)) language.value = data.language;
            previousQuestion = data.previous_question || null;
            typing.remove();
            addMessage(data.reply, 'bot', data);
            showSuggestions(data.suggestions || []);
            updateLabels();
        } catch (error) {
            previousQuestion = null;
            typing.remove();
            const message = error.status === 429
                ? wording('Too many questions in a short time. Please wait a minute and try again.', 'Daghan ra kaayong pangutana sa mubo nga panahon. Paghulat og usa ka minuto ug sulayi pag-usab.')
                : [401, 419].includes(error.status)
                    ? wording('Your session has expired. Refresh the page and sign in again.', 'Na-expire ang imong session. I-refresh ang page ug pag-sign in pag-usab.')
                    : wording('I could not reach RBIM right now. Please try again with a complete question.', 'Dili nako makontak ang RBIM karon. Palihog sulayi pag-usab gamit ang kompleto nga pangutana.');
            addMessage(message, 'bot');
        } finally {
            clearTimeout(timeout);
            send.disabled = reset.disabled = language.disabled = false;
            panel.removeAttribute('aria-busy');
            suggestionsWrap.hidden = !suggestions.childElementCount;
            if (!panel.hidden) input.focus();
        }
    };
    toggle.addEventListener('click', () => setOpen(panel.hidden));
    close.addEventListener('click', () => setOpen(false));
    reset.addEventListener('click', () => {
        if (send.disabled) return;
        previousQuestion = null;
        messages.replaceChildren();
        addMessage(wording('New conversation. Ask a complete question to start a new topic.', 'Bag-ong panag-istorya. Pangutana og kompleto aron magsugod og bag-ong hilisgutan.'), 'bot');
        input.value = ''; input.style.height = ''; search.value = '';
        filterQuestions(); setBrowsing(false); input.focus();
    });
    form.addEventListener('submit', event => { event.preventDefault(); ask(input.value); });
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); form.requestSubmit(); }
    });
    input.addEventListener('input', () => { input.style.height = ''; input.style.height = `${Math.min(input.scrollHeight, 92)}px`; });
    suggestions.addEventListener('click', event => { const button = event.target.closest('[data-assistant-suggestion]'); if (button) ask(button.textContent); });
    catalog.addEventListener('click', event => { const button = event.target.closest('[data-assistant-question]'); if (button && !send.disabled) ask(button.textContent); });
    catalog.addEventListener('toggle', () => { panel.classList.toggle('is-browsing', catalog.open); if (catalog.open && !panel.hidden) search.focus(); });
    search.addEventListener('input', filterQuestions);
    language.addEventListener('change', updateLabels);
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); setOpen(false); } });
    updateLabels();
})();
