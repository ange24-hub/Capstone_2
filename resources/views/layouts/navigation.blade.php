@php
    $user = auth()->user();
    if ($user->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU)) {
        $navigationGroups = [
            'Administration' => [
                ['municipal.approvals.index', 'municipal.approvals.*', 'check', 'Account approvals'],
                ['municipal.barangays.index', 'municipal.barangays.*', 'directory', 'Barangay directory'],
            ],
            'Reports' => [
                ['reports.population', 'reports.population*', 'document', 'Population reports'],
                ['reports.migration', 'reports.migration*', 'document', 'Migration reports'],
                ['migration.dashboard', 'migration.dashboard', 'trend', 'Migration trends'],
                ['concerns.summary', 'concerns.*', 'users', 'Concern summary'],
            ],
            'Maps' => [['spatial.index', 'spatial.index', 'map', 'Household map']],
        ];
    } elseif ($user->hasRole(App\Models\User::ROLE_BARANGAY)) {
        $recordLinks = [
            ['barangay.registry.active', 'barangay.registry.active', 'users', 'Resident registry'],
        ];
        if ($user->barangay?->usesResidenceRegistry()) {
            $recordLinks[] = ['barangay.residence.index', 'barangay.residence.*', 'home', 'Living in barangay'];
        }
        $recordLinks[] = ['barangay.registry.moved-out', 'barangay.registry.moved-out*', 'trend', 'Moved out'];
        $recordLinks[] = ['barangay.registry.deceased', 'barangay.registry.deceased', 'document', 'Deceased records'];
        $navigationGroups = [
            'Services' => [
                ['barangay.resident-approvals.index', 'barangay.resident-approvals.*', 'check', 'Resident approvals'],
                ['barangay.document-requests.index', 'barangay.document-requests.*', 'document', 'Document requests'],
                ['barangay.document-payments.history', 'barangay.document-payments.history', 'payment', 'Payment history'],
                ['concerns.index', 'concerns.*', 'users', 'Resident concerns'],
            ],
            'Resident records' => $recordLinks,
            'Reports' => [
                ['reports.population', 'reports.population*', 'document', 'Population reports'],
                ['reports.migration', 'reports.migration*', 'document', 'Migration reports'],
                ['migration.dashboard', 'migration.dashboard', 'trend', 'Migration trends'],
            ],
            'Maps & surveys' => [
                ['spatial.index', 'spatial.index', 'map', 'Household map'],
                ['offline.household', 'offline.household', 'location', 'Offline household survey'],
            ],
        ];
    } else {
        $navigationGroups = [
            'My services' => [
                ['resident.document-requests.create', 'resident.document-requests.create', 'form', 'Request a document'],
                ['resident.document-requests.index', 'resident.document-requests.index', 'document', 'My requests'],
                ['resident.document-payments.history', 'resident.document-payments.history', 'payment', 'Payment history'],
                ['concerns.index', 'concerns.*', 'users', 'My concerns'],
            ],
        ];
    }
@endphp

<nav class="side-nav" aria-label="Primary navigation">
    <div class="side-section">Overview</div>
    <a class="{{ request()->routeIs('dashboard', 'dashboard.barangay', 'dashboard.municipal', 'dashboard.resident') ? 'active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard', 'dashboard.barangay', 'dashboard.municipal', 'dashboard.resident')) aria-current="page" @endif><span class="nav-mark"><x-app-icon name="home" /></span><span>Overview</span></a>
    @if ($user->hasRole(App\Models\User::ROLE_BARANGAY))
        <a class="{{ request()->routeIs('barangay.rbi-updates.*', 'rbi-updates.*', 'registry.new-inhabitants.*') ? 'active' : '' }}" href="{{ route('barangay.rbi-updates.index') }}" @if(request()->routeIs('barangay.rbi-updates.*', 'rbi-updates.*', 'registry.new-inhabitants.*')) aria-current="{{ request()->routeIs('barangay.rbi-updates.index') ? 'page' : 'location' }}" @endif><span class="nav-mark"><x-app-icon name="form" /></span><span>RBI Forms</span></a>
        @if(request()->routeIs('barangay.rbi-updates.*', 'registry.new-inhabitants.*'))
            <div class="rbi-sidebar-subpages">
                <a href="{{ route('barangay.rbi-updates.residents', ['new' => 1]) }}" @if(request()->routeIs('barangay.rbi-updates.residents')) aria-current="page" @endif>Add residents</a>
                <a href="{{ route('barangay.rbi-updates.deceased', request()->only('edit', 'new')) }}" @if(request()->routeIs('barangay.rbi-updates.deceased')) aria-current="page" @endif>Deceased inhabitants</a>
                <a href="{{ route('barangay.rbi-updates.history') }}" @if(request()->routeIs('barangay.rbi-updates.history', 'registry.new-inhabitants.*')) aria-current="page" @endif>Report history</a>
            </div>
        @endif
    @endif
    @foreach ($navigationGroups as $group => $links)
        <div class="side-section">{{ $group }}</div>
        @foreach ($links as [$routeName, $patterns, $icon, $label])
            @php
                $active = request()->routeIs(...(array) $patterns);
                if (request()->routeIs('registry.*') && ! request()->routeIs('registry.new-inhabitants.*')) {
                    $sheet = request('sheet', 'active');
                    $registryRoute = request()->routeIs('registry.deceased.*') || $sheet === 'deceased'
                        ? 'barangay.registry.deceased' : 'barangay.registry.active';
                    $active = $routeName === $registryRoute;
                }
            @endphp
            <a class="{{ $active ? 'active' : '' }}" href="{{ route($routeName) }}" @if($active) aria-current="page" @endif><span class="nav-mark"><x-app-icon :name="$icon" /></span><span>{{ $label }}</span></a>
        @endforeach
    @endforeach
    @if ($user->hasAnyRole([App\Models\User::ROLE_BARANGAY, App\Models\User::ROLE_MUNICIPAL_LGU]))
        <div class="side-section">Account</div>
        <a class="profile-navigation-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif><span class="nav-mark"><x-app-icon name="profile" /></span><span>Profile Info</span></a>
    @endif
</nav>
