@extends('layouts.app')
@push('head')<link rel="stylesheet" href="{{ asset('css/population-workspace.css') }}?v={{ filemtime(public_path('css/population-workspace.css')) }}">@endpush
@section('content')
<section class="population-workspace population-report-builder">
    <header class="population-page-heading"><h1><span class="population-sticker population-sticker-blue"><x-app-icon name="document" /></span>Population Summary</h1><p>Open an original saved report or generate a new document from Consolidated.</p></header>
    @include('reports._population-pages')
    @include('reports._population-period-controls')
    <div class="population-period-empty"><x-app-icon name="inbox" /><h2>{{ $areaId ? 'No earlier saved report available' : 'Choose a barangay for historical reports' }}</h2>
        <p>{{ $areaId ? 'There is no saved copy for this period or an earlier period in this barangay. No current counts have been relabelled as historical data.' : 'Saved historical reports belong to individual barangays. Select a barangay above to open its original report.' }}</p>
        <a class="button secondary-button" href="{{ route('reports.population.submissions.index',array_filter(['barangay_id'=>$areaId])) }}">Browse Submission History</a>
    </div>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/population-reports.js') }}?v={{ filemtime(public_path('js/population-reports.js')) }}" defer></script>@endpush
