@php
    $links = auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU)
        ? [
            ['reports.population', 'users', 'Population reports', 'Explore available community records'],
            ['concerns.summary', 'activity', 'Concern insights', 'Review reported community concerns'],
            ['municipal.approvals.index', 'check', 'Account approvals', 'Review barangay secretary registrations'],
        ]
        : [
            ['concerns.index', 'activity', 'Resident concerns', 'Review and respond to community concerns'],
            ['barangay.rbi-updates.index', 'form', 'RBI forms', 'Prepare and manage monthly reports'],
            ['spatial.index', 'map', 'Household map', 'Explore your barangay household locations'],
        ];
@endphp
<nav class="overview-shortcuts" aria-label="Dashboard quick access">
    <div class="overview-shortcuts-heading"><span class="overview-section-kicker">QUICK ACCESS</span><h2>Your workspace tools</h2><p>Go straight to the service you need.</p></div>
    @foreach($links as [$routeName, $icon, $title, $description])
        <a class="overview-shortcut" href="{{ route($routeName) }}">
            <span class="overview-shortcut-icon"><x-app-icon :name="$icon" /></span>
            <span class="overview-shortcut-copy"><strong>{{ $title }}</strong><small>{{ $description }}</small></span>
            <x-app-icon name="arrow-right" />
        </a>
    @endforeach
</nav>
