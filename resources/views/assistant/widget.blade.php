@php
    $assistantBisaya = session('assistant.language.'.auth()->id(), 'en') === 'ceb';
    $assistantGroups = App\Services\AssistantGuide::questionGroups(auth()->user());
@endphp
<div class="rbim-assistant" data-assistant data-endpoint="{{ route('assistant.chat') }}">
    <button class="assistant-launcher" type="button" data-assistant-toggle aria-label="Open RBIM assistant" aria-expanded="false" aria-controls="rbim-assistant-panel">
        <span class="assistant-launcher-icon" aria-hidden="true"><x-app-icon name="inbox" /></span><span>Ask RBIM</span>
    </button>
    <section class="assistant-panel" id="rbim-assistant-panel" data-assistant-panel role="dialog" aria-modal="false" aria-labelledby="assistant-title" hidden>
        <header class="assistant-header">
            <div class="assistant-avatar" aria-hidden="true"><x-app-icon name="inbox" /></div>
            <div class="assistant-header-copy"><strong id="assistant-title">RBIM Assistant</strong><span>Records, services &amp; guidance</span></div>
            <button class="assistant-close" type="button" data-assistant-close aria-label="Close assistant"><x-app-icon name="close" /></button>
        </header>
        <div class="assistant-toolbar">
            <button class="assistant-reset" type="button" data-assistant-reset><span data-assistant-reset-label>New conversation</span></button>
            <label for="assistant-language">Reply in
                <select id="assistant-language" data-assistant-language>
                    <option value="en" @selected(!$assistantBisaya)>English</option>
                    <option value="ceb" @selected($assistantBisaya)>Bisaya</option>
                </select>
            </label>
        </div>
        <div class="assistant-messages" data-assistant-messages role="log" aria-live="polite" aria-relevant="additions" aria-label="Conversation">
            <div class="assistant-message assistant-message-bot">
                <div class="assistant-bubble">
                    <span class="assistant-source">RBIM Assistant</span>
                    <div class="assistant-response-content">
                        <p>{{ $assistantBisaya ? 'Maayong adlaw, '.auth()->user()->name.'.' : 'Hello, '.auth()->user()->name.'.' }}</p>
                        <p>{{ $assistantBisaya ? 'Pangutana bahin sa imong gitugotang records ug serbisyo, o pilia ang usa ka hilisgutan sa Explore questions.' : 'Ask about your permitted records and services, or choose a topic in Explore questions.' }}</p>
                    </div>
                    <div class="assistant-account-scope"><x-app-icon name="shield" /><span>{{ auth()->user()->roleLabel() }}</span></div>
                </div>
            </div>
        </div>
        <div class="assistant-suggestions-wrap" data-assistant-suggestions-wrap>
            <span class="assistant-section-label" data-assistant-suggestions-label>Try a question</span>
            <div class="assistant-suggestions" data-assistant-suggestions>
                @foreach (match(auth()->user()->role) {
                    App\Models\User::ROLE_MUNICIPAL_LGU => ['Show population summary', 'Show migration totals', 'Summarize submitted RBI forms'],
                    App\Models\User::ROLE_BARANGAY => ['Pila ka families sa among barangay?', 'Show PWD names', 'Show senior citizen names'],
                    default => ['Show my document requests', 'What is my account status?'],
                } as $suggestion)
                    <button type="button" data-assistant-suggestion>{{ $suggestion }}</button>
                @endforeach
            </div>
        </div>
        <details class="assistant-catalog" data-assistant-catalog>
            <summary><x-app-icon name="directory" /><span data-assistant-catalog-label>Explore questions</span><span class="assistant-catalog-count">{{ count(App\Services\AssistantGuide::questions(auth()->user())) }}</span></summary>
            <div class="assistant-catalog-body">
                <label class="assistant-search-label" for="assistant-question-search">Search questions</label>
                <input id="assistant-question-search" type="search" data-assistant-search placeholder="Search a topic or question" autocomplete="off">
                <p class="assistant-search-status" data-assistant-search-status role="status" hidden></p>
                <div class="assistant-catalog-groups">
                    @foreach ($assistantGroups as $group)
                        <details class="assistant-catalog-group" data-assistant-question-group>
                            <summary><span>{{ $group['label'] }}</span><span data-assistant-group-count>{{ count($group['questions']) }}</span></summary>
                            <div class="assistant-question-list">
                                @foreach ($group['questions'] as $question)
                                    <button type="button" data-assistant-question>{{ $question }}</button>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        </details>
        <div class="assistant-composer">
            <form class="assistant-form" data-assistant-form>
                <label class="sr-only" for="assistant-message">Ask about the RBIM system</label>
                <textarea id="assistant-message" data-assistant-input rows="1" maxlength="500" placeholder="Ask about RBIM…" required></textarea>
                <button type="submit" data-assistant-send aria-label="Send message"><x-app-icon name="arrow-right" /></button>
            </form>
            <p class="assistant-privacy"><x-app-icon name="shield" /><span data-assistant-privacy>Your account permissions always apply.</span></p>
        </div>
    </section>
</div>
<script src="{{ asset('js/assistant.js') }}?v={{ filemtime(public_path('js/assistant.js')) }}" defer></script>
