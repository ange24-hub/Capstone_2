@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/municipal-dashboard.css') }}?v={{ filemtime(public_path('css/municipal-dashboard.css')) }}">
@endpush

@section('content')
    @php
        $reportingBarangays = $barangays->filter(fn ($barangay) => $rbiUpdates->where('barangay_name', $barangay->name)->isNotEmpty())->count();
        $reportCoverage = $barangays->count() ? round($reportingBarangays / $barangays->count() * 100) : 0;
    @endphp
    <section class="dashboard-page municipal-dashboard" aria-labelledby="municipal-dashboard-title">
        <header class="municipal-hero">
            <div class="municipal-hero-copy">
                <span class="municipal-eyebrow">Municipal Administration <span aria-hidden="true">/</span> Tomas Oppus</span>
                <h1 id="municipal-dashboard-title">Municipal overview</h1>
                <p>A view of barangay reporting, account approvals, and population movement.</p>
            </div>
            <div class="municipal-hero-actions">
                <a href="{{ route('municipal.barangays.index') }}"><x-app-icon name="directory" />Barangay directory</a>
                <a href="{{ route('migration.dashboard') }}"><x-app-icon name="trend" />View migration details</a>
            </div>
        </header>

        @if (session('status'))
            <div class="success rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">{{ session('status') }}</div>
        @endif

        <div class="admin-section-heading">
            <div><span>Overview</span><h2>Key figures</h2></div>
            <p>Residents, reports, and areas that may need attention.</p>
        </div>
        <section class="dashboard-metrics municipal-metrics grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Municipal summary">
            <x-summary-card label="Registered residents" :value="number_format($barangays->sum('inhabitants_count'))" detail="Resident records across Tomas Oppus" icon="users" tone="primary" />
            <x-summary-card label="Registered households" :value="number_format($barangays->sum('households_count'))" detail="Households with recorded numbers" icon="home" tone="success" />
            <x-summary-card label="RBI reports received" :value="number_format($rbiUpdates->count())" detail="Submitted barangay reports" icon="form" tone="info" />
            <x-summary-card label="Pending approvals" :value="number_format($secretaryApprovalRequests->count())" detail="Secretary accounts to review" icon="shield" :tone="$secretaryApprovalRequests->isEmpty() ? 'neutral' : 'warning'" />
            <x-summary-card label="Six-month net migration" :value="($analyticsSummary['net_migration_6m'] >= 0 ? '+' : '').number_format($analyticsSummary['net_migration_6m'])" :detail="$analyticsSummary['migration_in_6m'].' in · '.$analyticsSummary['migration_out_6m'].' out'" icon="trend" />
            <x-summary-card label="Priority areas" :value="number_format($analyticsSummary['high_movement_count'])" detail="High-movement barangays" icon="map" :tone="$analyticsSummary['high_movement_count'] > 0 ? 'warning' : 'neutral'" />
        </section>

        <div class="admin-section-heading">
            <div><span>Navigate</span><h2>Quick access</h2></div>
        </div>
        <nav class="municipal-shortcuts" aria-label="Municipal quick access">
            <a href="{{ route('municipal.approvals.index') }}"><span><x-app-icon name="shield" /></span><div><strong>Account approvals</strong><small>{{ $secretaryApprovalRequests->count() }} awaiting review</small></div><x-app-icon name="arrow-right" /></a>
            <a href="{{ route('reports.population') }}"><span><x-app-icon name="users" /></span><div><strong>Population reports</strong><small>Review registered residents</small></div><x-app-icon name="arrow-right" /></a>
            <a href="{{ route('spatial.index') }}"><span><x-app-icon name="map" /></span><div><strong>Household map</strong><small>View locations and hazards</small></div><x-app-icon name="arrow-right" /></a>
        </nav>

        <div class="admin-section-heading">
            <div><span>Review</span><h2>Daily operations</h2></div>
        </div>
        <div class="municipal-work-grid">

        <div class="workflow-card municipal-panel rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" id="secretary-approvals">
            <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <span class="step-pill">Account Authorization</span>
                    <h2 class="section-title text-lg font-semibold text-slate-900">Secretary approvals</h2>
                    <p>Approve only verified secretaries assigned to the correct Tomas Oppus barangay.</p>
                </div>
                <x-status-badge status="pending">{{ $secretaryApprovalRequests->count() }} pending</x-status-badge>
            </div>

            @if ($secretaryApprovalRequests->isEmpty())
                <div class="dashboard-empty-state compact rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-slate-600">
                    <span class="municipal-empty-icon"><x-app-icon name="check" /></span>
                    <strong>No secretary accounts awaiting approval</strong>
                    <span>New secretary registrations will appear here automatically.</span>
                </div>
            @else
                <div class="municipal-approval-list grid gap-4 ">
                    @foreach ($secretaryApprovalRequests as $secretary)
                        <article class="municipal-approval-item rounded-xl border border-slate-200 bg-white bg-none shadow-sm p-5">
                            <div class="approval-identity">
                                <span class="account-avatar">{{ str($secretary->name)->substr(0, 1)->upper() }}</span>
                                <div>
                                    <strong>{{ $secretary->name }}</strong>
                                    <span>{{ $secretary->email }}</span>
                                </div>
                            </div>
                            <div class="approval-details">
                                <div><span>User ID</span><strong>{{ $secretary->staff_id ?: 'Not provided' }}</strong></div>
                                <div><span>Barangay</span><strong>{{ $secretary->barangay?->name ?? 'Not assigned' }}</strong></div>
                                <div><span>Registered</span><strong>{{ $secretary->created_at->format('M d, Y') }}</strong></div>
                            </div>
                            <div class="approval-actions flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('municipal.secretaries.approve', $secretary) }}">
                                    @csrf
                                    <button type="submit">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('municipal.secretaries.reject', $secretary) }}" onsubmit="return confirm('Reject this secretary registration?')">
                                    @csrf
                                    <button type="submit" class="danger-button">Reject</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="workflow-card municipal-panel municipal-directory-summary rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" id="incoming-rbi-reports">
            <div>
                <span class="step-pill">Barangay Reporting</span>
                <h2 class="section-title text-lg font-semibold text-slate-900">Barangay reporting</h2>
                <p>Review submitted forms and find the latest reports in the barangay directory.</p>
            </div>
            <div class="directory-summary-metrics">
                <div><strong>{{ $barangays->count() }}</strong><span>Total barangays</span></div>
                <div><strong>{{ $reportingBarangays }}</strong><span>With reports</span></div>
                <div><strong>{{ $rbiUpdates->count() }}</strong><span>Reports received</span></div>
            </div>
            <div class="municipal-report-coverage">
                <div><span>Barangays with submitted reports</span><strong>{{ $reportingBarangays }} / {{ $barangays->count() }}</strong></div>
                <progress max="100" value="{{ $reportCoverage }}" aria-label="Barangays with submitted reports">{{ $reportCoverage }}%</progress>
                <small>Across all recorded reporting months</small>
            </div>
            @if ($latestMunicipalReport = $rbiUpdates->first())
                <div class="municipal-latest-received" aria-label="Latest RBI report received">
                    <span>Latest received</span>
                    <strong>{{ $latestMunicipalReport->barangay_name ?: 'Barangay not set' }} · {{ optional($latestMunicipalReport->reporting_month)->format('F Y') ?: 'Month not set' }}</strong>
                    <small>{{ $latestMunicipalReport->rbiFamilies->count() }} family/families · {{ count($latestMunicipalReport->rows ?? []) }} inhabitant entries</small>
                    <a href="{{ route('rbi-updates.show', $latestMunicipalReport) }}">View latest report <x-app-icon name="arrow-right" /></a>
                </div>
            @endif
            <a class="button" href="{{ route('municipal.barangays.index') }}">Open Barangay Directory</a>
        </div>
        </div>

        <div class="workflow-card municipal-panel decision-support rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" id="decision-support">
            <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <span class="step-pill">AI-assisted Decision Support</span>
                    <h2 class="section-title text-lg font-semibold text-slate-900">Planning indicators</h2>
                    <p>Explainable indicators use recent RBI and migration patterns. They support planning decisions but do not replace field validation.</p>
                </div>
                <span class="badge inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $analyticsSummary['high_movement_count'] }} priority area(s)</span>
            </div>

            <div class="decision-grid">
                @forelse ($barangays->whereIn('movement_level', ['high', 'watch'])->sortByDesc('movement_total_6m') as $barangay)
                    <article class="decision-card decision-{{ $barangay->movement_level }}">
                        <div>
                            <span class="movement-indicator movement-{{ $barangay->movement_level }}">{{ ucfirst($barangay->movement_level) }}</span>
                            <h3>Barangay {{ $barangay->name }}</h3>
                        </div>
                        <p>{{ $barangay->planning_signal }}</p>
                        <dl>
                            <div><dt>Movement</dt><dd>{{ $barangay->movement_total_6m }}</dd></div>
                            <div><dt>Net migration</dt><dd>{{ $barangay->net_migration_6m >= 0 ? '+' : '' }}{{ $barangay->net_migration_6m }}</dd></div>
                            <div><dt>Recent RBI entry balance (not a forecast)</dt><dd>{{ $barangay->predicted_monthly_change >= 0 ? '+' : '' }}{{ $barangay->predicted_monthly_change }}</dd></div>
                        </dl>
                    </article>
                @empty
                    <div class="dashboard-empty-state rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-slate-600">
                        <strong>No priority movement signal detected</strong>
                        <span>Continue collecting monthly RBI and migration records to strengthen the indicators.</span>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
