@if(request()->routeIs('dashboard.barangay', 'dashboard.municipal', 'dashboard.resident'))
    <div class="overview-context">
        <div class="overview-context-office"><span class="overview-context-icon"><x-app-icon name="building" /></span><span>Municipality of<strong>Tomas Oppus</strong></span></div>
        <div class="overview-context-date"><span>{{ now()->format('l') }}</span><strong>{{ now()->format('d M Y') }}</strong></div>
        <span class="overview-context-caption">{{ auth()->user()->roleLabel() }} workspace</span>
    </div>
@endif
