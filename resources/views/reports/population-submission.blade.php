@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset('css/population-spreadsheet.css') }}?v={{ filemtime(public_path('css/population-spreadsheet.css')) }}">
<link rel="stylesheet" href="{{ asset('css/population-submissions.css') }}?v={{ filemtime(public_path('css/population-submissions.css')) }}">
@if(auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))
<link rel="stylesheet" href="{{ asset('css/population-workspace.css') }}?v={{ filemtime(public_path('css/population-workspace.css')) }}">
@endif
<style>@include('reports._population-monitoring-styles')</style>
@endpush
@section('content')
<section class="population-report population-spreadsheet population-report-monitoring population-workspace">
    <header class="population-submission-toolbar">
        <h1>Submitted Population Summary · {{ $submission->reference }}</h1>
        @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
        <p>Submitted to Municipal DILG/Admin by {{ $submission->submitted_by_name }} on {{ $submission->submitted_at->format('M d, Y h:i A') }}.</p>
        <p>This is the saved report as reviewed before submission. Later registry updates do not change this copy.</p>
        <div class="population-report-actions">
            <a class="button secondary-button" href="{{ route('reports.population.submissions.index', auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU) ? ['report_year'=>$submission->report_year,'semester'=>$submission->semester] : []) }}">{{ auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU) ? 'Back to semester reports' : 'Submission History' }}</a>
            @if(auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))
                <a class="button secondary-button" href="{{ route('reports.population', ['section' => 'residents']) }}">Current registry reports</a>
            @endif
            <a class="button secondary-button" href="{{ route('reports.population.submissions.show', ['submission'=>$submission->id,'print'=>1]) }}" target="_blank" rel="noopener">Print Report</a>
            <a class="button" href="{{ route('reports.population.submissions.pdf',$submission) }}">Download PDF</a>
            <a class="button secondary-button" href="{{ route('reports.population.submissions.excel',$submission) }}">Download Excel{{ !empty($report['signatures']) ? ' (data only)' : '' }}</a>
        </div>
    </header>
    @if(auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))
        @include('reports._population-period-controls')
    @endif
    @include('reports.population-content')
</section>
@endsection
@if($printMode)@push('scripts')<script>window.addEventListener('load', () => window.print());</script>@endpush @endif

@push('scripts')<script src="{{ asset('js/population-reports.js') }}?v={{ filemtime(public_path('js/population-reports.js')) }}" defer></script>@endpush
