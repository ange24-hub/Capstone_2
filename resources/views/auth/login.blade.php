@extends('layouts.app')

@section('content')
    <section class="panel auth-panel">
        <div class="page-kicker">Secure Access</div>
        <h1>Login</h1>
        <p class="auth-copy">Access the registry workspace assigned to your RBIM account.</p>

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <div class="check-row">
                <input id="remember" name="remember" type="checkbox" value="1">
                <label for="remember">Remember me</label>
            </div>

            <button class="primary-block" type="submit">Login</button>
        </form>

        <p class="form-footer">No account yet? <a href="{{ route('register') }}">Register</a></p>
    </section>
@endsection
