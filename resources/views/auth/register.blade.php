@extends('layouts.app')

@section('content')
    @php($selectedRole = old('role', App\Models\User::ROLE_RESIDENT))

    <section class="registration-page">
        <div class="registration-layout">
            <header class="registration-guide" aria-label="Municipal registration information">
                <span class="seal-crop registration-seal"><img src="{{ asset('images/tomas-oppus-seal.png') }}" alt="Municipality of Tomas Oppus seal"></span>
                <div class="registration-guide-copy">
                    <span class="registration-eyebrow">Republic of the Philippines <span aria-hidden="true">·</span> Southern Leyte</span>
                    <h1>Municipality of Tomas Oppus</h1>
                    <p>Registry of Barangay Inhabitants Management System</p>
                </div>
                <span class="registration-guide-tag">Official account registration</span>
            </header>

            <div class="registration-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm p-5 sm:p-8">
                <div class="registration-card-head">
                    <div class="page-kicker text-xs font-semibold uppercase tracking-widest text-blue-700">Create account</div>
                    <h2 id="registration-title">Resident registration</h2>
                    <p id="registration-description">Submit your details for review by your barangay secretary.</p>
                </div>

                @if ($errors->any())
                    <div class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert">
                        <strong>Please review the highlighted information.</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('register.store') }}" class="registration-form">
                    @csrf

                    <fieldset class="account-type-fieldset">
                        <legend>Who is this account for?</legend>
                        <div class="account-type-grid">
                            @foreach ($roles as $value => $label)
                                <label class="account-type-option">
                                    <input type="radio" name="role" value="{{ $value }}" @checked($selectedRole === $value) required>
                                    <span class="account-type-icon" aria-hidden="true">{{ $value === App\Models\User::ROLE_RESIDENT ? 'R' : 'BS' }}</span>
                                    <span>
                                        <strong>{{ $label }}</strong>
                                        <small>{{ $value === App\Models\User::ROLE_RESIDENT ? 'For people living in a Tomas Oppus barangay' : 'For authorized barangay secretaries' }}</small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="registration-section">
                        <div class="registration-section-head">
                            <span>Personal information</span>
                            <small>Use your complete and correct identity.</small>
                        </div>

                        <div class="registration-fields grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="field-group">
                                <label for="name">Full name</label>
                                <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" placeholder="e.g. Maria D. Santos" @error('name') aria-invalid="true" @enderror required autofocus>
                                @error('name') <small class="field-error text-sm text-red-700">{{ $message }}</small> @enderror
                            </div>

                            <div class="field-group">
                                <label for="email">Email address</label>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="name@example.com" aria-describedby="registration-email-help" @error('email') aria-invalid="true" @enderror required>
                                <small id="registration-email-help" class="block mt-2 text-sm text-slate-600">Use an email address you can access for barangay notices and documents.</small>
                                @error('email') <small class="field-error text-sm text-red-700">{{ $message }}</small> @enderror
                            </div>

                            <div class="field-group registration-field-wide md:col-span-2">
                                <label for="barangay_id">Barangay</label>
                                <select id="barangay_id" name="barangay_id" @error('barangay_id') aria-invalid="true" @enderror required>
                                    <option value="">Select your barangay</option>
                                    @foreach ($barangays as $barangay)
                                        <option value="{{ $barangay->id }}" @selected((string) old('barangay_id') === (string) $barangay->id)>
                                            {{ $barangay->name }}@if($barangay->localName()) — {{ $barangay->localName() }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('barangay_id') <small class="field-error text-sm text-red-700">{{ $message }}</small> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="registration-section secretary-fields" data-secretary-fields>
                        <div class="registration-section-head">
                            <span>Secretary verification</span>
                            <small>These details confirm your authorized barangay role.</small>
                        </div>

                        <div class="registration-fields grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="field-group">
                                <label for="user_id">Secretary user ID</label>
                                <input id="user_id" name="user_id" type="text" value="{{ old('user_id') }}" autocomplete="username" placeholder="e.g. TO-BANDAY-001" @error('user_id') aria-invalid="true" @enderror>
                                <small class="field-help">Letters, numbers, dashes, and underscores only.</small>
                                @error('user_id') <small class="field-error text-sm text-red-700">{{ $message }}</small> @enderror
                            </div>

                            <div class="field-group">
                                <label for="access_code">LGU access code</label>
                                <x-password-control field-id="access_code" label="access code">
                                    <input id="access_code" name="access_code" type="password" autocomplete="off" placeholder="Code issued by Municipal LGU" @error('access_code') aria-invalid="true" @enderror>
                                </x-password-control>
                                <small class="field-help">Contact the Municipal LGU if you have not received a code.</small>
                                @error('access_code') <small class="field-error text-sm text-red-700">{{ $message }}</small> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="registration-section">
                        <div class="registration-section-head">
                            <span>Account security</span>
                            <small>Use at least 8 characters.</small>
                        </div>

                        <div class="registration-fields grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="field-group">
                                <label for="password">Password</label>
                                <x-password-control field-id="password">
                                    <input id="password" name="password" type="password" autocomplete="new-password" @error('password') aria-invalid="true" @enderror required>
                                </x-password-control>
                                @error('password') <small class="field-error text-sm text-red-700">{{ $message }}</small> @enderror
                            </div>

                            <div class="field-group">
                                <label for="password_confirmation">Confirm password</label>
                                <x-password-control field-id="password_confirmation" label="password confirmation">
                                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                                </x-password-control>
                            </div>
                        </div>
                    </div>

                    <div class="registration-approval-note" id="approval-note">
                        Your barangay secretary will review this resident account before portal access is enabled.
                    </div>

                    <button class="primary-block registration-submit" id="registration-submit" type="submit">Create Resident Account</button>
                </form>

                <p class="form-footer">Already registered? <a href="{{ route('login') }}">Sign in to your account</a></p>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const roleInputs = document.querySelectorAll('input[name="role"]');
            const secretaryFields = document.querySelector('[data-secretary-fields]');
            const secretaryInputs = secretaryFields.querySelectorAll('input');
            const title = document.getElementById('registration-title');
            const description = document.getElementById('registration-description');
            const approvalNote = document.getElementById('approval-note');
            const submit = document.getElementById('registration-submit');

            function selectedRole() {
                return document.querySelector('input[name="role"]:checked')?.value;
            }

            function updateRegistrationForm() {
                const isSecretary = selectedRole() === @json(App\Models\User::ROLE_BARANGAY);

                secretaryFields.hidden = !isSecretary;
                secretaryInputs.forEach((input) => {
                    input.disabled = !isSecretary;
                    input.required = isSecretary;
                });

                title.textContent = isSecretary ? 'Barangay secretary registration' : 'Resident registration';
                description.textContent = isSecretary
                    ? 'Create an official secretary account for Municipal LGU verification.'
                    : 'Submit your details for review by your barangay secretary.';
                approvalNote.textContent = isSecretary
                    ? 'The Municipal LGU will review this secretary account before workspace access is enabled.'
                    : 'Your barangay secretary will review this resident account before portal access is enabled.';
                submit.textContent = isSecretary ? 'Create Secretary Account' : 'Create Resident Account';
            }

            roleInputs.forEach((input) => input.addEventListener('change', updateRegistrationForm));
            updateRegistrationForm();
        })();
    </script>
@endpush
