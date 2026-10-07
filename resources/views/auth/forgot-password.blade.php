@extends('layouts.app')

@section('content')
<section class="panel auth-panel portal-login-form rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="forgot-password-title">
    <div class="page-kicker text-xs font-semibold uppercase tracking-widest text-blue-700">Account recovery</div>
    <h1 id="forgot-password-title">Forgot your password?</h1>
    <p class="auth-copy">Enter the email registered to your RBIM account. We will send a secure reset link if the account exists.</p>

    @if (session('status'))
        <div class="success rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        <button class="primary-block" type="submit">Send reset link</button>
    </form>
    <p class="form-footer"><a href="{{ route('login') }}">Return to sign in</a></p>
</section>
@endsection
