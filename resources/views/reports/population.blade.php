@extends('layouts.app')
@section('content')
<section class="dashboard-page population-report population-spreadsheet {{ $selectedSection === 'summary' ? 'population-report-monitoring' : '' }} grid gap-6" aria-labelledby="population-report-title">
    @unless($selectedSection === 'summary')
    @include('reports._document-header', ['documentTitle' => strtoupper($reportTitle).' REPORT', 'documentBarangay' => str_starts_with($report['scopeLabel'], 'Barangay ') ? substr($report['scopeLabel'], 9) : 'All barangays', 'documentDate' => $report['generatedAt']->format('m/d/Y'), 'documentPrintOnly' => true])
    @endunless
    <header class="population-report-header">
        <div class="population-report-heading">
            <span class="population-report-eyebrow">Registry reports</span>
            <h1 id="population-report-title"><x-portal-sticker name="users" /> {{ $reportTitle }}</h1>
            @include('reports._jurisdiction')
        </div>
        <div class="population-report-actions">
            <a class="button secondary-button population-print-button" href="{{ route('reports.population', array_merge($reportFilters, ['section' => $selectedSection, 'print' => 1])) }}" target="_blank" rel="noopener"><x-app-icon name="document" /> Print Report</a>
            <a class="button" href="{{ route('reports.population.pdf', array_merge($reportFilters, ['section' => $selectedSection])) }}"><x-app-icon name="document" /> Download PDF</a>
            <a class="button secondary-button" href="{{ route('reports.population.excel', array_merge($reportFilters, ['section' => $selectedSection])) }}"><x-app-icon name="document" /> Download Excel</a>
        </div>
    </header>
    <form class="population-report-filter flex flex-wrap items-end gap-3" method="GET" action="{{ route('reports.population') }}">
        <input type="hidden" name="section" value="{{ $selectedSection }}">
        @unless($isSecretary)<div><label for="report-barangay">Barangay</label><select id="report-barangay" name="barangay_id"><option value="">All barangays — available records</option>@foreach($barangays as $barangay)<option value="{{ $barangay->id }}" @selected($selectedBarangayId === $barangay->id)>{{ $barangay->name }}</option>@endforeach</select></div>@endunless
        <div><label for="report-semester">Semester</label><select id="report-semester" name="semester"><option value="1" @selected($reportFilters['semester'] === 1)>1st Semester</option><option value="2" @selected($reportFilters['semester'] === 2)>2nd Semester</option></select></div>
        <div><label for="report-year">Calendar year</label><input id="report-year" type="number" name="report_year" min="1900" max="{{ now()->year }}" value="{{ $reportFilters['report_year'] }}" required></div>
        <button type="submit">Apply filter</button>
    </form>
    <div class="population-coverage-notice rounded-xl border border-blue-200 bg-blue-50 p-4 text-blue-900" role="note"><strong>{{ $report['coveredBarangays'] }} / {{ $report['totalBarangays'] }} barangays with resident records</strong><span>Partial registry coverage. Generated {{ $report['generatedAt']->format('M d, Y, h:i A') }}. Completeness has not been verified.</span></div>
    @if($report['totalRecords'] === 0)<div class="dashboard-empty-state rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-slate-600"><strong>No resident records encoded in this scope</strong><span>The report does not indicate zero population.</span></div>@endif
    <nav class="population-category-nav" aria-label="Population report categories">
        @php
            $categoryGroups = [
                'Overview & resident lists' => ['summary' => 'document', 'residents' => 'users', 'families' => 'users', 'households' => 'home', 'seniors' => 'shield', 'pwd' => 'shield'],
                'Population breakdown' => ['sex' => 'users', 'ages' => 'calendar', 'coverage' => 'map', 'area-ages' => 'trend'],
            ];
        @endphp
        @foreach($categoryGroups as $groupLabel => $categories)
            <div class="population-category-group">
                <span class="population-category-label">{{ $groupLabel }}</span>
                <div class="population-category-links">
                    @foreach($categories as $key => $icon)
                        <a class="population-category-link" href="{{ route('reports.population', array_merge($reportFilters, ['section' => $key])) }}" @if($selectedSection === $key) aria-current="page" @endif>
                            <span class="population-category-icon"><x-app-icon :name="$icon" /></span>
                            <span>{{ $sectionLabels[$key] }}</span>
                            <span class="population-category-arrow" aria-hidden="true"><x-app-icon name="arrow-right" /></span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
        <div class="population-category-hint"><x-app-icon name="document" /><span>{{ $selectedSection === 'summary' ? 'Full summary selected.' : $reportTitle.' selected.' }} Your PDF includes the complete selected report.</span></div>
    </nav>
    @include('reports.population-content')
    @if($detailPagination){{ $detailPagination->links() }}@endif
</section>
@endsection

@push('head')
    <style>@layer print-overrides { @include('reports._document-header-styles') }</style>
    <link rel="stylesheet" href="{{ asset('css/population-spreadsheet.css') }}?v={{ filemtime(public_path('css/population-spreadsheet.css')) }}">
    @if($selectedSection === 'summary')<style>@include('reports._population-monitoring-styles')</style>@endif
    @if(in_array($selectedSection, ['coverage', 'area-ages'], true) || ($report['totalBarangays'] > 1 && in_array($selectedSection, ['sex', 'ages'], true)))<style>@media print { @page { size: A4 landscape; } }</style>@endif
@endpush

@if($printMode)
    @push('scripts')
        <script>window.addEventListener('load', () => window.print());</script>
    @endpush
@endif
