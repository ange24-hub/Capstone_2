@extends('layouts.app')

@section('content')
    @php($isSecretary = $resident->hasRole(App\Models\User::ROLE_BARANGAY))

    <section class="panel approval-panel">
        <div class="approval-icon {{ $resident->approval_status === App\Models\User::APPROVAL_REJECTED ? 'is-rejected' : '' }}" aria-hidden="true">
            {{ $resident->approval_status === App\Models\User::APPROVAL_REJECTED ? '!' : '…' }}
        </div>

        <div class="page-kicker">{{ $isSecretary ? 'Municipal Staff Verification' : 'Resident Account Verification' }}</div>

        @if ($resident->approval_status === App\Models\User::APPROVAL_REJECTED)
            <h1>Registration not approved</h1>
            <p>
                Your registration was not approved. Please contact the
                {{ $isSecretary ? 'Municipal LGU administrator' : 'Barangay '.($resident->barangay?->name ?? 'assigned barangay').' office' }}
                to verify your information.
            </p>
        @else
            <h1>Awaiting {{ $isSecretary ? 'Municipal LGU' : 'secretary' }} approval</h1>
            <p>
                Your account was created successfully.
                @if ($isSecretary)
                    The Municipal LGU administrator must approve your appointment for Barangay {{ $resident->barangay?->name }} before you can access barangay operations.
                @else
                    The Barangay {{ $resident->barangay?->name }} secretary must confirm your residency before you can access the resident dashboard.
                @endif
            </p>
        @endif

        <div class="approval-details">
            <div><span>{{ $isSecretary ? 'Secretary' : 'Resident' }}</span><strong>{{ $resident->name }}</strong></div>
            <div><span>Barangay</span><strong>{{ $resident->barangay?->name ?? 'Not assigned' }}</strong></div>
            <div><span>Status</span><strong>{{ $resident->approvalStatusLabel() }}</strong></div>
        </div>

        <p class="approval-note">You may sign out and return later. Once approved, your next login will open your assigned dashboard automatically.</p>
    </section>
@endsection
