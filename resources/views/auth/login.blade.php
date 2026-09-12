@extends('layouts.app')

@section('content')
    <div class="portal-login-layout">
        <aside class="portal-login-intro bg-blue-900 bg-none text-white" aria-labelledby="portal-login-title">
            <span class="portal-login-label">Municipal Government</span>
            <span class="seal-crop portal-login-seal"><img src="{{ asset('images/tomas-oppus-seal.png') }}" alt="Municipality of Tomas Oppus seal"></span>
            <h2 id="portal-login-title">Tomas Oppus</h2>
            <p class="portal-login-location">Southern Leyte, Philippines</p>
            <p class="portal-login-description">Registry of Barangay Inhabitants<br>Management System</p>
            <div class="portal-login-features">
                <span>Resident records</span><span>Document requests</span><span>Barangay reports</span>
            </div>
            <span class="portal-login-footnote">Official municipal portal</span>
        </aside>
    <section class="panel auth-panel portal-login-form rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6">
        <div class="page-kicker text-xs font-semibold uppercase tracking-widest text-blue-700">Account access</div>
        <h1>Sign in</h1>
        <p class="auth-copy">Enter your email or assigned staff ID to access your account.</p>

        @if ($errors->any())
            <div class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <label for="login">Email or user ID</label>
            <input id="login" name="login" type="text" value="{{ old('login', old('email')) }}" autocomplete="username" placeholder="Email address or staff ID" required autofocus>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <div class="check-row">
                <input id="remember" name="remember" type="checkbox" value="1">
                <label for="remember">Remember me</label>
            </div>

            <button class="primary-block" type="submit">Sign in</button>
        </form>

        <p class="form-footer">No account yet? <a href="{{ route('register') }}">Create an account</a></p>
    </section>
    </div>
@endsection
