@extends('layouts.app')

@section('content')
    @php
        $netMovement = $totalIn - $totalOut;
        $selectedBarangay = $barangays->firstWhere('id', (int) request('barangay_id'));
    @endphp

    <section class="dashboard-page migration-dashboard" aria-labelledby="migration-dashboard-title">
        <header class="dashboard-page-header dashboard-page-header-with-actions">
            <div class="dashboard-title-group">
                <span class="dashboard-eyebrow">Population Movement</span>
                <h1 id="migration-dashboard-title">Migration trends</h1>
                <p>See where people are moving, compare arrivals and departures, and review recent events.</p>
            </div>
            @if (! auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))
                <form class="dashboard-filter" method="GET" action="{{ route('migration.dashboard') }}">
                    <label for="migration-barangay"><x-app-icon name="filter" /> View data for</label>
                    <div><select id="migration-barangay" name="barangay_id"><option value="">All barangays</option>@foreach ($barangays as $barangay)<option value="{{ $barangay->id }}" @selected((string) request('barangay_id') === (string) $barangay->id)>{{ $barangay->name }}</option>@endforeach</select><button type="submit">Apply filter</button></div>
                </form>
            @else
                <div class="dashboard-context-card"><span class="context-icon"><x-app-icon name="location" /></span><div><small>Current scope</small><strong>Barangay {{ auth()->user()->barangay?->name }}</strong><span>Barangay records only</span></div></div>
            @endif
        </header>

        @if ($selectedBarangay)
            <div class="active-filter"><span>Showing Barangay {{ $selectedBarangay->name }}</span><a href="{{ route('migration.dashboard') }}">Clear filter</a></div>
        @endif

        <section class="dashboard-metrics" aria-label="Migration summary">
            <article class="metric-card metric-primary"><span class="metric-icon"><x-app-icon name="users" /></span><div><span>Registry population</span><strong>{{ number_format($totalInhabitants) }}</strong><small>Resident profiles in scope</small></div></article>
            <article class="metric-card metric-success"><span class="metric-icon metric-arrow-in"><x-app-icon name="arrow-right" /></span><div><span>Arrivals</span><strong>{{ number_format($totalIn) }}</strong><small>Recorded in-migration</small></div></article>
            <article class="metric-card metric-warning"><span class="metric-icon"><x-app-icon name="arrow-right" /></span><div><span>Departures</span><strong>{{ number_format($totalOut) }}</strong><small>Recorded out-migration</small></div></article>
            <article class="metric-card {{ $netMovement < 0 ? 'metric-danger' : 'metric-info' }}"><span class="metric-icon"><x-app-icon name="activity" /></span><div><span>Net movement</span><strong>{{ $netMovement > 0 ? '+' : '' }}{{ number_format($netMovement) }}</strong><small>Arrivals minus departures</small></div></article>
        </section>

        <div class="analytics-grid">
            <section class="dashboard-card" aria-labelledby="barangay-movement-title">
                <header class="dashboard-card-header"><div><span class="dashboard-eyebrow">Area comparison</span><h2 id="barangay-movement-title">Movement by barangay</h2><p>Areas with the most movement appear first.</p></div><span class="count-chip">{{ $barangayStats->count() }} areas</span></header>
                @if ($barangayStats->isEmpty())
                    <div class="dashboard-empty-state"><span class="empty-icon"><x-app-icon name="map" /></span><strong>No movement data</strong><span>Recorded migration events will appear here.</span></div>
                @else
                    <div class="movement-list">
                        @php($maxEvents = max(1, (int) $barangayStats->max('total')))
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

            <section class="dashboard-card" aria-labelledby="municipal-trend-title">
                <header class="dashboard-card-header"><div><span class="dashboard-eyebrow">Over time</span><h2 id="municipal-trend-title">Monthly trend</h2><p>Arrivals and departures grouped by month.</p></div><span class="section-icon"><x-app-icon name="calendar" /></span></header>
                @if ($monthlyTrend->isEmpty())
                    <div class="dashboard-empty-state"><span class="empty-icon"><x-app-icon name="trend" /></span><strong>No monthly trend yet</strong><span>More events are needed to build a trend.</span></div>
                @else
                    <div class="table-wrap clean-table-wrap"><table class="clean-table compact-data-table"><thead><tr><th>Month</th><th>Arrivals</th><th>Departures</th><th>Net</th></tr></thead><tbody>@foreach ($monthlyTrend as $month)<tr><td><strong>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month['month'])->format('M Y') }}</strong></td><td><span class="value-in">{{ $month['in'] }}</span></td><td><span class="value-out">{{ $month['out'] }}</span></td>@php($monthNet = $month['in'] - $month['out'])<td><strong class="{{ $monthNet < 0 ? 'negative' : 'positive' }}">{{ $monthNet > 0 ? '+' : '' }}{{ $monthNet }}</strong></td></tr>@endforeach</tbody></table></div>
                @endif
            </section>
        </div>

        <section class="dashboard-card" aria-labelledby="recent-events-title">
            <header class="dashboard-card-header"><div><span class="dashboard-eyebrow">Latest activity</span><h2 id="recent-events-title">Recent migration events</h2><p>The 12 most recent movement records in the selected scope.</p></div><span class="count-chip">{{ $records->count() }} shown</span></header>
            @if ($records->isEmpty())
                <div class="dashboard-empty-state"><span class="empty-icon"><x-app-icon name="inbox" /></span><strong>No migration events</strong><span>New records will appear here.</span></div>
            @else
                <div class="table-wrap clean-table-wrap"><table class="clean-table"><thead><tr><th>Resident</th><th>Barangay</th><th>Movement</th><th>Date</th><th>Origin</th><th>Destination</th></tr></thead><tbody>@foreach ($records as $record)<tr><td><strong>{{ $record->inhabitant->fullName() }}</strong></td><td>Barangay {{ $record->barangay->name }}</td><td><span class="movement-type movement-type-{{ $record->type }}">{{ $record->typeLabel() }}</span></td><td>{{ $record->movement_date->format('M d, Y') }}</td><td>{{ $record->origin ?: 'Not provided' }}</td><td>{{ $record->destination ?: 'Not provided' }}</td></tr>@endforeach</tbody></table></div>
            @endif
        </section>
    </section>
@endsection
