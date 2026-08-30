<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#123f67">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'RBIM') }} | Municipality of Tomas Oppus</title>
    <link rel="stylesheet" href="{{ asset('css/rbim.css') }}?v={{ filemtime(public_path('css/rbim.css')) }}">
    @stack('head')
</head>
<body @auth class="role-{{ str_replace('_', '-', auth()->user()->role) }}" @endauth>
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
                        <a href="{{ route('home') }}">Home</a>
                        <a href="{{ route('home') }}#about">About</a>
                        <a href="{{ route('home') }}#services">Services</a>
                        <a class="public-login-link" href="{{ route('login') }}">Login</a>
                        <a class="public-register-link" href="{{ route('register') }}">Register</a>
                    </nav>
                </div>
            </header>

            <main class="public-content">
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
                        <span>Authorized public service portal · Data Privacy Act compliant operations</span>
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
            <aside class="app-sidebar" id="app-sidebar">
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

                <div class="side-label">Main Navigation</div>
                <nav class="side-nav" aria-label="Primary navigation">
                    @if (auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU))
                        <a class="@if(request()->routeIs('dashboard.municipal')) active @endif" href="{{ route('dashboard.municipal') }}"><span class="nav-mark"><x-app-icon name="home" /></span><span>Overview</span></a>
                        <a class="@if(request()->routeIs('municipal.barangays.*')) active @endif" href="{{ route('municipal.barangays.index') }}"><span class="nav-mark"><x-app-icon name="directory" /></span><span>Barangay Directory</span></a>
                        <a class="@if(request()->routeIs('migration.dashboard')) active @endif" href="{{ route('migration.dashboard') }}"><span class="nav-mark"><x-app-icon name="trend" /></span><span>Migration Trends</span></a>
                        <a class="@if(request()->routeIs('spatial.index')) active @endif" href="{{ route('spatial.index') }}"><span class="nav-mark"><x-app-icon name="map" /></span><span>Movement Map</span></a>
                    @elseif (auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))
                        <a class="@if(request()->routeIs('dashboard.barangay')) active @endif" href="{{ route('dashboard.barangay') }}"><span class="nav-mark"><x-app-icon name="home" /></span><span>Overview</span></a>
                        <a class="@if(request()->routeIs('barangay.rbi-updates.*')) active @endif" href="{{ route('barangay.rbi-updates.index') }}"><span class="nav-mark"><x-app-icon name="form" /></span><span>RBI Forms</span></a>
                        <a class="@if(request()->routeIs('registry.*')) active @endif" href="{{ route('registry.index') }}"><span class="nav-mark"><x-app-icon name="users" /></span><span>Resident Registry</span></a>
                        <a class="@if(request()->routeIs('migration.dashboard')) active @endif" href="{{ route('migration.dashboard') }}"><span class="nav-mark"><x-app-icon name="trend" /></span><span>Migration Records</span></a>
                        <a class="@if(request()->routeIs('spatial.index')) active @endif" href="{{ route('spatial.index') }}"><span class="nav-mark"><x-app-icon name="map" /></span><span>Household Map</span></a>
                    @else
                        <a class="@if(request()->routeIs('dashboard.resident')) active @endif" href="{{ route('dashboard.resident') }}"><span class="nav-mark"><x-app-icon name="home" /></span><span>My Dashboard</span></a>
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

            <div class="app-main">
                <header class="app-header">
                    <div class="app-header-primary">
                        <button class="mobile-menu-button" type="button" aria-label="Open navigation" aria-controls="app-sidebar" aria-expanded="false" data-sidebar-toggle>
                            <x-app-icon name="menu" />
                        </button>
                        <div>
                        <span class="app-header-kicker">Republic of the Philippines · Local Government Unit</span>
                            <strong>Registry of Barangay Inhabitants Management</strong>
                        </div>
                    </div>
                    <div class="app-header-meta">
                        <span class="system-status"><i></i> System Online</span>
                        <span class="header-date">{{ now()->format('F d, Y') }}</span>
                        <span class="header-user">{{ auth()->user()->name }}</span>
                    </div>
                </header>

                <main class="app-content" id="main-content">
                    @yield('content')
                </main>

                <footer class="app-footer">
                    <span>© {{ now()->year }} Municipal Government of Tomas Oppus</span>
                    <span>RBIM · Official LGU Information System</span>
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
                const setOpen = (open) => {
                    body.classList.toggle('sidebar-is-open', open);
                    toggle?.setAttribute('aria-expanded', String(open));
                };

                toggle?.addEventListener('click', () => setOpen(!body.classList.contains('sidebar-is-open')));
                close?.addEventListener('click', () => setOpen(false));
                document.addEventListener('keydown', (event) => event.key === 'Escape' && setOpen(false));
                window.addEventListener('resize', () => window.innerWidth > 960 && setOpen(false));
            })();
        </script>
    @endauth
</body>
</html>
