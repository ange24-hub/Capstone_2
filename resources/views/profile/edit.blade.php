@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}?v={{ filemtime(public_path('css/profile.css')) }}">
@endpush

@section('content')
<section class="staff-profile profile-workspace" aria-labelledby="profile-title">
    <header class="profile-heading">
        <div><span class="profile-eyebrow">Account settings</span><h1 id="profile-title">My Profile</h1><p>Manage your personal details and account security.</p></div>
        <a class="profile-dashboard-link" href="{{ route('dashboard') }}"><x-app-icon name="home" /> Back to dashboard</a>
    </header>
    @if(session('status'))<div class="success rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert"><strong>Please review your details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="profile-layout">
        <aside class="profile-account-card" aria-label="Current account">
            <div class="profile-account-body">
                <div class="profile-identity">
                    <span class="profile-avatar" aria-hidden="true">{{ str($user->name)->substr(0, 1)->upper() }}</span>
                    <div><h2>{{ $user->name }}</h2><span class="profile-role">{{ $user->roleLabel() }}</span><p class="profile-email">{{ $user->email }}</p></div>
                </div>
                <dl class="profile-account-details">
                    <div><dt>Assigned office</dt><dd>{{ $user->barangay ? 'Barangay '.$user->barangay->name : ($user->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU) ? 'Municipal LGU' : 'Not assigned') }}</dd></div>
                    @if($user->staff_id)<div><dt>Staff ID</dt><dd>{{ $user->staff_id }}</dd></div>@endif
                    <div><dt>Municipality</dt><dd>Tomas Oppus, Southern Leyte</dd></div>
                </dl>
                <div class="profile-office-note"><x-app-icon name="directory" /><p>To update your role or office assignment, contact your municipal administrator.</p></div>
            </div>
        </aside>
        <form method="POST" action="{{ route('profile.update') }}" class="profile-edit-form">
            @csrf
            @method('PUT')
            <section class="profile-section" aria-labelledby="profile-details-title">
                <header class="profile-section-heading"><div><h2 id="profile-details-title">Personal information</h2><p>Your name and email used across the system.</p></div></header>
                <div class="profile-fields">
                    <div class="profile-field"><label for="name">Full name <span aria-hidden="true">*</span></label><input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>@error('name')<small class="profile-field-error text-sm text-red-700" id="name-error">{{ $message }}</small>@enderror</div>
                    <div class="profile-field"><label for="email">Email address <span aria-hidden="true">*</span></label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email" aria-describedby="email-help{{ $errors->has('email') ? ' email-error' : '' }}" @error('email') aria-invalid="true" @enderror><small id="email-help">Changing your email requires your current password.</small>@error('email')<small class="profile-field-error text-sm text-red-700" id="email-error">{{ $message }}</small>@enderror</div>
                </div>
            </section>
            <section class="profile-section" aria-labelledby="profile-security-title">
                <header class="profile-section-heading"><div><h2 id="profile-security-title">Password &amp; security</h2><p>Leave password fields blank if you are only updating your name.</p></div></header>
                <div class="profile-fields">
                    <div class="profile-field profile-field-full"><label for="current_password">Current password</label><x-password-control field-id="current_password" label="current password" class="password-control-current"><input id="current_password" name="current_password" type="password" autocomplete="current-password" aria-describedby="current-password-help{{ $errors->has('current_password') ? ' current-password-error' : '' }}" @error('current_password') aria-invalid="true" @enderror></x-password-control><small id="current-password-help">Required when changing your email or password.</small>@error('current_password')<small class="profile-field-error text-sm text-red-700" id="current-password-error">{{ $message }}</small>@enderror</div>
                    <div class="profile-field"><label for="password">New password <span class="profile-optional">Optional</span></label><x-password-control field-id="password" label="new password"><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" aria-describedby="password-help{{ $errors->has('password') ? ' password-error' : '' }}" @error('password') aria-invalid="true" @enderror></x-password-control><small id="password-help">At least 8 characters.</small>@error('password')<small class="profile-field-error text-sm text-red-700" id="password-error">{{ $message }}</small>@enderror</div>
                    <div class="profile-field"><label for="password_confirmation">Confirm new password</label><x-password-control field-id="password_confirmation" label="password confirmation"><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"></x-password-control></div>
                </div>
            </section>
            <footer class="profile-form-footer"><p>Changes apply to your account only.</p><div><a class="button secondary-button" href="{{ route('profile.edit') }}">Cancel</a><button class="profile-save-button" type="submit"><x-app-icon name="check" /> Save changes</button></div></footer>
        </form>
    </div>
</section>
@endsection
