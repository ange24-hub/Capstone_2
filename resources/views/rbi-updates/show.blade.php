@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/rbi-report-view.css') }}?v={{ filemtime(public_path('css/rbi-report-view.css')) }}">
@endpush

@section('content')
    @php($familyCount = collect($rbiUpdate->rows ?? [])->pluck('household_head')->filter()->unique()->count())
    <section class="rbi-report-view" aria-labelledby="rbi-report-title">
        <header class="rbi-report-header">
            <div class="rbi-report-heading">
                <span class="rbi-report-emblem" aria-hidden="true"><x-app-icon name="document" /></span>
                <div>
                    <span class="rbi-report-eyebrow">RBI Monthly Form</span>
                    <h1 id="rbi-report-title">Monthly RBI report</h1>
                    <p>Barangay {{ $rbiUpdate->barangay_name ?: 'not set' }} <span aria-hidden="true">&middot;</span> {{ optional($rbiUpdate->reporting_month)->format('F Y') ?: 'Month not set' }}</p>
                </div>
            </div>
            <div class="rbi-report-downloads" aria-label="Download report">
                <a class="rbi-report-button rbi-report-pdf" href="{{ route('rbi-updates.export-pdf', $rbiUpdate) }}"><x-app-icon name="document" />Download Consolidated PDF</a>
                <a class="rbi-report-button rbi-report-word" href="{{ route('rbi-updates.export-word', $rbiUpdate) }}"><x-app-icon name="form" />Download Word Document</a>
                @if ($rbiUpdate->source_file_path)<a class="rbi-report-button" href="{{ route('rbi-updates.download', $rbiUpdate) }}">Original Upload</a>@endif
            </div>
        </header>

        <div class="rbi-report-metrics" aria-label="Monthly report summary">
            <article class="rbi-report-metric rbi-metric-status">
                <span class="rbi-metric-icon" aria-hidden="true"><x-app-icon name="check" /></span>
                <div><span class="rbi-metric-label">Report status</span><strong class="rbi-report-status rbi-report-status-{{ $rbiUpdate->status }}">{{ $rbiUpdate->statusLabel() }}</strong></div>
            </article>
            <article class="rbi-report-metric rbi-metric-families">
                <span class="rbi-metric-icon" aria-hidden="true"><x-app-icon name="users" /></span>
                <div><span class="rbi-metric-label">Families</span><strong>{{ $familyCount }}</strong></div>
            </article>
            <article class="rbi-report-metric rbi-metric-residents">
                <span class="rbi-metric-icon" aria-hidden="true"><x-app-icon name="users" /></span>
                <div><span class="rbi-metric-label">New inhabitants</span><strong>{{ count($rbiUpdate->rows ?? []) }}</strong></div>
            </article>
            <article class="rbi-report-metric rbi-metric-deceased">
                <span class="rbi-metric-icon" aria-hidden="true"><x-app-icon name="form" /></span>
                <div><span class="rbi-metric-label">Deceased</span><strong>{{ count($rbiUpdate->deceased_rows ?? []) }}</strong></div>
            </article>
            <article class="rbi-report-metric rbi-metric-submitted">
                <span class="rbi-metric-icon" aria-hidden="true"><x-app-icon name="calendar" /></span>
                <div><span class="rbi-metric-label">Submitted</span><strong>{{ $rbiUpdate->submitted_at?->copy()->timezone('Asia/Manila')->format('M d, Y') ?: 'Not submitted' }}</strong>@if($rbiUpdate->submitted_at)<small>{{ $rbiUpdate->submitted_at->copy()->timezone('Asia/Manila')->format('h:i A') }}</small>@endif</div>
            </article>
        </div>

        <section class="rbi-preview-panel" aria-labelledby="rbi-preview-title">
            <div class="rbi-preview-heading"><h2 id="rbi-preview-title"><x-app-icon name="document" />Document preview</h2><span>{{ count($wordPages) }} {{ Str::plural('page', count($wordPages)) }}</span></div>
            @include('rbi-updates._document-preview')
        </section>
    </section>
@endsection
