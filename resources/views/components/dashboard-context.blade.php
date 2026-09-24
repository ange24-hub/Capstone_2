@if(request()->routeIs('dashboard.barangay', 'dashboard.municipal', 'dashboard.resident'))
    <div class="overview-context">
        <div class="overview-context-office"><span class="overview-context-icon"><x-app-icon name="building" /></span><span>Municipality of<strong>Tomas Oppus</strong></span></div>
        <div class="overview-context-date" data-ph-clock>
            <span data-ph-weekday></span>
            <strong data-ph-date></strong>
            <span class="ph-clock-label" data-local-timezone>Device local time</span>
            <time class="ph-clock-time" data-ph-time>Loading clock…</time>
            <noscript>Enable JavaScript to display your device's date and time.</noscript>
        </div>
        <span class="overview-context-caption">{{ auth()->user()->roleLabel() }} workspace</span>
    </div>
@endif
