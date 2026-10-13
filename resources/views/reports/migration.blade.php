@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/population-spreadsheet.css') }}?v={{ filemtime(public_path('css/population-spreadsheet.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/migration-report.css') }}?v={{ filemtime(public_path('css/migration-report.css')) }}">
@endpush

@section('content')
<section class="dashboard-page population-report migration-report-page" aria-labelledby="migration-report-title">
    <header class="population-report-header migration-report-header">
        <div class="population-report-heading">
            <span class="population-report-eyebrow">Registry reports</span>
            <h1 id="migration-report-title">Monthly Migration Report <span class="migration-report-year">{{ $selectedYear }}</span></h1>
            <p class="migration-report-scope">{{ $report['scopeLabel'] }} <span aria-hidden="true">&middot;</span> {{ str_starts_with($report['scopeLabel'], 'Barangay ') ? 'Tomas Oppus, Southern Leyte' : 'Southern Leyte' }} <span aria-hidden="true">&middot;</span> Region VIII</p>
            <div class="migration-report-print-jurisdiction">@include('reports._jurisdiction')</div>
        </div>
        <div class="population-report-actions">
            <button class="button secondary-button population-print-button" type="button" onclick="window.print()"><x-app-icon name="document" /> Print Report</button>
            <a class="button" href="{{ route('reports.migration.pdf', array_filter(['barangay_id' => $selectedBarangayId, 'year' => $selectedYear])) }}"><x-app-icon name="document" /> Download PDF</a>
        </div>
    </header>

    <div class="migration-report-overview">
        <form class="population-report-filter migration-report-filter" method="GET" action="{{ route('reports.migration') }}">
            @unless($isSecretary)
                <div class="migration-report-field migration-report-area-field">
                    <label for="migration-report-barangay">Barangay</label>
                    <select id="migration-report-barangay" name="barangay_id">
                        <option value="">All barangays</option>
                        @foreach($barangays as $barangay)
                            <option value="{{ $barangay->id }}" @selected($selectedBarangayId === $barangay->id)>{{ $barangay->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div class="migration-report-field migration-report-year-field">
                <label for="migration-report-year">Reporting year</label>
                <input id="migration-report-year" name="year" type="number" min="1900" max="9999" value="{{ $selectedYear }}" required>
            </div>
            <button type="submit">Apply filter</button>
        </form>

        <div class="migration-report-highlights" aria-label="Recorded movement totals">
            <div><span>Recorded events</span><strong>{{ number_format($report['total']) }}</strong></div>
            <div><span>Arrivals</span><strong>{{ number_format($report['in']) }}</strong></div>
            <div><span>Departures</span><strong>{{ number_format($report['out']) }}</strong></div>
            <div><span>Net change</span><strong>{{ $report['net'] > 0 ? '+' : '' }}{{ number_format($report['net']) }}</strong></div>
        </div>
        <p class="migration-report-caveat">Based on movement dates. Reporting completeness has not been verified.</p>
    </div>

    @include('reports.migration-tables')
</section>
@endsection
