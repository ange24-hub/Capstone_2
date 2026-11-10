@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset('css/statistical-forecast.css') }}?v={{ filemtime(public_path('css/statistical-forecast.css')) }}">
@endpush
@section('content')
@php
    if ($forecastPage === 'migration') {
        $targetLabel = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $forecasts['in']['log_month'])->format('F Y');
        $allSaved = filled($forecasts['in']['saved_at']) && filled($forecasts['out']['saved_at']);
    }
    $reviewError = $errors->hasAny(['log_month', 'source_hash', 'confirm_complete']);
    $movementError = $errors->hasAny(['resident_id', 'migration_type', 'transaction_date', 'confirm_date']);
    $workspaceRoute = 'prediction.'.$forecastPage;
@endphp
<div class="statistical-forecast">
    <header class="statistical-hero"><div><p class="statistical-eyebrow">{{ $forecastPage === 'population' ? 'Provisional planning tool' : 'Native PHP estimates' }}</p><h1>{{ $forecastPage === 'population' ? 'Provisional Population Estimate' : 'Migration forecast' }}</h1>
        <p>Barangay {{ $barangay->name }}</p></div></header>
    @if(session('status'))<p class="statistical-notice" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="statistical-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <nav class="statistical-section-nav statistical-page-tabs" aria-label="Forecast pages">
        <a href="{{ route('prediction.population', ['barangay_id' => $barangay->id]) }}" @if($forecastPage === 'population') aria-current="page" @endif><x-app-icon name="users" /><span>Population estimate</span></a>
        <a href="{{ route('prediction.migration', ['barangay_id' => $barangay->id]) }}" @if($forecastPage === 'migration') aria-current="page" @endif><x-app-icon name="trend" /><span>Migration forecast</span></a>
    </nav>
    <form method="GET" action="{{ route($workspaceRoute) }}" class="statistical-toolbar">
        <label>Barangay<select name="barangay_id">@foreach($barangays as $area)<option value="{{ $area->id }}" @selected($area->id === $barangay->id)>{{ $area->name }}</option>@endforeach</select></label>
        <button type="submit">View</button>
        @if($forecastPage === 'migration')<a href="{{ route('migration.dashboard', ['barangay_id'=>$barangay->id]) }}">Migration trends →</a>@endif
    </form>
    @if($forecastPage === 'population')
        @include('barangay.dashboard._population-projection')
    @else
        @include('barangay.dashboard._migration-prediction')
    @endif

</div>
@endsection
@push('scripts')
<script src="{{ asset('js/prediction-charts.js') }}?v={{ filemtime(public_path('js/prediction-charts.js')) }}" defer></script>
@endpush
