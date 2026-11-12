@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/portal-home.css') }}?v={{ filemtime(public_path('css/portal-home.css')) }}" media="screen">
@endpush

@section('content')
    @php
        $isResident = auth()->user()?->hasRole(\App\Models\User::ROLE_RESIDENT);
        $accountRoute = auth()->check() ? 'dashboard' : 'login';
    @endphp

    <div class="portal-home">
        <section class="portal-home-intro" aria-labelledby="home-title">
            <img class="portal-home-photo" src="{{ asset('images/portal-service-desk.webp') }}" width="1200" height="800" fetchpriority="high" alt="">
            <div class="portal-home-inner portal-home-hero">
                <div class="portal-home-copy">
                    <h1 id="home-title">Smart Barangay Information System</h1>
                    <p>Request barangay documents, track updates, and send concerns to your barangay office in Tomas Oppus.</p>
                    <div class="portal-home-actions">
                        @auth
                            <a class="button" href="{{ route('dashboard') }}">Go to your dashboard <x-app-icon name="arrow-right" /></a>
                        @else
                            <a class="button" href="{{ route('login') }}">Sign in <x-app-icon name="arrow-right" /></a>
                            <a class="button secondary-button" href="{{ route('register') }}">Create an account</a>
                        @endauth
                    </div>
                </div>
            </div>
        </section>

        <section class="portal-home-inner portal-home-section portal-home-directory" id="resident-services" aria-labelledby="services-title">
            <div class="portal-home-section-heading">
                <h2 id="services-title">Resident services</h2>
                @guest
                    <a class="portal-home-text-link" href="{{ route('services') }}">View all services <x-app-icon name="arrow-right" /></a>
                @endguest
            </div>
            <div class="portal-home-services">
                <a class="portal-home-service" href="{{ route($isResident ? 'resident.document-requests.create' : $accountRoute) }}">
                    <x-app-icon name="document" />
                    <div><h3>Request a document</h3><p>Apply for a barangay clearance, residency certificate, or other document.</p></div>
                    <x-app-icon name="arrow-right" />
                </a>
                <a class="portal-home-service" href="{{ route($isResident ? 'resident.document-requests.index' : $accountRoute) }}">
                    <x-app-icon name="clock" />
                    <div><h3>Track your requests</h3><p>Check the status of your applications and payments.</p></div>
                    <x-app-icon name="arrow-right" />
                </a>
                <a class="portal-home-service" href="{{ route($isResident ? 'concerns.create' : $accountRoute) }}">
                    <x-app-icon name="activity" />
                    <div><h3>Submit a concern</h3><p>Contact your barangay office and follow its response.</p></div>
                    <x-app-icon name="arrow-right" />
                </a>
            </div>
        </section>

        <section class="portal-home-inner portal-home-section portal-home-about" id="about" aria-labelledby="about-title">
            <div>
                <h2 id="about-title">Getting started</h2>
                <p>RBIM is the Registry of Barangay Inhabitants Management portal for Tomas Oppus. Your barangay verifies your registration before you can use resident services.</p>
            </div>
            <ol class="portal-home-steps">
                <li><strong>Create an account</strong><span>Select the barangay where you live.</span></li>
                <li><strong>Get verified</strong><span>Your barangay secretary reviews your registration.</span></li>
                <li><strong>Sign in</strong><span>Request documents, check updates, or send a concern.</span></li>
            </ol>
        </section>
    </div>
@endsection
