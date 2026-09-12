@extends('layouts.app')

@section('content')
<div class="civic-home">
    <section class="civic-home-hero relative isolate overflow-hidden bg-blue-950 text-white" aria-labelledby="home-title">
        <div class="civic-home-pattern pointer-events-none absolute inset-0" aria-hidden="true"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-6 pb-24 pt-14 sm:px-8 sm:pt-20 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,.8fr)] lg:gap-16 lg:pb-28">
            <div class="min-w-0">
                <p class="mb-6 flex items-center gap-3 text-[11px] font-semibold uppercase tracking-[.18em] text-amber-200 sm:text-xs">
                    <span class="h-px w-8 bg-amber-300" aria-hidden="true"></span>
                    Official Digital Services Portal
                </p>
                <h1 id="home-title" class="m-0 max-w-2xl font-serif text-[2.6rem] font-bold leading-[1.1] tracking-tight text-white sm:text-6xl lg:text-[4rem]">
                    Sabay sa pagbabago,<br>
                    tungo sa mas<br class="hidden sm:block">
                    <span class="text-amber-200">magandang bukas.</span>
                </h1>
                <p class="mb-0 mt-6 max-w-xl text-base leading-7 text-blue-100 sm:text-lg">
                    Access barangay services, submit document requests, and securely manage resident information across the 29 barangays of Tomas Oppus.
                </p>
                <div class="mt-8 flex flex-col gap-3 min-[420px]:flex-row">
                    <a href="{{ route('register') }}" class="civic-home-primary inline-flex min-h-12 items-center justify-center gap-3 rounded-lg border border-amber-200 bg-amber-200 px-6 py-3 text-sm font-semibold text-blue-950 shadow-sm transition-colors hover:border-amber-100 hover:bg-amber-100">
                        Create an account <x-app-icon name="arrow-right" />
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-white/40 bg-white/5 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-white/15">
                        Sign in to your account
                    </a>
                </div>
                <div class="mt-9 flex flex-wrap gap-x-7 gap-y-3 border-t border-white/15 pt-5 text-xs text-blue-100 sm:gap-x-10">
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
            <a href="{{ route('register') }}" class="civic-home-service group flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-md transition duration-200 hover:-translate-y-1 hover:border-blue-300 hover:shadow-lg sm:p-6">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="form" /></span>
                <span class="min-w-0 flex-1"><strong class="block text-base font-semibold text-blue-950">Request a document</strong><span class="mt-1 block text-sm leading-6 text-slate-600">Register to access barangay document services.</span></span>
                <span class="mt-1 text-blue-600" aria-hidden="true"><x-app-icon name="arrow-right" /></span>
            </a>
            <a href="{{ route('login') }}" class="civic-home-service group flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-md transition duration-200 hover:-translate-y-1 hover:border-blue-300 hover:shadow-lg sm:p-6">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="activity" /></span>
                <span class="min-w-0 flex-1"><strong class="block text-base font-semibold text-blue-950">Track your requests</strong><span class="mt-1 block text-sm leading-6 text-slate-600">Sign in to view updates from your barangay.</span></span>
                <span class="mt-1 text-blue-600" aria-hidden="true"><x-app-icon name="arrow-right" /></span>
            </a>
            <a href="{{ route('services') }}" class="civic-home-service group flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-md transition duration-200 hover:-translate-y-1 hover:border-blue-300 hover:shadow-lg sm:p-6">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="directory" /></span>
                <span class="min-w-0 flex-1"><strong class="block text-base font-semibold text-blue-950">Explore online services</strong><span class="mt-1 block text-sm leading-6 text-slate-600">Find the service you need before getting started.</span></span>
                <span class="mt-1 text-blue-600" aria-hidden="true"><x-app-icon name="arrow-right" /></span>
            </a>
        </div>
    </section>

    <section id="about" class="scroll-mt-8 px-6 py-16 sm:px-8 sm:py-20">
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[minmax(0,.9fr)_minmax(0,1.1fr)] lg:gap-20">
            <div>
                <span class="text-xs font-semibold uppercase tracking-[.16em] text-blue-700">How Registration Works</span>
                <h2 class="mb-0 mt-4 max-w-lg text-3xl font-bold leading-tight tracking-tight text-blue-950 sm:text-4xl">Simple, secure, and verified by your barangay</h2>
                <p class="mb-0 mt-5 max-w-lg text-base leading-7 text-slate-600">RBIM connects residents with their local barangay while protecting access to municipal records and services.</p>
                <div class="mt-7 flex max-w-lg items-start gap-3 rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm leading-6 text-blue-900">
                    <span class="mt-1 shrink-0"><x-app-icon name="shield" /></span>
                    <p class="m-0">Your barangay secretary verifies your registration before you can access resident services.</p>
                </div>
            </div>
            <ol class="m-0 grid list-none gap-0 p-0">
                <li class="flex gap-5 border-b border-slate-200 pb-6">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full border border-blue-200 bg-white text-sm font-bold text-blue-800">01</span>
                    <div><h3 class="m-0 text-lg font-semibold text-blue-950">Create an account</h3><p class="mb-0 mt-2 text-sm leading-6 text-slate-600">Enter your personal account details and select your barangay.</p></div>
                </li>
                <li class="flex gap-5 border-b border-slate-200 py-6">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full border border-blue-200 bg-white text-sm font-bold text-blue-800">02</span>
                    <div><h3 class="m-0 text-lg font-semibold text-blue-950">Wait for verification</h3><p class="mb-0 mt-2 text-sm leading-6 text-slate-600">Your barangay secretary reviews and confirms your registration.</p></div>
                </li>
                <li class="flex gap-5 pt-6">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full border border-blue-200 bg-white text-sm font-bold text-blue-800">03</span>
                    <div><h3 class="m-0 text-lg font-semibold text-blue-950">Access resident services</h3><p class="mb-0 mt-2 text-sm leading-6 text-slate-600">Once approved, sign in and submit or track barangay requests.</p></div>
                </li>
            </ol>
        </div>
    </section>

    <section class="px-6 pb-16 sm:px-8 sm:pb-20" aria-labelledby="home-register-title">
        <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-6 rounded-2xl border border-blue-200 bg-white p-7 shadow-sm md:flex-row md:items-center sm:p-10">
            <div><span class="text-xs font-semibold uppercase tracking-[.16em] text-blue-700">Resident of Tomas Oppus?</span><h2 id="home-register-title" class="mb-0 mt-3 text-2xl font-bold tracking-tight text-blue-950 sm:text-3xl">Register with your barangay today.</h2></div>
            <a href="{{ route('register') }}" class="inline-flex min-h-12 shrink-0 items-center justify-center gap-3 rounded-lg bg-blue-700 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-blue-800">Create an Account <x-app-icon name="arrow-right" /></a>
        </div>
    </section>
</div>
@endsection
