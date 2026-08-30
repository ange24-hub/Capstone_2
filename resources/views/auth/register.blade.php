@extends('layouts.app')

@section('content')
    @php($selectedRole = old('role', App\Models\User::ROLE_RESIDENT))

    <section class="registration-page">
        <div class="registration-layout">
            <aside class="registration-guide" aria-label="Registration information">
                <span class="registration-eyebrow">RBIM Account Enrollment</span>
                <h1>Join your barangay’s digital registry.</h1>
                <p>Create a secure account connected to the barangay where you live or serve.</p>

                <div class="registration-steps">
                    <div>
                        <span>1</span>
                        <p><strong>Choose an account type</strong><small>Register as a resident or barangay secretary.</small></p>
                    </div>
                    <div>
                        <span>2</span>
                        <p><strong>Enter your information</strong><small>Select your barangay and provide valid account details.</small></p>
                    </div>
                    <div>
                        <span>3</span>
                        <p><strong>Wait for verification</strong><small>Your account becomes available after the appropriate LGU approval.</small></p>
                    </div>
                </div>

                <div class="registration-security-note">
                    <strong>Protected registration</strong>
                    <span>Barangay secretary accounts require an LGU-issued access code.</span>
                </div>
            </aside>

            <div class="registration-card">
                <div class="registration-card-head">
                    <div class="page-kicker">Create Account</div>
                    <h2 id="registration-title">Resident registration</h2>
                    <p id="registration-description">Create a resident account and send it to your barangay secretary for verification.</p>
                </div>

                @if ($errors->any())
                    <div class="errors" role="alert">
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

                        <div class="registration-fields">
                            <div class="field-group">
                                <label for="name">Full name</label>
                                <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" placeholder="e.g. Maria D. Santos" @error('name') aria-invalid="true" @enderror required autofocus>
                                @error('name') <small class="field-error">{{ $message }}</small> @enderror
                            </div>

                            <div class="field-group">
                                <label for="email">Email address</label>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="name@example.com" @error('email') aria-invalid="true" @enderror required>
                                @error('email') <small class="field-error">{{ $message }}</small> @enderror
                            </div>

                            <div class="field-group registration-field-wide">
                                <label for="barangay_id">Barangay</label>
                                <select id="barangay_id" name="barangay_id" @error('barangay_id') aria-invalid="true" @enderror required>
                                    <option value="">Select your barangay</option>
                                    @foreach ($barangays as $barangay)
                                        <option value="{{ $barangay->id }}" @selected((string) old('barangay_id') === (string) $barangay->id)>
                                            {{ $barangay->name }}@if($barangay->localName()) — {{ $barangay->localName() }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('barangay_id') <small class="field-error">{{ $message }}</small> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="registration-section secretary-fields" data-secretary-fields>
                        <div class="registration-section-head">
                            <span>Secretary verification</span>
                            <small>These details confirm your authorized barangay role.</small>
                        </div>

                        <div class="registration-fields">
                            <div class="field-group">
                                <label for="user_id">Secretary user ID</label>
                                <input id="user_id" name="user_id" type="text" value="{{ old('user_id') }}" autocomplete="username" placeholder="e.g. TO-BANDAY-001" @error('user_id') aria-invalid="true" @enderror>
                                <small class="field-help">Letters, numbers, dashes, and underscores only.</small>
                                @error('user_id') <small class="field-error">{{ $message }}</small> @enderror
                            </div>

                            <div class="field-group">
                                <label for="access_code">LGU access code</label>
                                <input id="access_code" name="access_code" type="password" autocomplete="off" placeholder="Code issued by Municipal LGU" @error('access_code') aria-invalid="true" @enderror>
                                <small class="field-help">Contact the Municipal LGU if you have not received a code.</small>
                                @error('access_code') <small class="field-error">{{ $message }}</small> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="registration-section">
                        <div class="registration-section-head">
                            <span>Account security</span>
                            <small>Use at least 8 characters.</small>
                        </div>

                        <div class="registration-fields">
                            <div class="field-group">
                                <label for="password">Password</label>
                                <input id="password" name="password" type="password" autocomplete="new-password" @error('password') aria-invalid="true" @enderror required>
                                @error('password') <small class="field-error">{{ $message }}</small> @enderror
                            </div>

                            <div class="field-group">
                                <label for="password_confirmation">Confirm password</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
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
                    : 'Create a resident account and send it to your barangay secretary for verification.';
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
