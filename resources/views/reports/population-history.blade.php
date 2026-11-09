@extends('layouts.app')
@push('head')<link rel="stylesheet" href="{{ asset('css/population-submissions.css') }}?v={{ filemtime(public_path('css/population-submissions.css')) }}">@endpush
@section('content')
<section class="population-history">
    <header><h1>Population Report Submission History</h1>
        <p>{{ $isSecretary ? 'Your barangay submissions and archived historical population reports.' : 'Population summaries received from barangay secretaries and archived historical reports.' }}</p>
        <a class="button secondary-button" href="{{ route('reports.population', array_filter(['barangay_id'=>$areaId])) }}">Current Population Summary</a></header>
    <form method="GET" class="population-history-filters">
        @unless($isSecretary)<label>Barangay<select name="barangay_id"><option value="">All barangays</option>@foreach($barangays as $area)<option value="{{ $area->id }}" @selected((int)$areaId===$area->id)>{{ $area->name }}</option>@endforeach</select></label>@endunless
        <label>Year<input type="number" name="report_year" min="1900" max="{{ now()->year }}" value="{{ $filters['report_year'] ?? '' }}" placeholder="All years"></label>
        <label>Semester<select name="semester"><option value="">All semesters</option><option value="1" @selected(($filters['semester']??'')==1)>1st Semester</option><option value="2" @selected(($filters['semester']??'')==2)>2nd Semester</option></select></label>
        <button class="button" type="submit">Filter history</button>
    </form>
    <p>Submitted copies are read-only. A changed report can be submitted as a new copy; earlier submissions stay available.</p>
    <div class="population-history-table"><table><thead><tr><th>Reference</th><th>Barangay</th><th>Reporting period</th><th>Inhabitants</th><th>Source / submitter</th><th>Submitted / archived on</th><th>Status</th><th>Report</th></tr></thead><tbody>
    @forelse($submissions as $submission)
        <tr><td>{{ $submission->reference }}</td><td>{{ $submission->barangay_name }}</td><td>{{ $submission->semester===1?'1st':'2nd' }} Semester {{ $submission->report_year }}</td><td>{{ number_format($submission->total_records) }}</td><td>{{ $submission->submitted_by_name }}</td><td>{{ $submission->submitted_at->format('M d, Y h:i A') }}</td><td>{{ $submission->is_historical_archive ? 'Archived historical report' : 'Submitted to Municipal DILG/Admin' }}</td><td><a href="{{ route('reports.population.submissions.show',$submission) }}">View saved report</a></td></tr>
    @empty<tr><td colspan="8">No population summary submissions yet.</td></tr>@endforelse
    </tbody></table></div>{{ $submissions->links() }}
</section>
@endsection
