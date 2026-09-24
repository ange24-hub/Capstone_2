@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/migration-workspace.css') }}?v={{ filemtime(public_path('css/migration-workspace.css')) }}">
@endpush

@section('content')
    @php
        $netMovement = $totalIn - $totalOut;
        $selectedBarangay = $barangays->firstWhere('id', (int) $selectedBarangayId);
    @endphp

    <section class="dashboard-page migration-dashboard" aria-labelledby="migration-dashboard-title">
        <header class="dashboard-page-header dashboard-page-header-with-actions flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 bg-transparent bg-none pb-6 shadow-none">
            <x-workspace-heading icon="trend">
                <span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Population Movement</span>
                <h1 id="migration-dashboard-title">Migration trends</h1>
                <p>Compare monthly arrivals and departures for {{ $selectedYear }}.</p>
            </x-workspace-heading>
        </header>

        <form class="migration-filters" method="GET" action="{{ route('migration.dashboard') }}" aria-label="Migration filters">
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
            <button type="submit"><x-app-icon name="filter" />Apply filters</button>
        </form>

        @if ($selectedBarangay)
            <div class="active-filter"><span>Showing Barangay {{ $selectedBarangay->name }} · {{ $selectedYear }}</span><a href="{{ route('migration.dashboard', ['year' => $selectedYear]) }}">Clear barangay filter</a></div>
        @endif

        @if ($demoRecordCount > 0)
            <p role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">Demo data included: {{ $demoRecordCount }} synthetic migration events. Remove demo data before using official reports.</p>
        @endif

        <section class="dashboard-metrics grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Migration summary">
            <article class="metric-card metric-primary rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="users" /></span><div><span>Registry population</span><strong>{{ number_format($totalInhabitants) }}</strong><small>Current resident profiles in scope</small></div></article>
            <article class="metric-card metric-success rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon metric-arrow-in flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="arrow-right" /></span><div><span>Arrivals</span><strong>{{ number_format($totalIn) }}</strong><small>Recorded in-migration</small></div></article>
            <article class="metric-card metric-warning rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="arrow-right" /></span><div><span>Departures</span><strong>{{ number_format($totalOut) }}</strong><small>Recorded out-migration</small></div></article>
            <article class="metric-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md {{ $netMovement < 0 ? 'metric-danger' : 'metric-info' }}"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="activity" /></span><div><span>Net movement</span><strong>{{ $netMovement > 0 ? '+' : '' }}{{ number_format($netMovement) }}</strong><small>Arrivals minus departures</small></div></article>
        </section>


                {{-- Out-Migration Predictive Analytics --}}
        <section class="dashboard-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6"
                 aria-labelledby="out-migration-forecast-title">

            <header class="dashboard-card-header flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-emerald-700">
                        Predictive Analytics
                    </span>

                    <h2 id="out-migration-forecast-title">
                        Out-Migration Forecast
                    </h2>

                    <p>
                        Estimate for {{ $predictionMonthLabel }} using completed months through {{ $sourceMonthLabel }}.
                    </p>
                </div>

                <span class="section-icon">
                    <x-app-icon name="trend" />
                </span>
            </header>

            <div class="migration-forecast-stats">

                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <span class="text-sm text-slate-500">
                        {{ $sourceMonthLabel }}
                    </span>

                    <strong class="mt-1 block text-2xl text-slate-900">
                        {{ number_format($currentMonthOut ?? 0) }}
                    </strong>

                    <small class="text-slate-500">
                        recorded out-migration
                    </small>
                </article>

                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <span class="text-sm text-slate-500">
                        {{ $previousMonthLabel }}
                    </span>

                    <strong class="mt-1 block text-2xl text-slate-900">
                        {{ number_format($previousMonthOut ?? 0) }}
                    </strong>

                    <small class="text-slate-500">
                        recorded out-migration
                    </small>
                </article>

                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <span class="text-sm text-slate-500">
                        3-Month Average
                    </span>

                    <strong class="mt-1 block text-2xl text-slate-900">
                        {{ number_format($threeMonthAverage ?? 0, 2) }}
                    </strong>

                    <small class="text-slate-500">
                        average monthly departures
                    </small>
                </article>

            </div>

            @if ($predictedOutMigration !== null)

                <div class="migration-estimate mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-5">

                    <div class="flex flex-wrap items-center justify-between gap-4">

                        <div>
                            <span class="text-sm font-semibold uppercase tracking-wider text-emerald-700">
                                Estimate for {{ $predictionMonthLabel }}
                            </span>

                            <div class="mt-1 text-3xl font-bold text-emerald-900">
                                {{ number_format(round($predictedOutMigration)) }}
                                <span class="text-base font-medium">
                                    estimated departure events
                                </span>
                            </div>

                            <p class="mt-2 text-sm text-emerald-800">
                                Based on recorded migration events in the selected barangay scope.
                            </p>
                        </div>

                        <div class="rounded-xl bg-white px-5 py-4 text-center shadow-sm">
                            <span class="block text-xs uppercase tracking-wider text-slate-500">
                                Model
                            </span>

                            <strong class="block text-sm text-slate-900">
                                {{ $predictionMethod }}
                            </strong>

                            <span class="block text-xs text-slate-500">
                                Exploratory estimate
                            </span>
                        </div>

                    </div>

                </div>

            @else

                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-5">

                    <strong class="text-amber-900">
                        Prediction currently unavailable.
                    </strong>

                    <p class="mt-1 text-sm text-amber-800">
                        {{ $predictionError }}
                    </p>

                </div>

            @endif

            <p class="mt-4 text-xs text-slate-500">
                @if ($predictionMethod === 'Gradient Boosting prototype')
                    The prototype model used synthetically assigned training dates.
                @else
                    When the model service is unavailable, the estimate uses the mean of the last three completed months.
                @endif
                Months without recorded events contribute zero recorded events; reporting completeness is unverified.
                These estimates are not validated forecasts or official migration statistics.
            </p>

        </section>

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
                <header class="dashboard-card-header flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4"><div><span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Over time</span><h2 id="municipal-trend-title">Monthly trend · {{ $selectedYear }}</h2><p>January–December. Months without recorded events show zero.</p></div><span class="section-icon"><x-app-icon name="calendar" /></span></header>
                @php
                    $chartMax = max(4, (int) ceil(max($monthlyTrend->max('in'), $monthlyTrend->max('out')) / 4) * 4);
                    $arrivalPoints = $monthlyTrend->map(fn ($month, $index) => (48 + $index * 44).','. (210 - $month['in'] / $chartMax * 176))->implode(' ');
                    $departurePoints = $monthlyTrend->map(fn ($month, $index) => (48 + $index * 44).','. (210 - $month['out'] / $chartMax * 176))->implode(' ');
                @endphp
                <div class="migration-chart-wrap">
                    <div class="migration-chart-legend"><span class="value-in">● Arrivals</span><span class="value-out">■ Departures (dashed)</span></div>
                    <svg class="migration-monthly-chart" viewBox="0 0 560 250" role="img" aria-labelledby="migration-chart-title migration-chart-description">
                        <title id="migration-chart-title">Monthly migration in {{ $selectedYear }}</title>
                        <desc id="migration-chart-description">Monthly arrivals and departures from January to December. Exact counts are in the table below.</desc>
                        @for ($tick = 0; $tick <= 4; $tick++)
                            <line x1="48" y1="{{ 210 - $tick * 44 }}" x2="532" y2="{{ 210 - $tick * 44 }}" stroke="#dce4eb" />
                            <text x="38" y="{{ 214 - $tick * 44 }}" text-anchor="end">{{ $chartMax * $tick / 4 }}</text>
                        @endfor
                        <polyline points="{{ $arrivalPoints }}" fill="none" stroke="#15803d" stroke-width="3" />
                        <polyline points="{{ $departurePoints }}" fill="none" stroke="#b45309" stroke-width="3" stroke-dasharray="7 5" />
                        @foreach ($monthlyTrend as $month)
                            @php($monthLabel = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month['month'])->format('M'))
                            <text x="{{ 48 + $loop->index * 44 }}" y="234" text-anchor="middle">{{ $monthLabel }}</text>
                            <circle cx="{{ 48 + $loop->index * 44 }}" cy="{{ 210 - $month['in'] / $chartMax * 176 }}" r="4" fill="#15803d"><title>{{ $monthLabel }}: {{ $month['in'] }} arrivals</title></circle>
                            <rect x="{{ 45 + $loop->index * 44 }}" y="{{ 207 - $month['out'] / $chartMax * 176 }}" width="6" height="6" fill="#b45309"><title>{{ $monthLabel }}: {{ $month['out'] }} departures</title></rect>
                        @endforeach
                    </svg>
                </div>
                @if ($totalIn + $totalOut === 0)
                    <p class="migration-chart-notice">No migration events recorded for {{ $selectedYear }}.</p>
                @endif
                <details class="migration-monthly-details"><summary>View monthly breakdown</summary>
                    <div class="table-wrap clean-table-wrap w-full overflow-x-auto rounded-xl border border-slate-200"><table class="clean-table compact-data-table"><thead><tr><th>Month</th><th>Arrivals</th><th>Departures</th><th>Net</th></tr></thead><tbody>@foreach ($monthlyTrend as $month)<tr><td><strong>{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month['month'])->format('M Y') }}</strong></td><td><span class="value-in">{{ $month['in'] }}</span></td><td><span class="value-out">{{ $month['out'] }}</span></td>@php($monthNet = $month['in'] - $month['out'])<td><strong class="{{ $monthNet < 0 ? 'negative' : 'positive' }}">{{ $monthNet > 0 ? '+' : '' }}{{ $monthNet }}</strong></td></tr>@endforeach</tbody></table></div>
                </details>
            </section>
        </div>

        <section class="dashboard-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" aria-labelledby="recent-events-title">
            <header class="dashboard-card-header flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4"><div><span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Latest activity</span><h2 id="recent-events-title">Recent migration events</h2><p>The 12 most recent movement records in the selected scope.</p></div><span class="count-chip">{{ $records->count() }} shown</span></header>
            @if ($records->isEmpty())
                <div class="dashboard-empty-state rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-slate-600"><span class="empty-icon"><x-app-icon name="inbox" /></span><strong>No migration events</strong><span>New records will appear here.</span></div>
            @else
                <div class="table-wrap clean-table-wrap w-full overflow-x-auto rounded-xl border border-slate-200"><table class="clean-table"><thead><tr><th>Resident</th><th>Barangay</th><th>Movement</th><th>Date</th><th>Origin</th><th>Destination</th></tr></thead><tbody>@foreach ($records as $record)<tr><td><strong>{{ $record->inhabitant->fullName() }}</strong></td><td>Barangay {{ $record->barangay->name }}</td><td><span class="movement-type movement-type-{{ $record->type }}">{{ $record->typeLabel() }}</span></td><td>{{ $record->movement_date->format('M d, Y') }}</td><td>{{ $record->origin ?: 'Not provided' }}</td><td>{{ $record->destination ?: 'Not provided' }}</td></tr>@endforeach</tbody></table></div>
            @endif
        </section>
    </section>
@endsection
