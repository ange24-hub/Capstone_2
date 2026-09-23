<!doctype html>
<html lang="en" class="municipal-ui">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#142d4e">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'RBIM') }} | Municipality of Tomas Oppus</title>
    <link rel="stylesheet" href="{{ asset('css/rbim.css') }}?v={{ filemtime(public_path('css/rbim.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/portal-template.css') }}?v={{ filemtime(public_path('css/portal-template.css')) }}" media="screen">
    @vite('resources/css/app.css')
    @stack('head')
</head>
<body @auth class="role-{{ str_replace('_', '-', auth()->user()->role) }} {{ request()->routeIs('dashboard.barangay', 'dashboard.municipal', 'dashboard.resident') ? 'dashboard-workspace' : '' }} {{ request()->routeIs('barangay.registry.*', 'registry.*', 'migration.dashboard', 'spatial.index') ? 'wide-workspace' : '' }} {{ !request()->routeIs('dashboard.*') ? 'focused-workspace' : '' }}" @endauth>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    @guest
        <div class="public-shell">
            <div class="ph-government-bar">
                <div><span>Republic of the Philippines</span><span>Municipality of Tomas Oppus, Southern Leyte</span></div>
            </div>

            <header class="public-site-header">
                <div class="public-header-inner">
                    <a class="public-brand" href="{{ route('home') }}">
                        <span class="seal-crop public-seal">
                            <img src="{{ asset('images/tomas-oppus-seal.png') }}" alt="Municipality of Tomas Oppus seal">
                        </span>
                        <span class="public-brand-copy">
                            <small>Official Website of the</small>
                            <strong>Municipality of Tomas Oppus</strong>
                            <em>Southern Leyte</em>
                        </span>
                    </a>

                    <nav class="public-nav" aria-label="Public navigation">
                        <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
                        <a href="{{ route('home') }}#about">About</a>
                        <a href="{{ route('services') }}" @if(request()->routeIs('services')) aria-current="page" @endif>Services</a>
                        <a class="public-login-link" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Login</a>
                        <a class="public-register-link" href="{{ route('register') }}" @if(request()->routeIs('register')) aria-current="page" @endif>Register</a>
                    </nav>
                </div>
            </header>

            <main class="public-content" id="main-content">
                @yield('content')
            </main>

            <footer class="public-footer">
                <div class="public-footer-main">
                    <div class="public-footer-brand">
                        <span class="seal-crop footer-seal"><img src="{{ asset('images/tomas-oppus-seal.png') }}" alt=""></span>
                        <div><strong>Municipal Government of Tomas Oppus</strong><span>Serving the people through accessible and responsible digital governance.</span></div>
                    </div>
                    <div class="public-footer-meta">
                        <strong>Registry of Barangay Inhabitants Management</strong>
                        <span>Authorized public service portal &middot; Municipality of Tomas Oppus</span>
                    </div>
                </div>
                <div class="public-footer-bottom">
                    <span>&copy; {{ now()->year }} Municipality of Tomas Oppus. All rights reserved.</span>
                    <span>Southern Leyte, Philippines</span>
                </div>
            </footer>
        </div>
    @else
        <div class="app-shell">
            <aside class="app-sidebar bg-blue-950 text-white" id="app-sidebar">
                <a class="app-brand" href="{{ route('dashboard') }}">
                    <span class="seal-crop app-brand-seal">
                        <img src="{{ asset('images/tomas-oppus-seal.png') }}" alt="Municipality of Tomas Oppus seal">
                    </span>
                    <span>
                        <small>Municipality of</small>
                        <strong>Tomas Oppus</strong>
                        <em>RBIM System</em>
                    </span>
                </a>

                <div class="sidebar-office">
                    <span>Official LGU Workspace</span>
                    <strong>Registry of Barangay Inhabitants</strong>
                </div>

                <div class="side-label">Workspace</div>
                <nav class="side-nav" aria-label="Primary navigation">
                    <a class="{{ request()->routeIs('dashboard.barangay', 'dashboard.municipal', 'dashboard.resident') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-mark"><x-app-icon name="home" /></span><span>Overview</span></a>
                    @if(auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU))
                        <a class="{{ request()->routeIs('concerns.*') ? 'active' : '' }}" href="{{ route('concerns.summary') }}"><span class="nav-mark"><x-app-icon name="users" /></span><span>Concern Insights</span></a>
                    @else
                        <a class="{{ request()->routeIs('concerns.*') ? 'active' : '' }}" href="{{ route('concerns.index') }}"><span class="nav-mark"><x-app-icon name="users" /></span><span>{{ auth()->user()->hasRole(App\Models\User::ROLE_RESIDENT) ? 'My Concerns' : 'Resident Concerns' }}</span></a>
                    @endif
                    @if (auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU))
                        <a class="@if(request()->routeIs('municipal.approvals.*')) active @endif" href="{{ route('municipal.approvals.index') }}"><span class="nav-mark"><x-app-icon name="check" /></span><span>Account Approvals</span></a>
                        <a class="@if(request()->routeIs('municipal.barangays.*')) active @endif" href="{{ route('municipal.barangays.index') }}"><span class="nav-mark"><x-app-icon name="directory" /></span><span>Barangay Directory</span></a>
                        <a class="@if(request()->routeIs('migration.dashboard')) active @endif" href="{{ route('migration.dashboard') }}"><span class="nav-mark"><x-app-icon name="trend" /></span><span>Migration Trends</span></a>
                        <a class="@if(request()->routeIs('spatial.index')) active @endif" href="{{ route('spatial.index') }}"><span class="nav-mark"><x-app-icon name="map" /></span><span>Movement Map</span></a>
                    @elseif (auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))
                        <a class="@if(request()->routeIs('barangay.resident-approvals.*')) active @endif" href="{{ route('barangay.resident-approvals.index') }}"><span class="nav-mark"><x-app-icon name="check" /></span><span>Resident Approvals</span></a>
                        <a class="@if(request()->routeIs('barangay.document-requests.*')) active @endif" href="{{ route('barangay.document-requests.index') }}"><span class="nav-mark"><x-app-icon name="document" /></span><span>Document Requests</span></a>
                        <div class="side-section">Resident records</div>
                        <a class="@if(request()->routeIs('barangay.rbi-updates.*')) active @endif" href="{{ route('barangay.rbi-updates.index') }}"><span class="nav-mark"><x-app-icon name="form" /></span><span>RBI Forms</span></a>
                        @if(request()->routeIs('barangay.rbi-updates.*'))
                            <div class="rbi-sidebar-subpages">
                                <a href="{{ route('barangay.rbi-updates.residents', ['new' => 1]) }}" @if(request()->routeIs('barangay.rbi-updates.index', 'barangay.rbi-updates.residents')) aria-current="page" @endif>Add residents</a>
                                <a href="{{ route('barangay.rbi-updates.deceased', request()->only('edit', 'new')) }}" @if(request()->routeIs('barangay.rbi-updates.deceased')) aria-current="page" @endif>Deceased inhabitants</a>
                            </div>
                        @endif
                        <a class="@if(request()->routeIs('barangay.registry.deceased')) active @endif" href="{{ route('barangay.registry.deceased') }}"><span class="nav-mark"><x-app-icon name="document" /></span><span>Deceased Records</span></a>
                        <a class="@if(request()->routeIs('barangay.registry.active') || request()->routeIs('registry.*') && !in_array(request('sheet'), ['new-inhabitants', 'deceased'], true)) active @endif" href="{{ route('barangay.registry.active') }}"><span class="nav-mark"><x-app-icon name="users" /></span><span>{{ auth()->user()->barangay?->usesResidenceRegistry() ? 'Consolidated / All Registered' : 'Resident Registry' }}</span></a>
