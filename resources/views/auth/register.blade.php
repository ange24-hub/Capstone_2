@extends('layouts.app')

@section('content')
    <section class="panel auth-panel">
        <div class="page-kicker">Account Enrollment</div>
        <h1>Register</h1>
        <p class="auth-copy">Create an RBIM account for the correct municipal, barangay, or resident access tier.</p>

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register.store') }}">
            @csrf

            <label for="name">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus>

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>

            <label for="role">Account type</label>
            <select id="role" name="role" required>
                @foreach ($roles as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', App\Models\User::ROLE_RESIDENT) === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <label for="access_code">LGU access code</label>
            <input id="access_code" name="access_code" type="text" value="{{ old('access_code') }}" autocomplete="off">

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

            <button class="primary-block" type="submit" style="margin-top: 18px;">Register</button>
        </form>

        <p class="form-footer">Already registered? <a href="{{ route('login') }}">Login</a></p>
    </section>
@endsection
