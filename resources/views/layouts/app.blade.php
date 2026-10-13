<!doctype html>
<html lang="en" class="municipal-ui">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#142d4e">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'RBIM') }} | Municipality of Tomas Oppus</title>
    <link rel="stylesheet" href="{{ asset('css/professional-workspace.css') }}?v={{ filemtime(public_path('css/professional-workspace.css')) }}" media="screen">
    @if(request()->routeIs('barangay.document-requests.index'))
        <link rel="stylesheet" href="{{ asset('css/document-requests.css') }}?v={{ filemtime(public_path('css/document-requests.css')) }}" media="screen">
    @endif
    @if(request()->routeIs('register'))
        <link rel="stylesheet" href="{{ asset('css/registration.css') }}?v={{ filemtime(public_path('css/registration.css')) }}" media="screen">
    @endif
    @if(request()->routeIs('dashboard.barangay', 'barangay.resident-approvals.index', 'barangay.document-requests.index', 'barangay.rbi-updates.index'))
        <link rel="stylesheet" href="{{ asset('css/barangay-dashboard-refinement.css') }}?v={{ filemtime(public_path('css/barangay-dashboard-refinement.css')) }}" media="screen">
    @endif
    @if(auth()->check() && auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU))
        <link rel="stylesheet" href="{{ asset('css/municipal-admin-theme.css') }}?v={{ filemtime(public_path('css/municipal-admin-theme.css')) }}" media="screen">
    @endif
    <link rel="stylesheet" href="{{ asset('css/rbim.css') }}?v={{ filemtime(public_path('css/rbim.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/portal-template.css') }}?v={{ filemtime(public_path('css/portal-template.css')) }}" media="screen">
    <link rel="stylesheet" href="{{ asset('css/workspace-headers.css') }}?v={{ filemtime(public_path('css/workspace-headers.css')) }}" media="screen">
    <link rel="stylesheet" href="{{ asset('css/workspace-sidebar.css') }}?v={{ filemtime(public_path('css/workspace-sidebar.css')) }}" media="screen">
    <link rel="stylesheet" href="{{ asset('css/profile-navigation.css') }}?v={{ filemtime(public_path('css/profile-navigation.css')) }}" media="screen">
    <link rel="stylesheet" href="{{ asset('css/print.css') }}?v={{ filemtime(public_path('css/print.css')) }}">
    @vite('resources/css/app.css')
    @stack('head')
</head>
<body @auth class="professional-shell {{ !request()->routeIs('barangay.rbi-updates.*', 'rbi-updates.*') ? 'professional-content' : '' }} role-{{ str_replace('_', '-', auth()->user()->role) }} {{ request()->routeIs('dashboard.barangay', 'dashboard.municipal', 'dashboard.resident') ? 'dashboard-workspace' : '' }} {{ request()->routeIs('barangay.registry.*', 'registry.*', 'barangay.document-requests.index', 'migration.dashboard', 'spatial.index') ? 'wide-workspace' : '' }} {{ !request()->routeIs('dashboard.*') ? 'focused-workspace' : '' }}" @else class="professional-public" @endauth>
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
                        <span>Authorized public service portal · Municipality of Tomas Oppus</span>
                    </div>
                </div>
                <div class="public-footer-bottom">
                    <span>© {{ now()->year }} Municipality of Tomas Oppus. All rights reserved.</span>
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
                @include('layouts.navigation')
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
                        <strong>{{ auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU) ? 'Municipal administrator' : auth()->user()->roleLabel() }}</strong>
                    </div>
                    <div class="app-header-meta">
                        <time class="header-date" data-ph-header-date aria-label="Device local date and time"></time>
                        @if(auth()->user()->hasAnyRole([App\Models\User::ROLE_BARANGAY, App\Models\User::ROLE_MUNICIPAL_LGU]))
                            <a class="header-user profile-navigation-trigger" href="{{ route('profile.edit') }}" title="Profile Info" aria-label="Profile Info: {{ auth()->user()->name }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif>
                                <span class="profile-navigation-avatar" aria-hidden="true">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                                <span class="profile-navigation-copy"><span class="profile-navigation-name">{{ auth()->user()->name }}</span><span class="profile-navigation-label">Profile Info</span></span>
                                <x-app-icon name="arrow-right" class="profile-navigation-arrow" />
                            </a>
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
                    <span>© {{ now()->year }} Municipal Government of Tomas Oppus</span>
                </footer>
            </div>
        </div>
        @include('assistant.widget')
    @endguest
    @stack('scripts')
    @auth
        <script src="{{ asset('js/philippine-clock.js') }}?v={{ filemtime(public_path('js/philippine-clock.js')) }}" defer></script>
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
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker
                .register('/service-worker.js')
                .then(function (registration) {
                    console.log(
                        'Service Worker registered:',
                        registration.scope
                    );
                })
                .catch(function (error) {
                    console.error(
                        'Service Worker registration failed:',
                        error
                    );
                });
        });
    }
</script>
</body>
</html>
