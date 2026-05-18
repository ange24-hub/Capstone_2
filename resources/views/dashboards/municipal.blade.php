@extends('layouts.app')

@section('content')
    <section class="panel stack">
        <div class="dashboard-hero">
            <div>
                <div class="page-kicker">Municipal Command Center</div>
                <h1>Municipal LGU Dashboard</h1>
                <p>Monitor barangay submissions, review registry files, and move between population, migration, and spatial intelligence tools.</p>

                <div class="hero-actions">
                    <a class="button" href="{{ route('registry.index') }}">Open Registry</a>
                    <a class="button" href="{{ route('migration.dashboard') }}">Migration Monitor</a>
                    <a class="button" href="{{ route('spatial.index') }}">Spatial Map</a>
                </div>
            </div>
            <div class="hero-side">
                <div class="hero-mini-card">
                    <strong>{{ number_format($rbiUpdates->count()) }}</strong>
                    <span>Submitted RBI updates</span>
                </div>
                <div class="hero-mini-card">
                    <strong>{{ number_format($rbiUpdates->sum(fn ($update) => count($update->rows ?? []))) }}</strong>
                    <span>Registry rows under review</span>
                </div>
            </div>
        </div>

        <div class="stat-grid">
            <div class="stat-card">
                <span class="stat-label">Access Tier</span>
                <span class="stat-value">LGU</span>
                <span class="stat-note">Municipal-level workspace</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Submissions</span>
                <span class="stat-value">{{ number_format($rbiUpdates->count()) }}</span>
                <span class="stat-note">Barangay files received</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Entries</span>
                <span class="stat-value">{{ number_format($rbiUpdates->sum(fn ($update) => count($update->rows ?? []))) }}</span>
                <span class="stat-note">Rows available for review</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Session</span>
                <span class="stat-value">Live</span>
                <span class="stat-note">{{ auth()->user()->name }}</span>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="workflow-card">
                <h2 class="section-title">Submitted RBI Monthly Updates</h2>

                @if ($rbiUpdates->isEmpty())
                    <p>No barangay RBI updates submitted yet.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Barangay</th>
                                    <th>Reporting Month</th>
                                    <th>As Of</th>
                                    <th>Barangay Personnel</th>
                                    <th>Entries</th>
                                    <th>Submitted</th>
                                    <th>Files</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rbiUpdates as $update)
                                    <tr>
                                        <td><strong>{{ $update->barangay_name ?: 'Not set' }}</strong></td>
                                        <td>{{ optional($update->reporting_month)->format('M Y') ?: 'Not set' }}</td>
                                        <td>{{ optional($update->as_of_date)->format('M d, Y') ?: 'Not set' }}</td>
                                        <td>{{ $update->barangayUser->name }}</td>
                                        <td>{{ count($update->rows ?? []) }}</td>
                                        <td>{{ optional($update->submitted_at)->format('M d, Y h:i A') }}</td>
                                        <td class="row-actions">
                                            <a href="{{ route('rbi-updates.show', $update) }}">View edited</a>
                                            <a href="{{ route('rbi-updates.export-edited', $update) }}">Download edited</a>
                                            @if ($update->source_file_path)
                                                <a href="{{ route('rbi-updates.download', $update) }}">Original</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="workflow-card">
                <h2 class="section-title">Recent Activity</h2>
                @if ($rbiUpdates->isEmpty())
                    <p>No submissions to summarize.</p>
                @else
                    <div class="timeline-list">
                        @foreach ($rbiUpdates->take(5) as $update)
                            <div class="timeline-item">
                                <strong>{{ $update->barangay_name ?: 'Unnamed barangay' }}</strong>
                                <span>{{ count($update->rows ?? []) }} entries submitted {{ optional($update->submitted_at)->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
