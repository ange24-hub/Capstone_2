@extends('layouts.app')
@push('head')<link rel="stylesheet" href="{{ asset('css/population-inbox.css') }}?v={{ filemtime(public_path('css/population-inbox.css')) }}" media="screen">@endpush
@section('content')
<section class="population-inbox dashboard-page" aria-labelledby="population-inbox-title">
    <header class="dashboard-page-header">
        <x-workspace-heading icon="document"><span class="dashboard-eyebrow">Municipal report inbox</span><h1 id="population-inbox-title">Population reports</h1><p>Reports received from barangays, organised by reporting year and semester.</p></x-workspace-heading>
        <span class="population-inbox-received"><x-app-icon name="check" /> Submitted reports only</span>
    </header>
    <form method="GET" class="population-inbox-filters" aria-label="Choose submitted reporting period">
        <div><label for="inbox-year">Reporting year</label><select id="inbox-year" name="report_year">@foreach($years as $option)<option value="{{ $option }}" @selected($year === (int)$option)>{{ $option }}</option>@endforeach</select></div>
        <div><label for="inbox-semester">Semester</label><select id="inbox-semester" name="semester"><option value="1" @selected($semester===1)>1st Semester · Jan–Jun</option><option value="2" @selected($semester===2)>2nd Semester · Jul–Dec</option></select></div>
        <div><label for="inbox-barangay">Barangay</label><select id="inbox-barangay" name="barangay_id"><option value="">All submitted barangays</option>@foreach($barangays as $area)<option value="{{ $area->id }}" @selected($areaId===$area->id)>{{ $area->name }}</option>@endforeach</select></div>
        <button type="submit" class="button"><x-app-icon name="filter" />Show reports</button>
    </form>
    <nav class="population-inbox-semesters" aria-label="Semesters for {{ $year }}">
        @foreach([1=>'January–June',2=>'July–December'] as $number=>$months)
        <a href="{{ route('reports.population.submissions.index', array_filter(['report_year'=>$year,'semester'=>$number,'barangay_id'=>$areaId])) }}" @if($semester===$number) aria-current="page" @endif><span>{{ $number===1?'1st':'2nd' }} Semester <small>{{ $year }} · {{ $months }}</small></span><strong>{{ $periodCounts[$number] ?? 0 }} <small>barangays</small></strong></a>
        @endforeach
    </nav>
    <div class="population-inbox-heading"><div><h2>{{ $semester===1?'1st':'2nd' }} Semester {{ $year }}</h2><p>{{ $submissions->total() }} {{ $submissions->total()===1?'report received':'reports received' }}@if($areaId) · Filtered by barangay @endif</p></div><span>Saved copies submitted by the barangay</span></div>
    @if($submissions->isEmpty())
        <div class="population-inbox-empty"><x-app-icon name="inbox" /><h2>No reports received for this period</h2><p>A barangay report appears here after its secretary submits it. Choose another year or semester to view earlier submissions.</p></div>
    @else
    <div class="population-inbox-grid">
        @foreach($submissions as $submission)
        <article class="population-inbox-card">
            <header><span class="population-inbox-report-icon"><x-app-icon name="document" /></span><div><span>{{ $submission->reference }}</span><h3>Barangay {{ $submission->barangay_name }}</h3></div><span class="population-inbox-status"><x-app-icon name="check" />Received</span></header>
            <div class="population-inbox-count"><strong>{{ number_format($submission->total_records) }}</strong><span>reported inhabitants</span></div>
            <dl><div><dt>Submitted by</dt><dd>{{ $submission->submitted_by_name }}</dd></div><div><dt>Received on</dt><dd>{{ $submission->submitted_at->format('M d, Y · h:i A') }}</dd></div></dl>
            <footer><a class="button" href="{{ route('reports.population.submissions.show',$submission) }}">View report<x-app-icon name="arrow-right" /></a><a class="button secondary-button" href="{{ route('reports.population.submissions.pdf',$submission) }}" aria-label="Download PDF for {{ $submission->barangay_name }}">PDF</a><a class="button secondary-button" href="{{ route('reports.population.submissions.excel',$submission) }}" aria-label="Download Excel for {{ $submission->barangay_name }}">Excel</a></footer>
        </article>
        @endforeach
    </div>
    {{ $submissions->links() }}
    @endif
</section>
@endsection
