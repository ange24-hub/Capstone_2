@extends('layouts.app')

@section('content')
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
@endsection
