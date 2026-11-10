@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/home-landing.css') }}?v={{ filemtime(public_path('css/home-landing.css')) }}" media="screen">
    <link rel="stylesheet" href="{{ asset('css/home-motion.css') }}?v={{ filemtime(public_path('css/home-motion.css')) }}" media="screen">
@endpush

@section('content')
<div id="home-landing" class="civic-home">
    <section class="landing-hero" aria-labelledby="home-title">
        <div class="landing-hero-decoration" aria-hidden="true"></div>
        <div class="home-ambient home-ambient-teal" aria-hidden="true"></div>
        <div class="home-ambient home-ambient-gold" aria-hidden="true"></div>
        <button class="home-motion-toggle" type="button" data-home-motion-toggle aria-pressed="false" hidden><span data-motion-label>Pause motion</span><span data-motion-symbol aria-hidden="true">Ⅱ</span></button>
        <div class="landing-container landing-hero-grid">
            <div class="landing-copy">
                <p class="landing-eyebrow"><span aria-hidden="true"></span> Official Digital Services Portal</p>
                <h1 id="home-title">Smart Barangay Information System</h1>
                <p class="landing-subtitle">for Population and Governance Management</p>
                <p class="landing-description">Your barangay, closer to you. Request documents, keep track of services, and connect with your local government in one place.</p>
                <div class="landing-actions">
                    @auth
                        <a href="{{ route('dashboard') }}" class="landing-button landing-button-primary">Go to your dashboard <x-app-icon name="arrow-right" /></a>
                    @else
                        <a href="{{ route('register') }}" class="landing-button landing-button-primary">Create an account <x-app-icon name="arrow-right" /></a>
                    @endauth
                    <a href="#resident-services" class="landing-button landing-button-secondary">Explore services <x-app-icon name="directory" /></a>
                </div>
                <div class="landing-trust">
                    <div class="landing-barangays"><strong>29</strong><span>barangays.<br>One connected community.</span></div>
                    <div class="landing-trust-note"><x-app-icon name="shield" /><span>Barangay-verified accounts<br>and protected resident records</span></div>
                </div>
            </div>
            <aside class="landing-community" aria-label="Tomas Oppus resident services">
                <div class="landing-illustration">
                    <div class="landing-illustration-caption"><span class="landing-small-label">Built around our community</span><h2>Local services.<br>A little closer.</h2></div>
                    <img src="{{ asset('images/civic-services-sticker.png') }}" alt="" width="1536" height="1024" decoding="async" fetchpriority="high">
                </div>
                <ul class="landing-service-highlights" aria-label="Resident services">
                    <li><x-app-icon name="document" /><span>Documents</span></li>
                    <li><x-app-icon name="clock" /><span>Request updates</span></li>
                    <li><x-app-icon name="users" /><span>Resident concerns</span></li>
                </ul>
            </aside>
        </div>
    </section>

    <section id="resident-services" class="landing-container landing-services" aria-labelledby="landing-services-title">
        <div class="landing-section-heading"><div><span class="landing-kicker">Here to help you</span><h2 id="landing-services-title">What would you like to do?</h2></div><span class="landing-section-note">Start with the service you need.</span></div>
        <div class="landing-service-grid">
            <a class="landing-service-card" href="{{ auth()->check() ? route('dashboard') : route('register') }}">
                <span class="landing-service-top"><span class="landing-service-icon"><x-app-icon name="document" /></span><span class="landing-service-number">01</span></span>
                <h3>Request a document</h3><p>Get started with barangay clearances, certifications, and other document requests.</p><span class="landing-service-action">{{ auth()->check() ? 'Open your dashboard' : 'Create your account' }} <x-app-icon name="arrow-right" /></span>
            </a>
            <a class="landing-service-card landing-service-violet" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
                <span class="landing-service-top"><span class="landing-service-icon"><x-app-icon name="clock" /></span><span class="landing-service-number">02</span></span>
                <h3>Track your requests</h3><p>Follow your submitted requests and see the latest updates from your barangay.</p><span class="landing-service-action">{{ auth()->check() ? 'View your dashboard' : 'Sign in to track' }} <x-app-icon name="arrow-right" /></span>
            </a>
            <a class="landing-service-card landing-service-teal" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
                <span class="landing-service-top"><span class="landing-service-icon"><x-app-icon name="users" /></span><span class="landing-service-number">03</span></span>
                <h3>Connect with your barangay</h3><p>Share a concern, send a follow-up, and stay connected with your local barangay.</p><span class="landing-service-action">{{ auth()->check() ? 'Open your dashboard' : 'Sign in to connect' }} <x-app-icon name="arrow-right" /></span>
            </a>
        </div>
    </section>

    <section id="about" class="landing-container landing-about" aria-labelledby="landing-about-title">
        <div class="landing-about-intro"><span class="landing-kicker">From registration to public service</span><h2 id="landing-about-title">A few simple steps.<br>A more connected barangay.</h2><p>The Registry of Barangay Inhabitants Management (RBIM) portal brings resident information and barangay services together, with access verified by your local barangay.</p><a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="landing-text-link">{{ auth()->check() ? 'Continue to your dashboard' : 'Get started today' }} <x-app-icon name="arrow-right" /></a></div>
        <ol class="landing-steps">
            <li><span class="landing-step-number">01</span><div><h3>Create your account</h3><p>Enter your details and select the barangay where you reside.</p></div><x-app-icon name="profile" /></li>
            <li><span class="landing-step-number">02</span><div><h3>Get verified by your barangay</h3><p>Your barangay secretary reviews and confirms your resident registration.</p></div><x-app-icon name="shield" /></li>
            <li><span class="landing-step-number">03</span><div><h3>Access your resident services</h3><p>Once approved, submit requests, view updates, and raise concerns.</p></div><x-app-icon name="check" /></li>
        </ol>
    </section>

    <section class="landing-container landing-bottom-wrap" aria-label="Account access">
        <div class="landing-bottom"><span class="landing-bottom-icon"><x-app-icon name="building" /></span><div><span class="landing-kicker">Better connected. Better served.</span><h2>Your next barangay transaction starts here.</h2></div><a class="landing-button landing-button-primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">{{ auth()->check() ? 'Open dashboard' : 'Sign in to your account' }} <x-app-icon name="arrow-right" /></a></div>
    </section>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/home-motion.js') }}?v={{ filemtime(public_path('js/home-motion.js')) }}" defer></script>
@endpush
