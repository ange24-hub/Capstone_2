@extends('layouts.app')
@section('content')
<section class="dashboard-page population-report population-spreadsheet {{ $selectedSection === 'summary' ? 'population-report-monitoring' : '' }} population-workspace population-report-builder" aria-labelledby="population-report-title">
    @unless($selectedSection === 'summary')
    @include('reports._document-header', ['documentTitle' => strtoupper($reportTitle).' REPORT', 'documentBarangay' => str_starts_with($report['scopeLabel'], 'Barangay ') ? substr($report['scopeLabel'], 9) : 'All barangays', 'documentDate' => $report['generatedAt']->format('m/d/Y'), 'documentPrintOnly' => true])
    @endunless
    <header class="population-page-heading population-builder-heading">
        <div><span class="population-builder-eyebrow">Reports workspace</span><h1 id="population-report-title"><span class="population-sticker population-sticker-blue" aria-hidden="true"><x-app-icon name="document" /></span>Population Summary</h1><p>{{ $report['scopeLabel'] }} · Tomas Oppus, Southern Leyte</p></div>
        <span class="population-live-badge"><span aria-hidden="true"></span>Latest Consolidated records</span>
    </header>
    @include('reports._population-pages')
    <form class="population-controls population-builder-controls" method="GET" action="{{ route('reports.population') }}" aria-label="Choose population report" data-population-report-controls>
        @unless($isSecretary)<div class="population-control population-control-wide"><label for="report-barangay">Barangay</label><select id="report-barangay" name="barangay_id"><option value="">All barangays</option>@foreach($barangays as $barangay)<option value="{{ $barangay->id }}" @selected($selectedBarangayId === $barangay->id)>{{ $barangay->name }}</option>@endforeach</select></div>@endunless
        <div class="population-control population-control-wide"><label for="report-section">Report type</label><select id="report-section" name="section">
            @foreach(['Summary & resident lists'=>['summary','residents','families','households','seniors','pwd'], 'Population breakdown'=>['sex','ages','coverage','area-ages']] as $group=>$keys)
                <optgroup label="{{ $group }}">@foreach($keys as $key)<option value="{{ $key }}" @selected($selectedSection === $key)>{{ $key==='summary' ? 'Population Summary' : $sectionLabels[$key] }}</option>@endforeach</optgroup>
            @endforeach
        </select></div>
        <div class="population-control population-control-year"><label for="report-year">Year</label><select id="report-year" name="report_year" required>@foreach(range(now()->year,1900) as $year)<option value="{{ $year }}" @selected($reportFilters['report_year']===$year)>{{ $year }}</option>@endforeach</select></div>
        <div class="population-control"><label for="report-semester">Semester</label><select id="report-semester" name="semester"><option value="1" @selected($reportFilters['semester'] === 1)>1st Semester · Jan–Jun</option><option value="2" @selected($reportFilters['semester'] === 2)>2nd Semester · Jul–Dec</option></select></div>
        <button class="button" type="submit"><x-app-icon name="document" /><span data-report-generate-label>Generate report</span></button>
        <p class="population-builder-control-hint" data-report-control-status role="status">Choose a year and semester. Your document updates automatically from Consolidated.</p>
    </form>
    @if($selectedSection==='summary')
    <div class="population-builder-stats" aria-label="Consolidated report totals">
        @foreach([['users','Inhabitants',$report['totalRecords']],['home','Households',$report['totalHouseholds']],['directory','Families',$report['totalFamilies']]] as [$icon,$label,$value])
            <div><span class="population-builder-stat-icon"><x-app-icon :name="$icon" /></span><p><strong>{{ number_format($value) }}</strong><span>{{ $label }}</span></p></div>
        @endforeach
    </div>
    @endif
    <div class="population-builder-document">
    <div class="population-preview-bar">
        <div><span class="population-builder-document-label">Document preview</span><h2>{{ $reportTitle }}</h2><p>{{ $reportFilters['semester'] === 1 ? '1st' : '2nd' }} Semester {{ $reportFilters['report_year'] }}@if($selectedSection === 'summary') · RBI Form C · A4 @endif</p></div>
        <div class="population-report-actions">
            <a class="button secondary-button" href="{{ route('reports.population', array_merge($reportFilters, ['section'=>$selectedSection,'print'=>1])) }}" target="_blank" rel="noopener"><x-app-icon name="print" />Print Report</a>
            <a class="button population-builder-pdf" href="{{ route('reports.population.pdf', array_merge($reportFilters, ['section'=>$selectedSection])) }}"><x-app-icon name="download" />Download PDF</a>
            <a class="button secondary-button" href="{{ route('reports.population.excel', array_merge($reportFilters, ['section'=>$selectedSection])) }}"><x-app-icon name="download" />Download Excel</a>
        </div>
    </div>
    <div class="population-builder-source" role="note"><x-app-icon name="activity" /><p><strong>Source: latest Consolidated registry</strong><span>As of {{ $report['generatedAt']->format('M d, Y · h:i A') }}. The selected year and semester label this newly generated report.</span></p>@if($usingSavedSignatures ?? false)<span class="population-builder-saved"><x-app-icon name="check" />Saved signatures included</span>@endif</div>
    @if($selectedSection==='summary')<p class="population-builder-mobile-hint">Swipe left or right to view the full document, or download the A4 PDF.</p>@endif
    @if($report['totalRecords'] === 0)<div class="dashboard-empty-state"><strong>No resident records encoded in this scope</strong><p>The report does not indicate zero population.</p></div>@endif
    @include('reports.population-content')
    <p class="population-coverage-note" role="note">{{ $report['coveredBarangays'] }} / {{ $report['totalBarangays'] }} barangays with resident records. Completeness has not been verified. Original historical reports remain unchanged in Submission History.</p>
    </div>
    @if($submissionPreviewToken ?? null)
        <section class="population-submission-panel" aria-label="Submit population summary">
            <div class="population-submit-copy"><h2><span class="population-sticker population-sticker-green" aria-hidden="true"><x-app-icon name="document" /></span>Submit Population Report Summary</h2>
            <p>After reviewing, sign and submit this {{ $report['monitoring']['semester'] === 1 ? '1st' : '2nd' }} Semester {{ $report['monitoring']['year'] }} report to Municipal DILG/Admin. The signed copy is saved in Submission History.</p>
            @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            </div>
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

@push('scripts')<script src="{{ asset('js/population-reports.js') }}?v={{ filemtime(public_path('js/population-reports.js')) }}" defer></script>@endpush

@if($printMode)
    @push('scripts')
        <script>window.addEventListener('load', () => window.print());</script>
    @endpush
@endif
