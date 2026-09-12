@extends('layouts.app')

@section('content')
    @php
        $netMovement = $totalIn - $totalOut;
        $selectedBarangay = $barangays->firstWhere('id', (int) request('barangay_id'));
    @endphp

    <section class="dashboard-page migration-dashboard" aria-labelledby="migration-dashboard-title">
        <header class="dashboard-page-header dashboard-page-header-with-actions flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 bg-transparent bg-none pb-6 shadow-none">
            <div class="dashboard-title-group">
                <span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Population Movement</span>
                <h1 id="migration-dashboard-title">Migration trends</h1>
                <p>Compare monthly arrivals and departures for {{ $selectedYear }}.</p>
            </div>
                <form class="dashboard-filter" method="GET" action="{{ route('migration.dashboard') }}">
                    <label for="migration-year">Year</label>
                    <input id="migration-year" name="year" type="number" min="1900" max="9999" value="{{ $selectedYear }}" required>
                    @error('year')<p role="alert">{{ $message }}</p>@enderror
                    @if (! auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))
                    <label for="migration-barangay"><x-app-icon name="filter" /> View data for</label>
                    <div><select id="migration-barangay" name="barangay_id"><option value="">All barangays</option>@foreach ($barangays as $barangay)<option value="{{ $barangay->id }}" @selected((string) request('barangay_id') === (string) $barangay->id)>{{ $barangay->name }}</option>@endforeach</select><button type="submit">Apply filter</button></div>
                    @else
                        <p>Barangay {{ auth()->user()->barangay?->name }}</p>
                        <button type="submit">Apply filter</button>
                    @endif
                </form>
        </header>

        @if ($selectedBarangay)
            <div class="active-filter"><span>Showing Barangay {{ $selectedBarangay->name }} · {{ $selectedYear }}</span><a href="{{ route('migration.dashboard', ['year' => $selectedYear]) }}">Clear barangay filter</a></div>
        @endif

        <section class="dashboard-metrics grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Migration summary">
            <article class="metric-card metric-primary rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="users" /></span><div><span>Registry population</span><strong>{{ number_format($totalInhabitants) }}</strong><small>Current resident profiles in scope</small></div></article>
            <article class="metric-card metric-success rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon metric-arrow-in flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="arrow-right" /></span><div><span>Arrivals</span><strong>{{ number_format($totalIn) }}</strong><small>Recorded in-migration</small></div></article>
            <article class="metric-card metric-warning rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="arrow-right" /></span><div><span>Departures</span><strong>{{ number_format($totalOut) }}</strong><small>Recorded out-migration</small></div></article>
            <article class="metric-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md {{ $netMovement < 0 ? 'metric-danger' : 'metric-info' }}"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="activity" /></span><div><span>Net movement</span><strong>{{ $netMovement > 0 ? '+' : '' }}{{ number_format($netMovement) }}</strong><small>Arrivals minus departures</small></div></article>
        </section>

        <div class="analytics-grid">
            <section class="dashboard-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" aria-labelledby="barangay-movement-title">
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

            <section class="dashboard-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" aria-labelledby="municipal-trend-title">
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
                    <div class="table-wrap clean-table-wrap w-full overflow-x-auto rounded-xl border border-slate-200"><table class="clean-table compact-data-table"><thead><tr><th>Month</th><th>Arrivals</th><th>Departures</th><th>Net</th></tr></thead><tbody>@foreach ($monthlyTrend as $month)<tr><td><strong>{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month['month'])->format('M Y') }}</strong></td><td><span class="value-in">{{ $month['in'] }}</span></td><td><span class="value-out">{{ $month['out'] }}</span></td>@php($monthNet = $month['in'] - $month['out'])<td><strong class="{{ $monthNet < 0 ? 'negative' : 'positive' }}">{{ $monthNet > 0 ? '+' : '' }}{{ $monthNet }}</strong></td></tr>@endforeach</tbody></table></div>
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
