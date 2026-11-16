@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/resident-registry-table.css') }}?v={{ filemtime(public_path('css/resident-registry-table.css')) }}" media="screen">
@endpush

@section('content')
<section class="panel registry-workspace registry-color-workspace rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6 grid gap-6" aria-labelledby="registry-submission-title">
    <header class="page-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5">
        <x-workspace-heading icon="users" label="Registry snapshot">
            <h1 id="registry-submission-title">{{ $submission->scopeLabel() }}</h1>
            <p>Barangay {{ $submission->barangay_name }} · {{ $submission->record_count }} residents · As of {{ $submission->as_of->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
        </x-workspace-heading>
        <div class="toolbar flex flex-wrap gap-3">
            <a class="button secondary-button" href="{{ route('municipal.registry-submissions.index') }}">Back to submissions</a>
            <a class="button" href="{{ route('municipal.registry-submissions.download', $submission) }}">Download CSV snapshot</a>
        </div>
    </header>

    <p>Submitted by {{ $submission->submitter?->name ?? 'Account unavailable' }} on {{ $submission->submitted_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}.</p>

    <div class="registry-table-summary">
        <p class="text-center font-semibold">Republic of the Philippines</p>
        <p class="text-center">Province of Southern Leyte</p>
        <p class="text-center">Municipality of Tomas Oppus</p>
        <h3>{{ strtoupper($submission->scopeLabel()) }}</h3>
        <dl>
            <div><dt>Region</dt><dd>VIII</dd></div>
            <div><dt>Province</dt><dd>Southern Leyte</dd></div>
            <div><dt>Municipality</dt><dd>Tomas Oppus</dd></div>
            <div><dt>Barangay</dt><dd>{{ $submission->barangay_name }}</dd></div>
            <div><dt>Households</dt><dd>{{ number_format($householdCount) }}</dd></div>
            <div><dt>Residents</dt><dd>{{ number_format($submission->record_count) }}</dd></div>
        </dl>
        <p class="text-center text-sm">Submitted {{ $submission->submitted_at->timezone('Asia/Manila')->format('M d, Y h:i A') }} · Read-only registry snapshot</p>
    </div>

    <div tabindex="0" role="region" aria-label="Submitted resident records; scroll horizontally for all columns" class="table-wrap registry-document-table w-full overflow-x-auto rounded-xl border border-slate-200">
        <table>
            <thead>
                <tr class="rbi-group-row">
                    <th rowspan="2">HH<br>No.</th><th rowspan="2">Family<br>No.</th><th colspan="4">NAME</th><th>RELATIONSHIP</th><th rowspan="2">PUROK / SITIO</th><th rowspan="2">PLACE OF<br>BIRTH</th><th>DATE OF</th><th rowspan="2">AGE</th><th rowspan="2">SEX<br>(M/F)</th><th rowspan="2">CIVIL<br>STATUS</th><th>School</th><th rowspan="2">RELIGION</th><th rowspan="2">OCCUPATION</th><th>REMARKS</th><th rowspan="2">ETHNICITY</th>
                </tr>
                <tr>
                    <th>Last Name</th><th>First Name</th><th>Middle Name</th><th>Qualifier</th><th>TO HOUSEHOLD<br>HEAD</th><th>BIRTH<br>(mm-dd-yy)</th><th>Grade/Year/<br>Level Completed</th><th>(OTHER INFO)</th>
                </tr>
                <tr class="rbi-meta-row"><th colspan="18">Residents: {{ number_format($submission->record_count) }}</th></tr>
            </thead>
            <tbody>
            @php($previousHousehold = null)
            @foreach($submission->snapshot ?? [] as $row)
                @php($householdNumber = $row['household_number'] ?? '')
                @php($householdStart = $householdNumber !== '' && $householdNumber !== 'Not recorded' && $householdNumber !== $previousHousehold)
                @php($previousHousehold = $householdNumber)
                <tr class="{{ $householdStart ? 'rbi-household-start punong-household-start' : '' }}">
                    <td>{{ $householdNumber }}</td><td>{{ $row['family_number'] ?? '' }}</td>
                    <td>{{ $row['last_name'] ?? '' }}</td><td>{{ $row['first_name'] ?? '' }}</td><td>{{ $row['middle_name'] ?? '' }}</td><td>{{ $row['suffix'] ?? '' }}</td>
                    <td>{{ $row['relationship_to_head'] ?? '' }}</td><td>{{ $row['purok'] ?? '' }}</td><td>{{ $row['birth_place'] ?? '' }}</td>
                    <td>{{ !empty($row['birth_date']) ? \Carbon\Carbon::parse($row['birth_date'])->format('m-d-y') : '' }}</td>
                    <td>{{ $row['recorded_age'] ?? '' }}</td><td>{{ ['Male' => 'M', 'Female' => 'F'][$row['sex'] ?? ''] ?? ($row['sex'] ?? '') }}</td>
                    <td>{{ $row['civil_status'] ?? '' }}</td><td>{{ $row['education_level'] ?? '' }}</td><td>{{ $row['religion'] ?? '' }}</td><td>{{ $row['occupation'] ?? '' }}</td><td>{{ $row['remarks'] ?? '' }}</td><td>{{ $row['ethnicity'] ?? '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
