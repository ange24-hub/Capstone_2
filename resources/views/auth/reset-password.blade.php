@extends('layouts.app')

@section('content')
<section class="panel auth-panel portal-login-form rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="reset-password-title">
    <div class="page-kicker text-xs font-semibold uppercase tracking-widest text-blue-700">Account recovery</div>
    <h1 id="reset-password-title">Set a new password</h1>
    <p class="auth-copy">Choose a strong password that you do not use for another account.</p>

    @if ($errors->any())
        <div class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required autofocus>
        <label for="password">New password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required>
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        <button class="primary-block" type="submit">Reset password</button>
    </form>
    <p class="form-footer"><a href="{{ route('login') }}">Return to sign in</a></p>
</section>
@endsection
