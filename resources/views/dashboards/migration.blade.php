@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/migration-insights.css') }}?v={{ filemtime(public_path('css/migration-insights.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/migration-workspace.css') }}?v={{ filemtime(public_path('css/migration-workspace.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/migration-forecast.css') }}?v={{ filemtime(public_path('css/migration-forecast.css')) }}">
@endpush

@section('content')
    @php
        $netMovement = $totalIn - $totalOut;
        $selectedBarangay = $barangays->firstWhere('id', (int) $selectedBarangayId);
        $migrationSection = in_array(request('section'), ['forecast', 'events'], true) ? request('section') : 'trends';
        $isAdminForecast = request()->routeIs('municipal.prediction-forecast');
        $forecastRoute = $isAdminForecast ? 'municipal.prediction-forecast' : 'migration.dashboard';
    @endphp

    <section class="dashboard-page migration-dashboard" aria-labelledby="migration-dashboard-title">
        <header class="dashboard-page-header dashboard-page-header-with-actions flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 bg-transparent bg-none pb-6 shadow-none">
            <x-workspace-heading icon="trend">
                <span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Population Movement</span>
                <h1 id="migration-dashboard-title">{{ $isAdminForecast ? 'Prediction analytics' : 'Migration Trends' }}</h1>
                <p>{{ match ($migrationSection) {
                    'forecast' => 'Verified migration forecasts and experimental SVR arrival estimates for supported barangays.',
                    'events' => 'Review the latest recorded arrivals and departures.',
                    default => 'Compare monthly arrivals and departures for '.$selectedYear.'.',
                } }}</p>
            </x-workspace-heading>
        </header>

        <nav class="migration-section-nav" aria-label="Migration trends sections">
            @foreach ((auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU) && auth()->user()->isApproved() ? ['trends' => 'Trends', 'forecast' => 'Verified forecast', 'events' => 'Recent events'] : ['trends' => 'Trends', 'events' => 'Recent events']) as $section => $label)
                <a href="{{ route($isAdminForecast && $section === 'forecast' ? $forecastRoute : 'migration.dashboard', array_filter(['year' => $selectedYear, 'barangay_id' => $selectedBarangayId, 'section' => $section], fn ($value) => $value !== null)) }}" @if($migrationSection === $section) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            @if(auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU))
            <a href="{{ route('municipal.rbi-report-history') }}">RBI report history</a>
            @endif
        </nav>

        <form class="migration-filters" method="GET" action="{{ route($forecastRoute) }}" aria-label="Migration filters">
            <input type="hidden" name="section" value="{{ $migrationSection }}">
            <div class="migration-filter-field">
                <label for="migration-year">Reporting year</label>
                <input id="migration-year" name="year" type="number" min="1900" max="9999" value="{{ $selectedYear }}" required>
                @error('year')<p role="alert">{{ $message }}</p>@enderror
            </div>
            @if (! auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))
                <div class="migration-filter-field migration-area-field">
                    <label for="migration-barangay">Barangay</label>
                    <select id="migration-barangay" name="barangay_id"><option value="">All barangays</option>@foreach ($barangays as $barangay)<option value="{{ $barangay->id }}" @selected((string) $selectedBarangayId === (string) $barangay->id)>{{ $barangay->name }}</option>@endforeach</select>
                </div>
            @else
                <div class="migration-filter-scope"><span>Barangay scope</span><strong>{{ auth()->user()->barangay?->name }}</strong></div>
            @endif
            <button type="submit"><x-app-icon name="filter" />{{ $migrationSection === 'forecast' ? 'Show forecast' : 'Apply filters' }}</button>
        </form>

        @if ($selectedBarangay && ! auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))
            <div class="active-filter"><span>Showing Barangay {{ $selectedBarangay->name }} &middot; {{ $selectedYear }}</span><a href="{{ route($forecastRoute, ['year' => $selectedYear, 'section' => $migrationSection]) }}">Clear barangay filter</a></div>
        @endif

        @if ($demoRecordCount > 0 && $migrationSection !== 'forecast')
            <p role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">Demo data included: {{ $demoRecordCount }} synthetic migration events. Remove demo data before using official reports.</p>
        @endif

        @if ($migrationSection === 'trends')
        <section class="dashboard-metrics grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Migration summary">
            <article class="metric-card metric-primary rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="users" /></span><div><span>Registry population</span><strong>{{ number_format($totalInhabitants) }}</strong><small>Current resident profiles in scope</small></div></article>
            <article class="metric-card metric-success rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon metric-arrow-in flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="arrow-right" /></span><div><span>Arrivals</span><strong>{{ number_format($totalIn) }}</strong><small>Reviewed RBI arrivals</small></div></article>
            <article class="metric-card metric-warning rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="arrow-right" /></span><div><span>Departures</span><strong>{{ number_format($totalOut) }}</strong><small>Recorded out-migration</small></div></article>
            <article class="metric-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md {{ $netMovement < 0 ? 'metric-danger' : 'metric-info' }}"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="activity" /></span><div><span>Net movement</span><strong>{{ $netMovement > 0 ? '+' : '' }}{{ number_format($netMovement) }}</strong><small>Arrivals minus departures</small></div></article>
        </section>
        @endif

        @if ($migrationSection === 'forecast')
            @include('dashboards._migration-forecast')
        @endif

        @if ($migrationSection === 'trends')
        <div class="analytics-grid migration-analysis">
            <section class="dashboard-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" aria-labelledby="barangay-movement-title" id="migration-area-panel">
                <header class="dashboard-card-header flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4"><div><span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Area comparison</span><h2 id="barangay-movement-title">Movement by barangay</h2><p>Areas with the most movement appear first.</p></div><span class="count-chip">{{ $barangayStats->count() }} areas</span></header>
                @if ($barangayStats->isEmpty())
                    <div class="dashboard-empty-state rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-slate-600"><span class="empty-icon"><x-app-icon name="map" /></span><strong>No movement data</strong><span>Recorded migration events will appear here.</span></div>
                @else
                    <div class="movement-list">
                        @php
                            $maxEvents = max(1, (int) $barangayStats->max('total'));
                        @endphp
                        @foreach ($barangayStats as $stat)
                            <article class="movement-row">
                                <div class="movement-row-head"><strong>Barangay {{ $stat['barangay']->name }}</strong><span>{{ $stat['total'] }} {{ \Illuminate\Support\Str::plural('event', $stat['total']) }}</span></div>
                                <div class="movement-bar"><i style="width: {{ max(4, ($stat['total'] / $maxEvents) * 100) }}%"></i></div>
                                <div class="movement-row-values"><span class="value-in">{{ $stat['in'] }} in</span><span class="value-out">{{ $stat['out'] }} out</span><strong class="{{ $stat['net'] < 0 ? 'negative' : 'positive' }}">{{ $stat['net'] > 0 ? '+' : '' }}{{ $stat['net'] }} net</strong></div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="dashboard-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" aria-labelledby="municipal-trend-title" id="migration-monthly-panel">
                <header class="dashboard-card-header flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4"><div><span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Over time</span><h2 id="municipal-trend-title">Monthly trend &middot; {{ $selectedYear }}</h2><p>January&ndash;December. Months without recorded events show zero.</p></div><span class="section-icon"><x-app-icon name="calendar" /></span></header>
                @php
                    $chartMax = max(4, (int) ceil(max($monthlyTrend->max('in'), $monthlyTrend->max('out')) / 4) * 4);
                    $arrivalPoints = $monthlyTrend->map(fn ($month, $index) => (48 + $index * 44).','. (210 - $month['in'] / $chartMax * 176))->implode(' ');
                    $departurePoints = $monthlyTrend->map(fn ($month, $index) => (48 + $index * 44).','. (210 - $month['out'] / $chartMax * 176))->implode(' ');
                @endphp
                <div class="migration-chart-wrap">
                    @if ($totalIn + $totalOut > 0)
                    <div class="migration-chart-legend"><span class="migration-legend-item"><i class="migration-legend-swatch migration-legend-in" aria-hidden="true"></i>Arrivals</span><span class="migration-legend-item"><i class="migration-legend-swatch migration-legend-out" aria-hidden="true"></i>Departures</span></div>
                    <svg class="migration-monthly-chart" viewBox="0 0 560 250" role="img" aria-labelledby="migration-chart-title migration-chart-description">
                        <title id="migration-chart-title">Monthly migration in {{ $selectedYear }}</title>
                        <desc id="migration-chart-description">Monthly arrivals and departures from January to December. Exact counts are in the table below.</desc>
                        @for ($tick = 0; $tick <= 4; $tick++)
                            <line x1="48" y1="{{ 210 - $tick * 44 }}" x2="532" y2="{{ 210 - $tick * 44 }}" stroke="#dce4eb" />
                            <text x="38" y="{{ 214 - $tick * 44 }}" text-anchor="end">{{ $chartMax * $tick / 4 }}</text>
                        @endfor
                        <polyline points="{{ $arrivalPoints }}" fill="none" stroke="var(--migration-in)" stroke-width="3" />
                        <polyline points="{{ $departurePoints }}" fill="none" stroke="var(--migration-out)" stroke-width="3" stroke-dasharray="7 5" />
                        @foreach ($monthlyTrend as $month)
                            @php($monthLabel = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month['month'])->format('M'))
                            <text x="{{ 48 + $loop->index * 44 }}" y="234" text-anchor="middle">{{ $monthLabel }}</text>
                            <circle cx="{{ 48 + $loop->index * 44 }}" cy="{{ 210 - $month['in'] / $chartMax * 176 }}" r="4" fill="var(--migration-in)"><title>{{ $monthLabel }}: {{ $month['in'] }} arrivals</title></circle>
                            <rect x="{{ 45 + $loop->index * 44 }}" y="{{ 207 - $month['out'] / $chartMax * 176 }}" width="6" height="6" fill="var(--migration-out)"><title>{{ $monthLabel }}: {{ $month['out'] }} departures</title></rect>
                        @endforeach
                    </svg>
                    @else
                        <p class="migration-chart-empty">No migration events recorded for {{ $selectedYear }}.</p>
                    @endif
                </div>
                <details class="migration-monthly-details"><summary>View monthly breakdown</summary>
                    <div class="table-wrap clean-table-wrap w-full overflow-x-auto rounded-xl border border-slate-200"><table class="clean-table compact-data-table"><thead><tr><th>Month</th><th>Arrivals</th><th>Departures</th><th>Net</th></tr></thead><tbody>@foreach ($monthlyTrend as $month)<tr><td><strong>{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month['month'])->format('M Y') }}</strong></td><td><span class="value-in">{{ $month['in'] }}</span></td><td><span class="value-out">{{ $month['out'] }}</span></td>@php($monthNet = $month['in'] - $month['out'])<td><strong class="{{ $monthNet < 0 ? 'negative' : 'positive' }}">{{ $monthNet > 0 ? '+' : '' }}{{ $monthNet }}</strong></td></tr>@endforeach</tbody></table></div>
                </details>
            </section>
        </div>
        @endif

        @if ($migrationSection === 'trends' && $analyticsInsights)
            @include('dashboards._migration-insights')
        @endif

        @if ($migrationSection === 'events')
        <section class="dashboard-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" aria-labelledby="recent-events-title">
            <header class="dashboard-card-header flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4"><div><span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Latest activity</span><h2 id="recent-events-title">Recent departure records</h2><p>The 12 most recent dated departures. Arrival counts come from classified RBI entries by first-save month.</p></div><span class="count-chip">{{ $records->count() }} shown</span></header>
            @if ($records->isEmpty())
                <div class="dashboard-empty-state rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-slate-600"><span class="empty-icon"><x-app-icon name="inbox" /></span><strong>No migration events</strong><span>New records will appear here.</span></div>
            @else
                <div class="table-wrap clean-table-wrap w-full overflow-x-auto rounded-xl border border-slate-200"><table class="clean-table"><thead><tr><th>Resident</th><th>Barangay</th><th>Movement</th><th>Date</th><th>Origin</th><th>Destination</th></tr></thead><tbody>@foreach ($records as $record)<tr><td><strong>{{ $record->inhabitant->fullName() }}</strong></td><td>Barangay {{ $record->barangay->name }}</td><td><span class="movement-type movement-type-{{ $record->type }}">{{ $record->typeLabel() }}</span></td><td>{{ $record->movement_date->format('M d, Y') }}</td><td>{{ $record->origin ?: 'Not provided' }}</td><td>{{ $record->destination ?: 'Not provided' }}</td></tr>@endforeach</tbody></table></div>
            @endif
        </section>
        @endif
    </section>
@endsection
