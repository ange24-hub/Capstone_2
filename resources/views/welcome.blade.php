@extends('layouts.app')

@section('content')
    <div class="portal-home">
        <section class="portal-home-intro" aria-labelledby="home-title">
            <div class="portal-home-inner">
                <h1 id="home-title">Smart Barangay Information System</h1>
                <p>Request barangay documents, track your requests, and submit concerns to your barangay office.</p>
                <div class="portal-home-actions">
                    @auth
                        <a class="button" href="{{ route('dashboard') }}">Go to your dashboard</a>
                    @else
                        <a class="button" href="{{ route('login') }}">Sign in</a>
                        <a class="button secondary-button" href="{{ route('register') }}">Create an account</a>
                    @endauth
                </div>
            </div>
        </section>

        <section class="portal-home-inner portal-home-section" id="resident-services" aria-labelledby="services-title">
            <h2 id="services-title">Resident services</h2>
            <div class="portal-home-services">
                <article>
                    <x-app-icon name="document" />
                    <h3>Request a document</h3>
                    <p>Apply for barangay clearance, residency, indigency, and other available certifications.</p>
                </article>
                <article>
                    <x-app-icon name="clock" />
                    <h3>Track your requests</h3>
                    <p>View request status, payment history, and updates from your barangay.</p>
                </article>
                <article>
                    <x-app-icon name="users" />
                    <h3>Submit a concern</h3>
                    <p>Send a concern to your barangay office and follow its progress.</p>
                </article>
            </div>
        </section>

        <section class="portal-home-inner portal-home-section" id="about" aria-labelledby="about-title">
            <h2 id="about-title">About the portal</h2>
            <p>The Registry of Barangay Inhabitants Management supports resident registration and barangay services in Tomas Oppus.</p>
            <ol class="portal-home-steps">
                <li>Create an account and select the barangay where you live.</li>
                <li>Wait for your barangay secretary to verify your registration.</li>
                <li>Sign in to submit requests and use resident services.</li>
            </ol>
        </section>
    </div>
@endsection
