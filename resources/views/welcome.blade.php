@extends('layouts.app')

@section('content')
    <section class="public-hero">
        <div class="public-hero-copy">
            <span class="public-eyebrow">Official Digital Services Portal</span>
            <h1>Serbisyong barangay, mas malapit sa mamamayan.</h1>
            <p>Access barangay services, submit document requests, and securely manage resident information across the 29 barangays of Tomas Oppus.</p>
            <div class="public-hero-actions">
                <a class="button" href="{{ route('register') }}">Create Resident Account</a>
                <a class="button public-outline-button" href="{{ route('login') }}">Access Your Account</a>
            </div>
            <div class="public-trust-row">
                <span><strong>29</strong> Barangays</span>
                <span><strong>Secure</strong> Resident Records</span>
                <span><strong>Online</strong> Public Services</span>
            </div>
        </div>
        <div class="public-hero-seal" aria-label="Municipality of Tomas Oppus official seal">
            <div class="hero-seal-frame">
                <img src="{{ asset('images/tomas-oppus-seal.png') }}" alt="Municipality of Tomas Oppus seal">
            </div>
            <div class="hero-office-card">
                <span>Municipal Government</span>
                <strong>Tomas Oppus</strong>
                <small>Southern Leyte · Established 1972</small>
            </div>
        </div>
    </section>

    <section class="public-section" id="services">
        <div class="public-section-head">
            <span class="public-eyebrow">Online Services</span>
            <h2>Barangay services in one secure portal</h2>
            <p>Designed to make local government transactions more accessible, organized, and transparent.</p>
        </div>

        <div class="service-card-grid">
            <article class="service-card">
                <span class="service-icon">DC</span>
                <h3>Document Requests</h3>
                <p>Request barangay clearance, residency, indigency, and other available certifications online.</p>
                <a href="{{ route('register') }}">Register to request <span aria-hidden="true">→</span></a>
            </article>
            <article class="service-card">
                <span class="service-icon">RT</span>
                <h3>Request Tracking</h3>
                <p>View the status and history of your barangay document requests from your resident account.</p>
                <a href="{{ route('login') }}">Track a request <span aria-hidden="true">→</span></a>
            </article>
            <article class="service-card">
                <span class="service-icon">RV</span>
                <h3>Resident Verification</h3>
                <p>Every resident account is verified by the assigned barangay secretary before access is granted.</p>
                <a href="{{ route('register') }}">Start registration <span aria-hidden="true">→</span></a>
            </article>
        </div>
    </section>

    <section class="public-section public-about" id="about">
        <div>
            <span class="public-eyebrow">How Registration Works</span>
            <h2>Simple, secure, and verified by your barangay</h2>
            <p>RBIM connects residents with their local barangay while protecting access to municipal records and services.</p>
        </div>
        <ol class="public-steps">
            <li><span>01</span><div><strong>Create an account</strong><p>Enter your personal account details and select your barangay.</p></div></li>
            <li><span>02</span><div><strong>Wait for verification</strong><p>Your barangay secretary reviews and confirms your registration.</p></div></li>
            <li><span>03</span><div><strong>Access resident services</strong><p>Once approved, sign in and submit or track barangay requests.</p></div></li>
        </ol>
    </section>

    <section class="public-cta">
        <div>
            <span>Resident of Tomas Oppus?</span>
            <h2>Register with your barangay today.</h2>
        </div>
        <a class="button" href="{{ route('register') }}">Create an Account</a>
    </section>
@endsection
