@extends('layouts.app')
@section('content')
<section class="dashboard-page population-report population-spreadsheet {{ $selectedSection === 'summary' ? 'population-report-monitoring' : '' }} population-workspace" aria-labelledby="population-report-title">
    @unless($selectedSection === 'summary')
    @include('reports._document-header', ['documentTitle' => strtoupper($reportTitle).' REPORT', 'documentBarangay' => str_starts_with($report['scopeLabel'], 'Barangay ') ? substr($report['scopeLabel'], 9) : 'All barangays', 'documentDate' => $report['generatedAt']->format('m/d/Y'), 'documentPrintOnly' => true])
    @endunless
    <header class="population-page-heading">
        <h1 id="population-report-title">Population Reports</h1>
        <p>{{ $report['scopeLabel'] }} · Tomas Oppus, Southern Leyte</p>
    </header>
    @include('reports._population-pages')
    <form class="population-controls" method="GET" action="{{ route('reports.population') }}" aria-label="Choose population report">
        @unless($isSecretary)<div class="population-control population-control-wide"><label for="report-barangay">Barangay</label><select id="report-barangay" name="barangay_id"><option value="">All barangays</option>@foreach($barangays as $barangay)<option value="{{ $barangay->id }}" @selected($selectedBarangayId === $barangay->id)>{{ $barangay->name }}</option>@endforeach</select></div>@endunless
        <div class="population-control population-control-wide"><label for="report-section">Report type</label><select id="report-section" name="section">
            @foreach(['Summary & resident lists'=>['summary','residents','families','households','seniors','pwd'], 'Population breakdown'=>['sex','ages','coverage','area-ages']] as $group=>$keys)
                <optgroup label="{{ $group }}">@foreach($keys as $key)<option value="{{ $key }}" @selected($selectedSection === $key)>{{ $sectionLabels[$key] }}</option>@endforeach</optgroup>
            @endforeach
        </select></div>
        <div class="population-control"><label for="report-semester">Semester</label><select id="report-semester" name="semester"><option value="1" @selected($reportFilters['semester'] === 1)>1st Semester</option><option value="2" @selected($reportFilters['semester'] === 2)>2nd Semester</option></select></div>
        <div class="population-control population-control-year"><label for="report-year">Calendar year</label><input id="report-year" type="number" name="report_year" min="1900" max="{{ now()->year }}" value="{{ $reportFilters['report_year'] }}" required></div>
        <button class="button" type="submit">View report</button>
    </form>
    <div class="population-preview-bar">
        <div><h2>{{ $reportTitle }}</h2><p>{{ $reportFilters['semester'] === 1 ? '1st' : '2nd' }} Semester {{ $reportFilters['report_year'] }}@if($selectedSection === 'summary') · A4 · 1 page @endif</p></div>
        <div class="population-report-actions">
            <a class="button secondary-button" href="{{ route('reports.population', array_merge($reportFilters, ['section'=>$selectedSection,'print'=>1])) }}" target="_blank" rel="noopener">Print Report</a>
            <a class="button secondary-button" href="{{ route('reports.population.pdf', array_merge($reportFilters, ['section'=>$selectedSection])) }}">Download PDF</a>
            <a class="button secondary-button" href="{{ route('reports.population.excel', array_merge($reportFilters, ['section'=>$selectedSection])) }}">Download Excel</a>
        </div>
    </div>
    <p class="population-coverage-note" role="note">{{ $report['coveredBarangays'] }} / {{ $report['totalBarangays'] }} barangays with resident records. Completeness has not been verified. The selected semester/year labels the current registry report.</p>
    @if($report['totalRecords'] === 0)<div class="dashboard-empty-state"><strong>No resident records encoded in this scope</strong><p>The report does not indicate zero population.</p></div>@endif
    @include('reports.population-content')
    @if($submissionPreviewToken ?? null)
        <section class="population-submission-panel" aria-label="Submit population summary">
            <h2>Submit Population Report Summary</h2>
            <p>After reviewing, sign and submit this {{ $report['monitoring']['semester'] === 1 ? '1st' : '2nd' }} Semester {{ $report['monitoring']['year'] }} report to Municipal DILG/Admin. The signed copy is saved in Submission History.</p>
            @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <form method="GET" action="{{ route('reports.population.submissions.sign') }}">
                <input type="hidden" name="preview_token" value="{{ $submissionPreviewToken }}">
                <button class="button" type="submit">Submit to Municipal DILG/Admin</button>
            </form>
        </section>
    @endif
    @if($detailPagination){{ $detailPagination->links() }}@endif
</section>
@endsection

@push('head')
    <style>@layer print-overrides { @include('reports._document-header-styles') }</style>
    <link rel="stylesheet" href="{{ asset('css/population-spreadsheet.css') }}?v={{ filemtime(public_path('css/population-spreadsheet.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/population-submissions.css') }}?v={{ filemtime(public_path('css/population-submissions.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/population-workspace.css') }}?v={{ filemtime(public_path('css/population-workspace.css')) }}">
    @if($selectedSection === 'summary')<style>@include('reports._population-monitoring-styles')</style>@endif
    @if(in_array($selectedSection, ['coverage', 'area-ages'], true) || ($report['totalBarangays'] > 1 && in_array($selectedSection, ['sex', 'ages'], true)))<style>@media print { @page { size: A4 landscape; } }</style>@endif
@endpush

@if($printMode)
    @push('scripts')
        <script>window.addEventListener('load', () => window.print());</script>
    @endpush
@endif
