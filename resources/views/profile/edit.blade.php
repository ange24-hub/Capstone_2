@extends('layouts.app')
@section('content')
<section class="panel stack staff-profile">
    <h1>My Profile</h1>
    <p>{{ $user->roleLabel() }}@if($user->barangay) ? {{ $user->barangay->name }}@endif</p>
    @if(session('status'))<div class="success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="POST" action="{{ route('profile.update') }}" class="stack">
        @csrf @method('PUT')
        <div><label for="name">Full name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name"></div>
        <div><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email"></div>
        <div><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password"><small>Required when changing your email or password.</small></div>
        <div><label for="password">New password (optional)</label><input id="password" name="password" type="password" autocomplete="new-password"><small>At least 8 characters. Leave blank to keep your current password.</small></div>
        <div><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"></div>
        <div><button type="submit">Update Profile</button></div>
    </form>
</section>
@endsection
