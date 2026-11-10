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
                ['migration.dashboard', 'migration.*', 'trend', 'Migration trends'],
                ...($user->isApproved() ? [['prediction.dashboard', ['prediction.*','municipal.prediction-*'], 'trend', 'Statistical forecast']] : []),
                ['concerns.summary', 'concerns.*', 'users', 'Concern summary'],
            ],
            'Maps' => [['spatial.index', 'spatial.index', 'map', 'Household map']],
        ];
    } elseif ($user->hasRole(App\Models\User::ROLE_BARANGAY)) {
        $recordLinks = [
            ['barangay.registry.active', ['barangay.registry.active', 'barangay.residence.*'], 'users', 'Resident registry'],
        ];
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
                ['migration.dashboard', 'migration.*', 'trend', 'Migration trends'],
                ...($user->isApproved() ? [['prediction.dashboard', 'prediction.*', 'trend', 'Statistical forecast']] : []),
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
        <a class="{{ request()->routeIs('barangay.rbi-updates.*', 'rbi-updates.*', 'registry.new-inhabitants.*', 'rbi.arrivals.*') ? 'active' : '' }}" href="{{ route('barangay.rbi-updates.index') }}" @if(request()->routeIs('barangay.rbi-updates.*', 'rbi-updates.*', 'registry.new-inhabitants.*', 'rbi.arrivals.*')) aria-current="location" @endif><span class="nav-mark"><x-app-icon name="form" /></span><span>RBI Forms</span></a>
        @if(request()->routeIs('barangay.rbi-updates.*', 'registry.new-inhabitants.*', 'rbi.arrivals.*'))
            <div class="rbi-sidebar-subpages">
                <a href="{{ route('rbi.arrivals.index') }}" @if(request()->routeIs('rbi.arrivals.*')) aria-current="page" @endif>3-month arrival counts</a>
                <a href="{{ route('barangay.rbi-updates.residents', ['new' => 1]) }}" @if(request()->routeIs('barangay.rbi-updates.index', 'barangay.rbi-updates.residents', 'barangay.rbi-updates.deceased')) aria-current="page" @endif>Monthly RBI form</a>
                <a href="{{ route('barangay.rbi-updates.history') }}" @if(request()->routeIs('barangay.rbi-updates.history', 'registry.new-inhabitants.*', 'rbi.arrivals.*')) aria-current="page" @endif>Report history</a>
            </div>
        @endif
    @endif
    @foreach ($navigationGroups as $group => $links)
        <div class="side-section">{{ $group }}</div>
        @foreach ($links as [$routeName, $patterns, $icon, $label])
            @php
                $active = request()->routeIs(...(array) $patterns);
                if (request()->routeIs('registry.*') && ! request()->routeIs('registry.new-inhabitants.*', 'rbi.arrivals.*')) {
                    $sheet = request('sheet', 'active');
                    $registryRoute = request()->routeIs('registry.deceased.*') || $sheet === 'deceased'
                        ? 'barangay.registry.deceased' : 'barangay.registry.active';
                    $active = $routeName === $registryRoute;
                }
            @endphp
            <a class="{{ $active ? 'active' : '' }}" href="{{ route($routeName) }}" @if($active) aria-current="page" @endif><span class="nav-mark"><x-app-icon :name="$icon" /></span><span>{{ $label }}</span></a>
            @if($routeName === 'reports.population' && request()->routeIs('reports.population*'))
                <div class="rbi-sidebar-subpages" aria-label="Population report subpages">
                    @if($user->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU))
                    <a href="{{ route('reports.population.submissions.index', request()->only('report_year','semester')) }}">Reports by year &amp; semester</a>
                    @else
                    <a href="{{ route('reports.population', request()->only('barangay_id')) }}" @if(request()->routeIs('reports.population', 'reports.population.submissions.sign')) aria-current="page" @endif>Population Summary</a>
                    <a href="{{ route('reports.population.submissions.index', request()->only('barangay_id')) }}" @if(request()->routeIs('reports.population.submissions.index', 'reports.population.submissions.show')) aria-current="page" @endif>Submission History</a>
                    @endif
                </div>
            @endif
        @endforeach
    @endforeach
    @if ($user->hasAnyRole([App\Models\User::ROLE_BARANGAY, App\Models\User::ROLE_MUNICIPAL_LGU]))
        <div class="side-section">Account</div>
        <a class="profile-navigation-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif><span class="nav-mark"><x-app-icon name="profile" /></span><span>Profile Info</span></a>
    @endif
</nav>
