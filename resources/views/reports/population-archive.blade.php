@extends('layouts.app')
@push('head')<link rel="stylesheet" href="{{ asset('css/population-submissions.css') }}?v={{ filemtime(public_path('css/population-submissions.css')) }}">@endpush
@section('content')
<section class="population-history">
    <h1>{{ $submission->barangay_name }} Historical Population Report · {{ $submission->reference }}</h1>
    <p>{{ $submission->semester === 1 ? '1st' : '2nd' }} Semester {{ $submission->report_year }} · Original photographed RBI Form C</p>
    <p>Archived in this system on {{ $submission->submitted_at->format('M d, Y h:i A') }}. {{ $archive['date_note'] }}</p>
    <div class="population-report-actions">
        <a class="button secondary-button" href="{{ route('reports.population.submissions.index', ['barangay_id'=>$submission->barangay_id]) }}">Population Report History</a>
        <a class="button" href="{{ route('reports.population.submissions.source', ['submission'=>$submission->id,'download'=>1]) }}">Download original photo</a>
        <a class="button secondary-button" href="{{ route('reports.population.submissions.source', $submission) }}" target="_blank" rel="noopener">Open original photo</a>
    </div>
    <div class="population-history-table"><table><thead><tr><th>Reported inhabitants</th><th>Households</th><th>Families</th><th>Sum of age brackets</th></tr></thead><tbody><tr>
        <td>{{ number_format($archive['population']) }}</td><td>{{ number_format($archive['households']) }}</td><td>{{ number_format($archive['families']) }}</td><td>{{ number_format($archive['age_total_sum']) }}</td>
    </tr></tbody></table></div>
    @if($archive['age_total_sum'] !== $archive['population'])
        <p role="note"><strong>Needs reconciliation:</strong> The reported population ({{ $archive['population'] }}) differs from the sum of the 17 age brackets ({{ $archive['age_total_sum'] }}). Both figures are preserved as read from the document; neither has been silently corrected.</p>
    @else
        <p>The 17 age-bracket totals add up to the reported population. This arithmetic check does not independently verify registry completeness.</p>
    @endif
    <p><strong>Analytics use:</strong> Historical population comparison only. This semestral report does not identify monthly arrivals or departures. Population changes can also reflect births, deaths and record corrections. It is excluded from monthly migration forecasting and MAPE evaluation.</p>
    <p>The original photograph below preserves the age, sex and sector breakdowns, blank fields and signatures. Blank entries are not interpreted as zero. Current resident records are unchanged.</p>
    <img src="{{ route('reports.population.submissions.source', $submission) }}" alt="Original {{ $submission->barangay_name }} population report, semester {{ $submission->semester }} of {{ $submission->report_year }}" style="display:block;width:100%;height:auto;margin-top:24px">
</section>
@endsection
