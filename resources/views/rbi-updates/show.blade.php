@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/rbi-report-view.css') }}?v={{ filemtime(public_path('css/rbi-report-view.css')) }}">
@endpush

@section('content')
    @php($familyCount = collect($rbiUpdate->rows ?? [])->pluck('household_head')->filter()->unique()->count())
    @php($classifiedArrivals = $rbiUpdate->reporting_month ? collect($rbiUpdate->rows ?? [])->filter(fn ($row) => App\Support\RbiEntryClassification::isArrival($row, $rbiUpdate->reporting_month->format('Y-m')))->count() : 0)
    <section class="rbi-report-view rbi-report-detail" aria-labelledby="rbi-report-title">
        <header class="rbi-report-header">
            <div class="rbi-report-heading">
                <span class="rbi-report-emblem" aria-hidden="true"><x-portal-sticker name="document" /></span>
                <div>
                    <span class="rbi-report-eyebrow">RBI Monthly Form</span>
                    <h1 id="rbi-report-title">Monthly RBI report</h1>
                    <p>Barangay {{ $rbiUpdate->barangay_name ?: 'not set' }} <span aria-hidden="true">&middot;</span> {{ optional($rbiUpdate->reporting_month)->format('F Y') ?: 'Month not set' }}</p>
                </div>
            </div>
            <div class="rbi-report-downloads" aria-label="Report actions">
                <button class="rbi-report-button" type="button" onclick="window.print()"><x-app-icon name="document" />Print Report</button>
                <a class="rbi-report-button rbi-report-pdf" href="{{ route('rbi-updates.export-pdf', $rbiUpdate) }}"><x-app-icon name="document" />Download Consolidated PDF</a>
                <a class="rbi-report-button rbi-report-word" href="{{ route('rbi-updates.export-excel', $rbiUpdate) }}"><x-app-icon name="form" />Download Excel</a>
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
                <div><span class="rbi-metric-label">New inhabitants</span><strong>{{ count($rbiUpdate->rows ?? []) }}</strong><small>{{ $classifiedArrivals }} classified in-migration</small></div>
            </article>
            <article class="rbi-report-metric rbi-metric-deceased">
                <span class="rbi-metric-icon" aria-hidden="true"><x-app-icon name="form" /></span>
                <div><span class="rbi-metric-label">Deceased</span><strong>{{ count($rbiUpdate->deceased_rows ?? []) }}</strong></div>
            </article>
            <article class="rbi-report-metric rbi-metric-submitted">
                <span class="rbi-metric-icon" aria-hidden="true"><x-app-icon name="calendar" /></span>
                <div><span class="rbi-metric-label">First saved</span><strong>{{ $rbiUpdate->created_at?->copy()->timezone('Asia/Manila')->format('M d, Y') ?: 'Not recorded' }}</strong><small>{{ $rbiUpdate->submitted_at ? 'Submitted '.$rbiUpdate->submitted_at->copy()->timezone('Asia/Manila')->format('M d, Y h:i A') : 'Not submitted' }}</small></div>
            </article>
        </div>

        <section class="workflow-card rbi-entry-summary" aria-labelledby="rbi-classification-title">
            <h2 id="rbi-classification-title">Entry classifications for arrival analysis</h2><p>Classified in-migration entries are counted by the month each resident row was first saved. Submitting the same form does not count them again. Births and late registrations are excluded.</p>
            <div class="table-wrap"><table><thead><tr><th>Resident</th><th>Entry type</th><th>First saved</th><th>Previous residence</th><th>Confirmed arrival month</th><th>Exact date (if known)</th></tr></thead><tbody>
                @forelse($rbiUpdate->rows ?? [] as $row)<tr><td>{{ trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')) }}</td><td>{{ App\Support\RbiEntryClassification::labels()[$row['entry_type'] ?? ''] ?? 'Not classified — review monthly form' }}</td><td>{{ \Illuminate\Support\Carbon::parse(($row['first_saved_at'] ?? '') ?: $rbiUpdate->created_at)->timezone('Asia/Manila')->format('M d, Y h:i A') }}</td><td>{{ ($row['entry_type'] ?? '') === 'in_migration' ? (filter_var($row['previous_residence_unknown'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'Not recorded — confirmed in-migrant' : (($row['previous_residence'] ?? '') ?: 'Not recorded')) : '—' }}</td><td>{{ ($row['entry_type'] ?? '') === 'in_migration' ? ($row['arrival_month'] ?? substr($row['arrival_date'] ?? '',0,7)) : '—' }}</td><td>{{ ($row['entry_type'] ?? '') === 'in_migration' ? (($row['arrival_date'] ?? '') ?: 'Day not recorded') : '—' }}</td></tr>@empty<tr><td colspan="6">No resident entries.</td></tr>@endforelse
            </tbody></table></div>
        </section>
        <section class="rbi-preview-panel" aria-labelledby="rbi-preview-title">
            <div class="rbi-preview-heading"><h2 id="rbi-preview-title"><x-app-icon name="form" />Excel preview</h2><span>.xlsx</span></div>
            @include('rbi-updates._spreadsheet-preview')
            <div class="rbi-report-print-document">
                @include('rbi-updates._document-preview')
            </div>
        </section>
    </section>
@endsection
