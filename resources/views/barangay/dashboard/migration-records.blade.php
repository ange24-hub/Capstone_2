@extends('layouts.app')

@push('head')
<link rel="stylesheet" href="{{ asset('css/statistical-forecast.css') }}?v={{ filemtime(public_path('css/statistical-forecast.css')) }}">
@endpush

@section('content')
<div class="statistical-forecast forecast-workspace">
    <header class="forecast-header">
        <div class="forecast-heading-row">
            <div class="forecast-heading">
                <span class="forecast-heading-icon"><x-app-icon name="trend" /></span>
                <div>
                    <span class="forecast-heading-kicker">Population &amp; movement</span>
                    <h1>Migration records</h1>
                    <p class="forecast-location"><x-app-icon name="location" /> Barangay {{ $barangay->name }} <span class="forecast-scope-tag">Dated events and coverage</span></p>
                </div>
            </div>
            <a class="forecast-back-link" href="{{ route('migration.dashboard', ['barangay_id'=>$barangay->id]) }}"><x-app-icon name="activity" />Migration trends<x-app-icon name="arrow-right" /></a>
        </div>
        <div class="forecast-controls">
            <nav class="statistical-section-nav statistical-page-tabs" aria-label="Population forecasting and migration records">
                <a href="{{ route('prediction.population', ['barangay_id' => $barangay->id]) }}"><x-app-icon name="users" /><span>Population forecast</span></a>
                <a href="{{ route('prediction.migration', ['barangay_id' => $barangay->id]) }}" aria-current="page"><x-app-icon name="trend" /><span>Migration records</span></a>
            </nav>
            @if($barangays->count() > 1)
                <form method="GET" action="{{ route('prediction.migration') }}" class="statistical-toolbar forecast-scope-form">
                    <label for="forecast-barangay">Barangay</label>
                    <select id="forecast-barangay" name="barangay_id">@foreach($barangays as $area)<option value="{{ $area->id }}" @selected($area->id === $barangay->id)>{{ $area->name }}</option>@endforeach</select>
                    <button type="submit">View<x-app-icon name="arrow-right" /></button>
                </form>
            @endif
        </div>
    </header>
    @if(session('status'))<p class="statistical-notice" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="statistical-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @include('barangay.dashboard._migration-records')
</div>
@endsection
