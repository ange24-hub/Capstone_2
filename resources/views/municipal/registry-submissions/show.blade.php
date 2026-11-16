@extends('layouts.app')

@section('content')
<section class="panel dashboard-page" aria-labelledby="registry-submission-title">
    <header class="page-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5">
        <x-workspace-heading icon="users">
            <span class="dashboard-eyebrow">Registry snapshot · {{ $submission->reference() }}</span>
            <h1 id="registry-submission-title">{{ $submission->scopeLabel() }}</h1>
            <p>Barangay {{ $submission->barangay_name }} · {{ $submission->record_count }} records · As of {{ $submission->as_of->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
        </x-workspace-heading>
        <div class="toolbar flex flex-wrap gap-3">
            <a class="button secondary-button" href="{{ route('municipal.registry-submissions.index') }}">Back to submissions</a>
            <a class="button" href="{{ route('municipal.registry-submissions.download', $submission) }}">Download CSV snapshot</a>
        </div>
    </header>

    <p>Submitted by {{ $submission->submitter?->name ?? 'Account unavailable' }} on {{ $submission->submitted_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}.</p>
    <details class="statistical-details"><summary>Snapshot integrity hash</summary><code>{{ $submission->snapshot_hash }}</code></details>

    <div class="table-wrap w-full overflow-x-auto rounded-xl border border-slate-200">
        <table>
            <thead><tr><th>Household</th><th>Family</th><th>Last name</th><th>First name</th><th>Middle name</th><th>Suffix</th><th>Relationship</th><th>Purok</th><th>Birth place</th><th>Birth date</th><th>Recorded age</th><th>Sex</th><th>Civil status</th><th>Education</th><th>Religion</th><th>Occupation</th><th>Remarks</th><th>Ethnicity</th><th>Residence status</th></tr></thead>
            <tbody>
            @foreach($submission->snapshot ?? [] as $row)
                <tr>
                    <td>{{ $row['household_number'] ?? '' }}</td><td>{{ $row['family_number'] ?? '' }}</td>
                    <td>{{ $row['last_name'] ?? '' }}</td><td>{{ $row['first_name'] ?? '' }}</td><td>{{ $row['middle_name'] ?? '' }}</td><td>{{ $row['suffix'] ?? '' }}</td>
                    <td>{{ $row['relationship_to_head'] ?? '' }}</td><td>{{ $row['purok'] ?? '' }}</td><td>{{ $row['birth_place'] ?? '' }}</td><td>{{ $row['birth_date'] ?? '' }}</td>
                    <td>{{ $row['recorded_age'] ?? '' }}</td><td>{{ $row['sex'] ?? '' }}</td><td>{{ $row['civil_status'] ?? '' }}</td><td>{{ $row['education_level'] ?? '' }}</td>
                    <td>{{ $row['religion'] ?? '' }}</td><td>{{ $row['occupation'] ?? '' }}</td><td>{{ $row['remarks'] ?? '' }}</td><td>{{ $row['ethnicity'] ?? '' }}</td><td>{{ $row['residence_status'] ?? '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