@if(auth()->user()->barangay?->usesResidenceRegistry())
                        <a class="{{ request()->routeIs('barangay.residence.*') ? 'active' : '' }}" href="{{ route('barangay.residence.index') }}"><span class="nav-mark"><x-app-icon name="home" /></span><span>Living in Barangay</span></a>
@endif
                        <a class="@if(request()->routeIs('barangay.registry.moved-out*')) active @endif" href="{{ route('barangay.registry.moved-out') }}"><span class="nav-mark"><x-app-icon name="trend" /></span><span>Moved Out</span></a>
                        <a class="@if(request()->routeIs('migration.dashboard')) active @endif" href="{{ route('migration.dashboard') }}"><span class="nav-mark"><x-app-icon name="trend" /></span><span>Migration Records</span></a>
                        <a class="@if(request()->routeIs('spatial.index')) active @endif" href="{{ route('spatial.index') }}"><span class="nav-mark"><x-app-icon name="map" /></span><span>Household Map</span></a>
                    @else
                        <a class="@if(request()->routeIs('resident.document-requests.create')) active @endif" href="{{ route('resident.document-requests.create') }}"><span class="nav-mark"><x-app-icon name="form" /></span><span>Request a Document</span></a>
                        <a class="@if(request()->routeIs('resident.document-requests.index')) active @endif" href="{{ route('resident.document-requests.index') }}"><span class="nav-mark"><x-app-icon name="document" /></span><span>My Requests</span></a>
                    @endif
                    @if(auth()->user()->hasAnyRole([App\Models\User::ROLE_BARANGAY, App\Models\User::ROLE_MUNICIPAL_LGU]))
                        <div class="side-section">Reports &amp; insights</div>
                        <a class="{{ request()->routeIs('reports.population*') ? 'active' : '' }}" href="{{ route('reports.population') }}"><span class="nav-mark"><x-app-icon name="document" /></span><span>Population Reports</span></a>
                        <a class="{{ request()->routeIs('reports.migration*') ? 'active' : '' }}" href="{{ route('reports.migration') }}"><span class="nav-mark"><x-app-icon name="document" /></span><span>Migration Reports</span></a>
                        <a class="{{ request()->routeIs('analysis.forecast-readiness') ? 'active' : '' }}" href="{{ route('analysis.forecast-readiness') }}"><span class="nav-mark"><x-app-icon name="trend" /></span><span>Forecast Readiness</span></a>
                    @endif
                </nav>

                <div class="sidebar-spacer"></div>

                <div class="sidebar-account">
                    <span class="account-avatar">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                    <div>
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>{{ auth()->user()->roleLabel() }}</span>
                        @if (auth()->user()->barangay)
                            <small>Barangay {{ auth()->user()->barangay->name }}</small>
                        @endif
                    </div>
                </div>

                <form class="sidebar-logout" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"><span class="nav-mark"><x-app-icon name="logout" /></span><span>Sign out</span></button>
                </form>
            </aside>

            <button class="sidebar-backdrop" type="button" aria-label="Close navigation" data-sidebar-close></button>

            <div class="app-main min-w-0 bg-slate-50">
                <header class="app-header border-b border-slate-200 bg-white">
                    <div class="app-header-primary">
                        <button class="desktop-sidebar-button" type="button" aria-label="Collapse navigation" aria-controls="app-sidebar" aria-expanded="true" data-sidebar-collapse>
                            <x-app-icon name="menu" />
                        </button>
                        <button class="mobile-menu-button" type="button" aria-label="Open navigation" aria-controls="app-sidebar" aria-expanded="false" data-sidebar-toggle>
                            <x-app-icon name="menu" />
                        </button>
                        <div>
                        <span class="app-header-kicker">Municipality of Tomas Oppus</span>
                            <strong>{{ auth()->user()->roleLabel() }} workspace</strong>
                        </div>
                    </div>
                    <div class="app-header-meta">
                        <span class="system-status"><x-app-icon name="shield" /> Authorized workspace</span>
                        <time class="header-date" datetime="{{ now('Asia/Manila')->toDateString() }}">{{ now('Asia/Manila')->format('F d, Y') }}</time>
                        @if(auth()->user()->hasAnyRole([App\Models\User::ROLE_BARANGAY, App\Models\User::ROLE_MUNICIPAL_LGU]))
                            <a class="header-user" href="{{ route('profile.edit') }}" title="My Profile" aria-label="My Profile: {{ auth()->user()->name }}">{{ auth()->user()->name }}</a>
                        @else
                            <span class="header-user">{{ auth()->user()->name }}</span>
                        @endif
                    </div>
                </header>

                <main class="app-content min-w-0" id="main-content">
                    <nav class="workspace-location" aria-label="Page location" hidden data-page-location>
                        <a href="{{ route('dashboard') }}">Workspace</a>
                        <span aria-hidden="true">/</span>
                        <span aria-current="page" data-page-location-title></span>
                    </nav>
                    @yield('content')
                </main>

                <footer class="app-footer @hasSection('footer-reminder') app-footer-with-reminder @endif">
                    @yield('footer-reminder')
                    <span>&copy; {{ now()->year }} Municipal Government of Tomas Oppus</span>
                    <span>RBIM &middot; Official LGU Information System</span>
                </footer>
            </div>
        </div>

        @include('assistant.widget')
    @endguest

    @stack('scripts')
    @auth
        <script>
            (() => {
                const body = document.body;
                const toggle = document.querySelector('[data-sidebar-toggle]');
                const close = document.querySelector('[data-sidebar-close]');
                const collapse = document.querySelector('[data-sidebar-collapse]');
                const sidebar = document.getElementById('app-sidebar');
                const workspace = document.querySelector('.app-main');
                const syncCollapse = () => {
                    const collapsed = body.classList.contains('sidebar-collapsed');
                    collapse?.setAttribute('aria-expanded', String(!collapsed));
                    collapse?.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
                };
                try {
                    const saved = localStorage.getItem('rbim.sidebar.collapsed');
                    if (saved !== null) body.classList.toggle('sidebar-collapsed', saved === 'true');
                } catch (_) {}
                syncCollapse();
                const pageTitle = document.querySelector('#main-content h1')?.textContent.trim();
                if (pageTitle) {
                    document.querySelector('[data-page-location-title]').textContent = pageTitle;
                    document.querySelector('[data-page-location]').hidden = false;
                    document.title = `${pageTitle} | RBIM · Tomas Oppus`;
                }
                document.querySelectorAll('.side-nav a').forEach((link) => {
                    link.setAttribute('aria-label', link.textContent.trim());
                    link.title = link.textContent.trim();
                    if (link.classList.contains('active')) link.setAttribute('aria-current', 'page');
                });
                const setOpen = (open) => {
                    const wasOpen = body.classList.contains('sidebar-is-open');
                    body.classList.toggle('sidebar-is-open', open);
                    toggle?.setAttribute('aria-expanded', String(open));
                    sidebar.inert = window.innerWidth <= 960 && !open;
                    workspace.inert = open && window.innerWidth <= 960;
                    if (open) sidebar.querySelector('a')?.focus();
                    else if (wasOpen) toggle?.focus();
                };

                toggle?.addEventListener('click', () => setOpen(!body.classList.contains('sidebar-is-open')));
                collapse?.addEventListener('click', () => {
                    const isCollapsed = body.classList.toggle('sidebar-collapsed');
                    syncCollapse();
                    try { localStorage.setItem('rbim.sidebar.collapsed', String(isCollapsed)); } catch (_) {}
                });
                close?.addEventListener('click', () => setOpen(false));
                document.addEventListener('keydown', (event) => event.key === 'Escape' && setOpen(false));
                document.addEventListener('keydown', (event) => {
                    if (event.key !== 'Tab' || !body.classList.contains('sidebar-is-open')) return;
                    const items = [...sidebar.querySelectorAll('a,button')].filter(el => el.getClientRects().length && !el.disabled);
                    const first = items[0], last = items[items.length - 1];
                    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
                    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
                });
                const syncViewport = () => {
                    if (window.innerWidth > 960) setOpen(false);
                    else sidebar.inert = !body.classList.contains('sidebar-is-open');
                };
                window.addEventListener('resize', syncViewport);
                syncViewport();
            })();
        </script>
    @endauth
</body>
</html>
