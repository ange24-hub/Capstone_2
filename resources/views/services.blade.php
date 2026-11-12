@extends('layouts.app')

@section('content')
    <section class="public-section" id="services">
        <div class="public-section-head mb-8 text-center ">
            <span class="public-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700 ">Online Services</span>
            <h2>Barangay services in one secure portal</h2>
            <p>Designed to make local government transactions more accessible, organized, and transparent.</p>
        </div>

        <div class="service-card-grid grid grid-cols-1 gap-6 md:grid-cols-3 ">
            <article class="service-card rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md ">
                <span class="service-icon rounded-xl bg-blue-50 text-blue-800"><x-app-icon name="document" /></span>
                <h3>Document Requests</h3>
                <p>Request barangay clearance, residency, indigency, and other available certifications online.</p>
                <a href="{{ route('register') }}">Register to request <span aria-hidden="true">→</span></a>
            </article>
            <article class="service-card rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md ">
                <span class="service-icon rounded-xl bg-blue-50 text-blue-800"><x-app-icon name="clock" /></span>
                <h3>Request Tracking</h3>
                <p>View the status and history of your barangay document requests from your resident account.</p>
                <a href="{{ route('login') }}">Track a request <span aria-hidden="true">→</span></a>
            </article>
            <article class="service-card rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md ">
                <span class="service-icon rounded-xl bg-blue-50 text-blue-800"><x-app-icon name="shield" /></span>
                <h3>Resident Verification</h3>
                <p>Every resident account is verified by the assigned barangay secretary before access is granted.</p>
                <a href="{{ route('register') }}">Start registration <span aria-hidden="true">→</span></a>
            </article>
        </div>
    </section>
@endsection
