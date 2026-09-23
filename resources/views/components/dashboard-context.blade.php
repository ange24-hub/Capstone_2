@if(request()->routeIs('dashboard.barangay', 'dashboard.municipal', 'dashboard.resident'))
    <div class="overview-context">
        <div class="overview-context-office"><span class="overview-context-icon"><x-app-icon name="building" /></span><span>Municipality of<strong>Tomas Oppus</strong></span></div>
        <div class="overview-context-date" data-ph-clock data-server-time="{{ now()->toIso8601String() }}">
            <span data-ph-weekday>{{ now('Asia/Manila')->format('l') }}</span>
            <strong data-ph-date>{{ now('Asia/Manila')->format('d M Y') }}</strong>
            <span class="ph-clock-label">Philippine Standard Time (UTC+8)</span>
            <time class="ph-clock-time" data-ph-time datetime="{{ now('Asia/Manila')->toIso8601String() }}">{{ now('Asia/Manila')->format('h:i:s A') }}</time>
        </div>
        <span class="overview-context-caption">{{ auth()->user()->roleLabel() }} workspace</span>
    </div>
    @once
        @push('scripts')
            <script src="{{ asset('js/philippine-clock.js') }}?v={{ filemtime(public_path('js/philippine-clock.js')) }}" defer></script>
        @endpush
    @endonce
@endif
