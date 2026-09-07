@extends('layouts.app')

@section('content')
    @php($familyCount = collect($rbiUpdate->rows ?? [])->pluck('household_head')->filter()->unique()->count())
    <section class="panel stack">
        <div class="page-kicker">RBI Monthly Form</div>
        <div class="page-head">
            <div>
                <h1>Updates of Barangay Registry of Barangay Inhabitants</h1>
                <p>For the month of {{ strtoupper(optional($rbiUpdate->reporting_month)->format('F Y') ?: 'NOT SET') }} — Barangay {{ $rbiUpdate->barangay_name ?: 'not set' }}</p>
            </div>
            <div class="toolbar">
                <a class="button" href="{{ route('rbi-updates.export-pdf', $rbiUpdate) }}">Download Consolidated PDF</a>
                <a class="button secondary-button" href="{{ route('rbi-updates.export-word', $rbiUpdate) }}">Download Word Document</a>
                @if ($rbiUpdate->source_file_path)<a class="button secondary-button" href="{{ route('rbi-updates.download', $rbiUpdate) }}">Original Upload</a>@endif
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta"><strong>Status</strong>{{ $rbiUpdate->statusLabel() }}</div>
            <div class="meta"><strong>Families</strong>{{ $familyCount }}</div>
            <div class="meta"><strong>New inhabitants</strong>{{ count($rbiUpdate->rows ?? []) }}</div>
            <div class="meta"><strong>Deceased</strong>{{ count($rbiUpdate->deceased_rows ?? []) }}</div>
            <div class="meta"><strong>Submitted</strong>{{ optional($rbiUpdate->submitted_at)->format('M d, Y h:i A') ?: 'Not submitted' }}</div>
        </div>

        @include('rbi-updates._document-preview')
    </section>
@endsection
