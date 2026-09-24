@extends('layouts.app')
@section('content')
<section class="dashboard-page population-report grid gap-6" aria-labelledby="population-report-title">
    <header class="dashboard-page-header dashboard-page-header-with-actions flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 bg-transparent bg-none pb-6 shadow-none">
            <x-workspace-heading icon="users"><span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Registry reports</span><h1 id="population-report-title">{{ $reportTitle }}</h1><p>{{ $report['scopeLabel'] }}</p></x-workspace-heading>
        <a class="button" href="{{ route('reports.population.pdf', array_filter(['barangay_id' => $selectedBarangayId, 'section' => $selectedSection])) }}"><x-app-icon name="document" /> Download PDF{{ $selectedSection !== 'summary' ? ' - '.$reportTitle : '' }}</a>
    </header>
    @unless($isSecretary)
    <form class="population-report-filter flex flex-wrap items-end gap-3" method="GET" action="{{ route('reports.population') }}"><input type="hidden" name="section" value="{{ $selectedSection }}"><div><label for="report-barangay">Barangay</label><select id="report-barangay" name="barangay_id"><option value="">All barangays — available records</option>@foreach($barangays as $barangay)<option value="{{ $barangay->id }}" @selected($selectedBarangayId === $barangay->id)>{{ $barangay->name }}</option>@endforeach</select></div><button type="submit">Apply filter</button></form>
    @endunless
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
                        <a class="population-category-link" href="{{ route('reports.population', array_filter(['barangay_id' => $selectedBarangayId, 'section' => $key])) }}" @if($selectedSection === $key) aria-current="page" @endif>
                            <span class="population-category-icon"><x-app-icon :name="$icon" /></span>
                            <span>{{ $sectionLabels[$key] }}</span>
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
