@extends('layouts.app')
@section('content')
<section class="dashboard-page population-report grid gap-6" aria-labelledby="migration-report-title">
<header class="dashboard-page-header dashboard-page-header-with-actions flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 bg-transparent bg-none pb-6 shadow-none">
            <x-workspace-heading icon="trend"><span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Registry reports</span><h1 id="migration-report-title">Monthly Migration Report</h1><p>{{ $report['scopeLabel'] }} &middot; {{ $selectedYear }}</p></x-workspace-heading>
<a class="button" href="{{ route('reports.migration.pdf', array_filter(['barangay_id' => $selectedBarangayId, 'year' => $selectedYear])) }}"><x-app-icon name="document" /> Download PDF</a>
</header>
<form class="population-report-filter flex flex-wrap items-end gap-3" method="GET" action="{{ route('reports.migration') }}">
@unless($isSecretary)<div><label for="migration-report-barangay">Barangay</label><select id="migration-report-barangay" name="barangay_id"><option value="">All barangays</option>@foreach($barangays as $barangay)<option value="{{ $barangay->id }}" @selected($selectedBarangayId === $barangay->id)>{{ $barangay->name }}</option>@endforeach</select></div>@endunless
<div><label for="migration-report-year">Reporting year</label><input id="migration-report-year" name="year" type="number" min="1900" max="9999" value="{{ $selectedYear }}" required></div><button type="submit">Apply filter</button>
</form>
<div class="population-coverage-notice rounded-xl border border-blue-200 bg-blue-50 p-4 text-blue-900" role="note"><strong>{{ number_format($report['total']) }} recorded movement events in {{ $selectedYear }}</strong><span>Based on movement dates. Reporting completeness has not been verified.</span></div>
@include('reports.migration-tables')
</section>
@endsection
