@extends('layouts.app')
@push('head')<link rel="stylesheet" href="{{ asset('css/population-submissions.css') }}?v={{ filemtime(public_path('css/population-submissions.css')) }}"><link rel="stylesheet" href="{{ asset('css/population-workspace.css') }}?v={{ filemtime(public_path('css/population-workspace.css')) }}">@endpush
@section('content')
<section class="population-workspace">
    <header class="population-page-heading">
        <div class="population-breadcrumb"><a href="{{ route('reports.population', array_filter(['barangay_id'=>$areaId])) }}">Population Reports</a> / Submission History</div>
        <h1>Submission History</h1>
        <p>{{ $isSecretary ? 'Your barangay’s submitted and archived population reports.' : 'Submitted and archived population reports from all barangays.' }}</p>
    </header>
    @include('reports._population-pages')
    <form method="GET" class="population-controls" aria-label="Filter submission history">
        @unless($isSecretary)<label class="population-control population-control-wide">Barangay<select name="barangay_id"><option value="">All barangays</option>@foreach($barangays as $area)<option value="{{ $area->id }}" @selected((int)$areaId===$area->id)>{{ $area->name }}</option>@endforeach</select></label>@endunless
        <label class="population-control population-control-year">Year<input type="number" name="report_year" min="1900" max="{{ now()->year }}" value="{{ $filters['report_year'] ?? '' }}" placeholder="All years"></label>
        <label class="population-control">Semester<select name="semester"><option value="">All semesters</option><option value="1" @selected(($filters['semester']??'')==1)>1st Semester</option><option value="2" @selected(($filters['semester']??'')==2)>2nd Semester</option></select></label>
        <button class="button" type="submit">Filter history</button>
    </form>
    <p class="population-history-description">{{ $submissions->total() }} reports · Saved copies stay unchanged when resident records are updated.</p>
    <div class="population-history">
    <div class="population-history-table"><table><thead><tr><th>Reference</th><th>Barangay</th><th>Reporting period</th><th>Inhabitants</th><th>Source / submitter</th><th>Submitted / archived on</th><th>Status</th><th>Report</th></tr></thead><tbody>
    @forelse($submissions as $submission)
        <tr><td>{{ $submission->reference }}</td><td>{{ $submission->barangay_name }}</td><td>{{ $submission->semester===1?'1st':'2nd' }} Semester {{ $submission->report_year }}</td><td>{{ number_format($submission->total_records) }}</td><td>{{ $submission->submitted_by_name }}</td><td>{{ $submission->submitted_at->format('M d, Y h:i A') }}</td><td>{{ $submission->is_historical_archive ? 'Archived historical report' : 'Submitted to Municipal DILG/Admin' }}</td><td><a href="{{ route('reports.population.submissions.show',$submission) }}">View saved report</a></td></tr>
    @empty<tr><td colspan="8">No population summary submissions yet.</td></tr>@endforelse
    </tbody></table></div></div>{{ $submissions->links() }}
</section>
@endsection
