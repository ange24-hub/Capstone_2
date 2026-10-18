@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/home-glass.css') }}?v={{ filemtime(public_path('css/home-glass.css')) }}" media="screen">
@endpush

@section('content')
<div class="civic-home">
    <section class="civic-home-hero relative isolate overflow-hidden bg-blue-950 text-white" aria-labelledby="home-title">
        <div class="civic-home-pattern pointer-events-none absolute inset-0" aria-hidden="true"></div>
        <div class="home-hero-layout relative mx-auto grid items-center">
            <div class="home-hero-copy min-w-0">
                <p class="mb-6 flex items-center gap-3 text-[11px] font-semibold uppercase tracking-[.18em] text-amber-200 sm:text-xs">
                    <span class="h-px w-8 bg-amber-300" aria-hidden="true"></span>
                    Official Digital Services Portal
                </p>
                <h1 id="home-title">
                    Smart Barangay Information System
                    <span class="home-title-secondary">for Population and Governance Management</span>
                </h1>
                <p class="mb-0 mt-6 max-w-xl text-base leading-7 text-blue-100 sm:text-lg">
                    Access barangay services, submit document requests, and securely manage resident information across the 29 barangays of Tomas Oppus.
                </p>
                <div class="home-hero-actions mt-8 flex flex-col gap-3 min-[420px]:flex-row">
                    @auth
                    <a href="{{ route('dashboard') }}" class="civic-home-primary inline-flex min-h-12 items-center justify-center gap-3 rounded-lg border border-amber-200 bg-amber-200 px-6 py-3 text-sm font-semibold text-blue-950 shadow-sm transition-colors hover:border-amber-100 hover:bg-amber-100">
                        Go to your dashboard <x-app-icon name="arrow-right" />
                    </a>
                    @else
                    <a href="{{ route('register') }}" class="civic-home-primary inline-flex min-h-12 items-center justify-center gap-3 rounded-lg border border-amber-200 bg-amber-200 px-6 py-3 text-sm font-semibold text-blue-950 shadow-sm transition-colors hover:border-amber-100 hover:bg-amber-100">
                        Create an account <x-app-icon name="arrow-right" />
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-white/40 bg-white/5 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-white/15">
                        Sign in to your account
                    </a>
                    @endauth
                </div>
                <div class="home-hero-trust mt-9 flex flex-wrap gap-x-7 gap-y-3 border-t border-white/15 pt-5 text-xs text-blue-100 sm:gap-x-10">
                    <span><strong class="mr-1 text-base font-semibold text-white">29</strong> Barangays</span>
                    <span class="inline-flex items-center gap-2"><x-app-icon name="shield" /> Secure resident records</span>
                    <span class="inline-flex items-center gap-2"><x-app-icon name="document" /> Online public services</span>
                </div>
            </div>
            <div class="civic-home-identity relative flex items-center justify-center gap-5 text-left lg:flex-col lg:gap-0 lg:text-center">
                <div class="civic-home-seal relative z-10 size-24 shrink-0 overflow-hidden rounded-full border-[4px] border-white/30 bg-white shadow-2xl sm:size-36 lg:size-80 lg:border-[6px]">
                    <img src="{{ asset('images/tomas-oppus-seal.png') }}" alt="Municipality of Tomas Oppus seal" class="size-full scale-[1.24] object-cover">
                </div>
                <div class="relative z-10 min-w-0 lg:mt-7">
                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-[.24em] text-blue-200 sm:text-xs">Municipal Government</p>
                    <strong class="block font-serif text-2xl font-bold text-white sm:text-3xl lg:text-4xl">Tomas Oppus</strong>
                    <p class="mb-0 mt-2 text-sm text-blue-100">Southern Leyte &middot; Established 1972</p>
                </div>
            </div>
        </div>
    </section>

    <section class="relative z-10 mx-auto -mt-8 max-w-7xl px-6 sm:px-8" aria-label="Online service shortcuts">
        <div class="grid gap-4 md:grid-cols-3">
            <a href="{{ route(auth()->check() ? 'dashboard' : 'register') }}" class="civic-home-service group flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-md transition duration-200 hover:-translate-y-1 hover:border-blue-300 hover:shadow-lg sm:p-6">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="form" /></span>
                <span class="min-w-0 flex-1"><strong class="block text-base font-semibold text-blue-950">Request a document</strong><span class="mt-1 block text-sm leading-6 text-slate-600">{{ auth()->check() ? 'Open your dashboard to access barangay document services.' : 'Register to access barangay document services.' }}</span></span>
                <span class="mt-1 text-blue-600" aria-hidden="true"><x-app-icon name="arrow-right" /></span>
            </a>
            <a href="{{ route(auth()->check() ? 'dashboard' : 'login') }}" class="civic-home-service group flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-md transition duration-200 hover:-translate-y-1 hover:border-blue-300 hover:shadow-lg sm:p-6">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="activity" /></span>
                <span class="min-w-0 flex-1"><strong class="block text-base font-semibold text-blue-950">Track your requests</strong><span class="mt-1 block text-sm leading-6 text-slate-600">{{ auth()->check() ? 'Open your dashboard to view updates from your barangay.' : 'Sign in to view updates from your barangay.' }}</span></span>
                <span class="mt-1 text-blue-600" aria-hidden="true"><x-app-icon name="arrow-right" /></span>
            </a>
            <a href="{{ route(auth()->check() ? 'dashboard' : 'services') }}" class="civic-home-service group flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-md transition duration-200 hover:-translate-y-1 hover:border-blue-300 hover:shadow-lg sm:p-6">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="directory" /></span>
                <span class="min-w-0 flex-1"><strong class="block text-base font-semibold text-blue-950">Explore online services</strong><span class="mt-1 block text-sm leading-6 text-slate-600">Find the service you need before getting started.</span></span>
                <span class="mt-1 text-blue-600" aria-hidden="true"><x-app-icon name="arrow-right" /></span>
            </a>
        </div>
    </section>

    <section id="about" class="home-registration" aria-labelledby="home-registration-title">
        <div class="home-registration-card">
            <div class="home-registration-copy">
                <span class="home-section-eyebrow">How Registration Works</span>
                <h2 id="home-registration-title">Simple, secure, and verified by your barangay.</h2>
                <p class="home-registration-description">RBIM connects residents with their local barangay while protecting access to municipal records and services.</p>
                <div class="home-verification-note">
                    <span class="home-verification-icon" aria-hidden="true"><x-app-icon name="shield" /></span>
                    <div>
                        <strong>Verified by your barangay</strong>
                        <p>Your barangay secretary confirms your registration before you can access resident services.</p>
                    </div>
                </div>
            </div>
            <ol class="home-registration-steps">
                <li class="home-registration-step">
                    <span class="home-step-number" aria-hidden="true">01</span>
                    <div><h3>Create an account</h3><p>Enter your personal account details and select your barangay.</p></div>
                </li>
                <li class="home-registration-step">
                    <span class="home-step-number" aria-hidden="true">02</span>
                    <div><h3>Wait for verification</h3><p>Your barangay secretary reviews and confirms your registration.</p></div>
                </li>
                <li class="home-registration-step">
                    <span class="home-step-number" aria-hidden="true">03</span>
                    <div><h3>Access resident services</h3><p>Once approved, sign in and submit or track barangay requests.</p></div>
                </li>
            </ol>
        </div>
    </section>

    <section class="home-register" aria-labelledby="home-register-title">
        <div class="home-register-card">
            <div>
                <span class="home-section-eyebrow">Resident of Tomas Oppus?</span>
                <h2 id="home-register-title">Register with your barangay today.</h2>
                <p>Create your account to get started with local services.</p>
            </div>
            <a href="{{ route(auth()->check() ? 'dashboard' : 'register') }}" class="home-register-button">{{ auth()->check() ? 'Go to Dashboard' : 'Create an Account' }} <x-app-icon name="arrow-right" /></a>
        </div>
    </section>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/home-motion.js') }}?v={{ filemtime(public_path('js/home-motion.js')) }}" defer></script>
@endpush
