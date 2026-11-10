@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset('css/statistical-forecast.css') }}?v={{ filemtime(public_path('css/statistical-forecast.css')) }}">
<link rel="stylesheet" href="{{ asset('css/forecast-workspace.css') }}?v={{ filemtime(public_path('css/forecast-workspace.css')) }}" media="screen">
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
<div class="statistical-forecast forecast-workspace">
    <header class="forecast-header">
        <div class="forecast-heading-row">
            <div class="forecast-heading">
                <span class="forecast-heading-icon"><x-app-icon :name="$forecastPage === 'population' ? 'users' : 'trend'" /></span>
                <div>
                    <h1>{{ $forecastPage === 'population' ? 'Provisional Population Estimate' : 'Migration forecast' }}</h1>
                    <p class="forecast-location"><x-app-icon name="location" /> Barangay {{ $barangay->name }} <span class="forecast-scope-tag">{{ $forecastPage === 'population' ? 'Planning estimate' : 'Native PHP estimates' }}</span></p>
                </div>
            </div>
            <a class="forecast-back-link" href="{{ route('migration.dashboard', ['barangay_id'=>$barangay->id]) }}"><x-app-icon name="activity" />Migration trends<x-app-icon name="arrow-right" /></a>
        </div>
        <div class="forecast-controls">
            <nav class="statistical-section-nav statistical-page-tabs" aria-label="Forecast pages">
                <a href="{{ route('prediction.population', ['barangay_id' => $barangay->id]) }}" @if($forecastPage === 'population') aria-current="page" @endif><x-app-icon name="users" /><span>Population estimate</span></a>
                <a href="{{ route('prediction.migration', ['barangay_id' => $barangay->id]) }}" @if($forecastPage === 'migration') aria-current="page" @endif><x-app-icon name="trend" /><span>Migration forecast</span></a>
            </nav>
            @if($barangays->count() > 1)
                <form method="GET" action="{{ route($workspaceRoute) }}" class="statistical-toolbar forecast-scope-form">
                    <label for="forecast-barangay">Barangay</label>
                    <select id="forecast-barangay" name="barangay_id">@foreach($barangays as $area)<option value="{{ $area->id }}" @selected($area->id === $barangay->id)>{{ $area->name }}</option>@endforeach</select>
                    <button type="submit">View<x-app-icon name="arrow-right" /></button>
                </form>
            @endif
        </div>
    </header>
    @if(session('status'))<p class="statistical-notice" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="statistical-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
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
