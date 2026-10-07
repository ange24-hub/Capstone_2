@php
    $links = auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU)
        ? [
            ['reports.population', 'users', 'Population reports'],
            ['concerns.summary', 'activity', 'Concern insights'],
            ['municipal.approvals.index', 'check', 'Account approvals'],
        ]
        : [
            ['concerns.index', 'activity', 'Resident concerns'],
            ['barangay.rbi-updates.index', 'form', 'RBI forms'],
            ['spatial.index', 'map', 'Household map'],
        ];
@endphp
<nav class="overview-shortcuts" aria-label="Dashboard quick access">
    <div class="overview-shortcuts-heading"><h2>Quick access</h2></div>
    @foreach($links as [$routeName, $icon, $title])
        <a class="overview-shortcut" href="{{ route($routeName) }}">
            <span class="overview-shortcut-icon"><x-app-icon :name="$icon" /></span>
            <span class="overview-shortcut-copy"><strong>{{ $title }}</strong></span>
            <x-app-icon name="arrow-right" />
        </a>
    @endforeach
</nav>
