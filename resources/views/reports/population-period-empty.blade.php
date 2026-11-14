@extends('layouts.app')
@push('head')<link rel="stylesheet" href="{{ asset('css/population-workspace.css') }}?v={{ filemtime(public_path('css/population-workspace.css')) }}">@endpush
@section('content')
<section class="population-workspace population-report-builder">
    <header class="population-page-heading"><h1><span class="population-sticker population-sticker-blue"><x-app-icon name="document" /></span>Population Summary</h1><p>Open an original saved report or generate a new document from Consolidated.</p></header>
    @include('reports._population-pages')
    @include('reports._population-period-controls')
    <div class="population-period-empty">
        <x-app-icon name="inbox" />
        @if($futurePeriod)
            <h2>This reporting period has yet to start</h2>
            <p>You can generate this report when its year and semester begin.</p>
        @else
            <h2>No saved report for this period</h2>
            <p>Choose another year or semester, or open a saved report from Submission History.</p>
        @endif
        <a class="button secondary-button" href="{{ route('reports.population.submissions.index',array_filter(['barangay_id'=>$areaId])) }}">Browse Submission History</a>
    </div>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/population-reports.js') }}?v={{ filemtime(public_path('js/population-reports.js')) }}" defer></script>@endpush
