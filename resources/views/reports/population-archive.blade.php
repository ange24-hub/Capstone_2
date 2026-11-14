@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset('css/population-spreadsheet.css') }}?v={{ filemtime(public_path('css/population-spreadsheet.css')) }}">
<link rel="stylesheet" href="{{ asset('css/population-submissions.css') }}?v={{ filemtime(public_path('css/population-submissions.css')) }}">
<link rel="stylesheet" href="{{ asset('css/population-workspace.css') }}?v={{ filemtime(public_path('css/population-workspace.css')) }}">
<style>@include('reports._population-monitoring-styles')</style>
@endpush
@section('content')
<section class="population-workspace population-report-builder population-report-monitoring population-archive-document">
    <header class="population-page-heading population-builder-heading"><div><span class="population-builder-eyebrow">Saved historical report · {{ $submission->reference }}</span><h1><span class="population-sticker population-sticker-blue"><x-app-icon name="document" /></span>{{ $submission->barangay_name }} Population Summary</h1><p>{{ $submission->semester===1?'1st':'2nd' }} Semester {{ $submission->report_year }} · Original reporting period preserved</p></div><span class="population-history-badge"><x-app-icon name="clock" />Historical document</span></header>
    @include('reports._population-pages')
    @include('reports._population-period-controls')
    <div class="population-builder-stats" aria-label="Original reported totals">
        @foreach([['users','Reported inhabitants',$archive['population']],['home','Households',$archive['households']],['directory','Families',$archive['families']]] as [$icon,$label,$value])<div><span class="population-builder-stat-icon"><x-app-icon :name="$icon" /></span><p><strong>{{ number_format($value) }}</strong><span>{{ $label }}</span></p></div>@endforeach
    </div>
    @if($archive['age_total_sum'] !== $archive['population'])
        <p class="population-period-notice" role="note"><strong>Needs reconciliation:</strong> Reported population: {{ $archive['population'] }}. Sum of age brackets: {{ $archive['age_total_sum'] }}. Both original figures are preserved.</p>
    @endif
    <div class="population-builder-document">
        <div class="population-preview-bar"><div><span class="population-builder-document-label">Document preview</span><h2>Historical Population Summary</h2><p>{{ $submission->reference }} · {{ $submission->semester===1?'1st':'2nd' }} Semester {{ $submission->report_year }} · A4</p></div><div class="population-report-actions">
            <a class="button secondary-button" href="{{ route('reports.population.submissions.show',['submission'=>$submission->id,'print'=>1]) }}" target="_blank" rel="noopener"><x-app-icon name="print" />Print Report</a>
            <a class="button population-builder-pdf" href="{{ route('reports.population.submissions.pdf',$submission) }}"><x-app-icon name="download" />Download PDF</a>
            <a class="button secondary-button" href="{{ route('reports.population.submissions.excel',$submission) }}"><x-app-icon name="download" />Download Excel</a>
        </div></div>
        <div class="population-builder-source"><x-app-icon name="shield" /><p><strong>Digitized original report · historical counts</strong><span>No current Consolidated values or current signatures are applied to this saved report.</span></p></div>
        <p class="population-builder-mobile-hint">Swipe to view the full document, or download the A4 PDF.</p>
        @include('reports.population-monitoring')
    </div>
    <details class="population-archive-source-details"><summary>Original source &amp; archive details</summary><p>Original photographed RBI Form C, retained unchanged. Archived on {{ $submission->submitted_at->format('M d, Y h:i A') }}. {{ $archive['date_note'] }}</p><p>Original signatures remain in the retained source. Blank source cells are not treated as zero. Historical population reports are excluded from monthly migration forecasting and MAPE evaluation.</p><div class="population-report-actions"><a class="button secondary-button" href="{{ route('reports.population.submissions.source',$submission) }}" target="_blank" rel="noopener">Open original source</a><a class="button secondary-button" href="{{ route('reports.population.submissions.source',['submission'=>$submission->id,'download'=>1]) }}">Download original photo</a></div></details>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/population-reports.js') }}?v={{ filemtime(public_path('js/population-reports.js')) }}" defer></script>@endpush
@if($printMode)@push('scripts')<script>window.addEventListener('load',()=>window.print());</script>@endpush @endif
