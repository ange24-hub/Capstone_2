@extends('layouts.app')
@push('head')<link rel="stylesheet" href="{{ asset('css/migration-forecast.css') }}?v={{ filemtime(public_path('css/migration-forecast.css')) }}">@endpush
@section('content')
<section class="migration-forecast-card" aria-labelledby="saved-forecast-title">
    <h1 id="saved-forecast-title">Saved future estimate</h1>
    <p>{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m',$future['target_month'])->format('F Y') }} · Saved {{ $futureSnapshot['saved_at'] }} by {{ $futureSnapshot['saved_by']['name'] }}.</p>
    <p><strong>Experimental SVR · Classified arrival records only.</strong> These figures retain the original inputs and model version. They are not verified physical migration counts or a municipal total.</p>
    <div class="forecast-summary-grid">@foreach($future['rows'] as $row)<article><span>{{ $row['barangay'] }}</span><strong>{{ number_format($row['prediction'],2) }}</strong><small>Last observed: {{ $row['last_observed_month'] }}; input gap {{ $row['gap_months'] }} months.</small></article>@endforeach</div>
    <p>Old data and long gaps make this highly uncertain. Future MAE/RMSE and out-migration remain unavailable.</p>
    <p><a href="{{ route('municipal.prediction-pooled.future.evidence',$futureSnapshot['id']) }}">Download future forecast evidence (JSON)</a> · <a href="{{ route('municipal.prediction-forecast',array_filter(['barangay_id'=>$future['selected_barangay_id']??null])) }}">Back to prediction analytics</a></p>
</section>
@endsection
