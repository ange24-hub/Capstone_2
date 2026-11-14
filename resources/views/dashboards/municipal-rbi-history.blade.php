@extends('layouts.app')
@push('head')
    <link rel="stylesheet" href="{{ asset('css/migration-history-experiment.css') }}?v={{ filemtime(public_path('css/migration-history-experiment.css')) }}">
@endpush
@section('content')
<section id="history-experiment" aria-labelledby="rbi-history-title">
    <header class="history-heading">
        <div><span class="history-eyebrow">Municipal administration · All barangays</span><h1 id="rbi-history-title">RBI report history</h1><p>Review the recorded reporting months and resident entry classifications for each barangay.</p></div>
        <a class="button secondary-button" href="{{ route('municipal.prediction-forecast') }}">Prediction analytics</a>
    </header>
    <p>Imported history and saved monthly forms are listed for review. Classified in-migration entries in saved forms feed the forecast by first-save month, including drafts. Unclassified entries need review.</p>
    <div class="history-metrics">
        <article><span>Barangays</span><strong>{{ $history['area_count'] }}</strong><small>All configured barangays</small></article>
        <article><span>Imported history entries</span><strong>{{ number_format(array_sum(array_column($history['coverage'], 'history_entries'))) }}</strong><small>Matched against saved forms during review</small></article>
        <article><span>Saved monthly forms</span><strong>{{ number_format(array_sum(array_column($history['coverage'], 'monthly_forms'))) }}</strong><small>Includes drafts awaiting submission</small></article>
    </div>
    @if($history['unmapped_reports'])<div class="history-notice" role="status"><strong>Report ownership needs review</strong><span>{{ count($history['unmapped_reports']) }} reports have an unknown or conflicting barangay assignment.</span></div>@endif
    <section class="history-detail">
        <h2>Report history by barangay</h2>
        <div class="table-wrap"><table><thead><tr><th>Barangay</th><th>History entries</th><th>Monthly forms</th><th>Recorded months</th><th>Source period</th><th>Undated entries</th><th>Review</th></tr></thead><tbody>
            @foreach($history['coverage'] as $area)
            <tr><th scope="row">{{ $area['name'] }}</th><td>{{ $area['history_entries'] }}</td><td>{{ $area['monthly_forms'] }}</td><td>{{ $area['recorded_months'] }}</td><td>{{ $area['first_month'] ?? 'No dated source' }}{{ $area['last_month'] ? '–'.$area['last_month'] : '' }}</td><td>{{ $area['undated'] }}</td><td><a href="{{ route('municipal.prediction-history', $area['id']) }}">Review entries</a></td></tr>
            @endforeach
        </tbody></table></div>
    </section>
</section>
@endsection
