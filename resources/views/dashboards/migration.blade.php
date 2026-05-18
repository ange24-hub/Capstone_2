@extends('layouts.app')

@section('content')
    <section class="panel stack">
        <div class="dashboard-hero">
            <div>
                <div class="page-kicker">Migration Intelligence</div>
                <h1>Migration Dashboard</h1>
                <p>Track movement across barangays, detect high-migration areas, and compare in-migration, out-migration, and net population change.</p>
            </div>
            <div class="hero-side">
                <div class="hero-mini-card">
                    <strong>{{ number_format($totalIn - $totalOut) }}</strong>
                    <span>Net movement</span>
                </div>
                <div class="hero-mini-card">
                    <strong>{{ number_format($totalIn + $totalOut) }}</strong>
                    <span>Total migration events</span>
                </div>
            </div>
        </div>

        <div class="page-head">
            <form class="toolbar compact-toolbar" method="GET" action="{{ route('migration.dashboard') }}">
                <select name="barangay_id" aria-label="Filter by barangay">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $barangay)
                        <option value="{{ $barangay->id }}" @selected((string) request('barangay_id') === (string) $barangay->id)>{{ $barangay->name }}</option>
                    @endforeach
                </select>
                <button class="secondary-button" type="submit">Filter</button>
            </form>
        </div>

        <div class="stat-grid">
            <div class="stat-card">
                <span class="stat-label">Registry Population</span>
                <span class="stat-value">{{ number_format($totalInhabitants) }}</span>
                <span class="stat-note">Personal profiles tracked</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">In-Migration</span>
                <span class="stat-value">{{ number_format($totalIn) }}</span>
                <span class="stat-note">Arrivals recorded</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Out-Migration</span>
                <span class="stat-value">{{ number_format($totalOut) }}</span>
                <span class="stat-note">Departures recorded</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Net Movement</span>
                <span class="stat-value">{{ number_format($totalIn - $totalOut) }}</span>
                <span class="stat-note">In minus out</span>
            </div>
        </div>

        <div class="workflow-card">
            <h2 class="section-title">Barangay-Level Movement</h2>

            @if ($barangayStats->isEmpty())
                <p>No migration records available.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Barangay</th>
                                <th>In</th>
                                <th>Out</th>
                                <th>Net</th>
                                <th>Total Events</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($barangayStats as $stat)
                                <tr>
                                    <td>{{ $stat['barangay']->name }}</td>
                                    <td>{{ $stat['in'] }}</td>
                                    <td>{{ $stat['out'] }}</td>
                                    <td>{{ $stat['net'] }}</td>
                                    <td>{{ $stat['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="workflow-card">
            <h2 class="section-title">Municipal Trend</h2>

            @if ($monthlyTrend->isEmpty())
                <p>No monthly trend data available.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>In</th>
                                <th>Out</th>
                                <th>Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($monthlyTrend as $month)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month['month'])->format('M Y') }}</td>
                                    <td>{{ $month['in'] }}</td>
                                    <td>{{ $month['out'] }}</td>
                                    <td>{{ $month['in'] - $month['out'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="workflow-card">
            <h2 class="section-title">Recent Migration Events</h2>

            @if ($records->isEmpty())
                <p>No migration events recorded.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Person</th>
                                <th>Barangay</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Origin</th>
                                <th>Destination</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $record)
                                <tr>
                                    <td>{{ $record->inhabitant->fullName() }}</td>
                                    <td>{{ $record->barangay->name }}</td>
                                    <td><span class="badge">{{ $record->typeLabel() }}</span></td>
                                    <td>{{ $record->movement_date->format('M d, Y') }}</td>
                                    <td>{{ $record->origin ?: 'Not set' }}</td>
                                    <td>{{ $record->destination ?: 'Not set' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
