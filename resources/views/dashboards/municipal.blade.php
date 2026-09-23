@extends('layouts.app')

@section('content')
    <section class="dashboard-page municipal-dashboard" aria-labelledby="municipal-dashboard-title">
        <header class="dashboard-page-header dashboard-page-header-with-actions flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 bg-transparent bg-none pb-6 shadow-none">
            <div class="dashboard-title-group">
                <span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Municipal Administration</span>
                <h1 id="municipal-dashboard-title">Municipal overview</h1>
                <p>Review urgent account approvals, received RBI reports, and population movement across Tomas Oppus.</p>
            </div>
            <div class="dashboard-header-actions">
                <a class="button" href="{{ route('municipal.barangays.index') }}"><x-app-icon name="directory" /> Barangay directory</a>
                <a class="button secondary-button" href="{{ route('migration.dashboard') }}"><x-app-icon name="trend" /> Migration trends</a>
            </div>
            <x-dashboard-context />
        </header>

        @if (session('status'))
            <div class="success rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">{{ session('status') }}</div>
        @endif

        <section class="dashboard-metrics grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Municipal summary">
            <article class="metric-card metric-primary rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="form" /></span><div><span>RBI reports received</span><strong>{{ number_format($rbiUpdates->count()) }}</strong><small>Submitted barangay reports</small></div></article>
            <article class="metric-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md {{ $secretaryApprovalRequests->isEmpty() ? 'metric-success' : 'metric-warning' }}"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="users" /></span><div><span>Pending approvals</span><strong>{{ number_format($secretaryApprovalRequests->count()) }}</strong><small>Secretary accounts to review</small></div></article>
            <article class="metric-card metric-info rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="activity" /></span><div><span>Six-month net migration</span><strong>{{ $analyticsSummary['net_migration_6m'] >= 0 ? '+' : '' }}{{ number_format($analyticsSummary['net_migration_6m']) }}</strong><small>{{ $analyticsSummary['migration_in_6m'] }} in · {{ $analyticsSummary['migration_out_6m'] }} out</small></div></article>
            <article class="metric-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md {{ $analyticsSummary['high_movement_count'] > 0 ? 'metric-danger' : 'metric-success' }}"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="map" /></span><div><span>Priority areas</span><strong>{{ number_format($analyticsSummary['high_movement_count']) }}</strong><small>High-movement barangays</small></div></article>
        </section>

        <x-dashboard-shortcuts />

        <div class="workflow-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" id="secretary-approvals">
            <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <span class="step-pill">Account Authorization</span>
                    <h2 class="section-title text-lg font-semibold text-slate-900">Pending Barangay Secretary Accounts</h2>
                    <p>Approve only verified secretaries assigned to the correct Tomas Oppus barangay.</p>
                </div>
                <x-status-badge status="pending">{{ $secretaryApprovalRequests->count() }} pending</x-status-badge>
            </div>

            @if ($secretaryApprovalRequests->isEmpty())
                <div class="dashboard-empty-state compact rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-slate-600">
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

        <div class="workflow-card municipal-directory-summary rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" id="incoming-rbi-reports">
            <div>
                <span class="step-pill">Direct Municipal Receiving</span>
                <h2 class="section-title text-lg font-semibold text-slate-900">Monthly RBI Reports by Barangay</h2>
                <p>The complete barangay list and all received RBI reports now have a separate, searchable directory so this dashboard stays concise.</p>
            </div>
            <div class="directory-summary-metrics">
                <div><strong>{{ $barangays->count() }}</strong><span>Total barangays</span></div>
                <div><strong>{{ $barangays->filter(fn ($barangay) => $rbiUpdates->where('barangay_name', $barangay->name)->isNotEmpty())->count() }}</strong><span>With reports</span></div>
                <div><strong>{{ $rbiUpdates->count() }}</strong><span>Reports received</span></div>
            </div>
            @if ($latestMunicipalReport = $rbiUpdates->first())
                <div class="municipal-latest-received" aria-label="Latest RBI report received">
                    <span>Latest received</span>
                    <strong>{{ $latestMunicipalReport->barangay_name ?: 'Barangay not set' }} · {{ optional($latestMunicipalReport->reporting_month)->format('F Y') ?: 'Month not set' }}</strong>
                    <small>{{ $latestMunicipalReport->rbiFamilies->count() }} family/families · {{ count($latestMunicipalReport->rows ?? []) }} inhabitant entries</small>
                </div>
            @endif
            <a class="button" href="{{ route('municipal.barangays.index') }}">Open Barangay Directory</a>
        </div>

        <div class="workflow-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" id="trend-monitoring">
            <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <span class="step-pill">Trend Monitoring</span>
                    <h2 class="section-title text-lg font-semibold text-slate-900">Population and Migration Comparison</h2>
                    <p>Compare the latest RBI population change, six-month in/out-migration, and movement against the previous six months.</p>
                </div>
                <a class="secondary-button" href="{{ route('migration.dashboard') }}">Open Detailed Migration Monitor</a>
            </div>

            <div class="table-wrap w-full overflow-x-auto rounded-xl border border-slate-200">
                <table class="trend-table">
                    <thead>
                        <tr>
                            <th>Barangay</th>
                            <th>Current Population</th>
                            <th>Latest RBI Net</th>
                            <th>In / Out (6 months)</th>
                            <th>Net Migration</th>
                            <th>Movement Trend</th>
                            <th>Recent RBI entry balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($barangays->sortByDesc('movement_total_6m')->take(10) as $barangay)
                            <tr>
                                <td><strong>{{ $barangay->name }}</strong></td>
                                <td>{{ number_format($barangay->inhabitants_count) }}</td>
                                <td class="signed-value">{{ $barangay->latest_rbi_net_change >= 0 ? '+' : '' }}{{ $barangay->latest_rbi_net_change }}</td>
                                <td>{{ $barangay->migration_in_6m }} in / {{ $barangay->migration_out_6m }} out</td>
                                <td class="signed-value">{{ $barangay->net_migration_6m >= 0 ? '+' : '' }}{{ $barangay->net_migration_6m }}</td>
                                <td>
                                    <span class="movement-indicator movement-{{ $barangay->movement_level }}">{{ ucfirst($barangay->movement_level) }}</span>
                                    <small>{{ $barangay->movement_change_percent >= 0 ? '+' : '' }}{{ $barangay->movement_change_percent }}% vs previous period</small>
                                </td>
                                <td class="signed-value" title="Rounded mean of inhabitant entries minus deceased entries over up to three latest recorded reporting months. Not a population forecast.">{{ $barangay->predicted_monthly_change >= 0 ? '+' : '' }}{{ $barangay->predicted_monthly_change }} recorded average</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="workflow-card decision-support rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" id="decision-support">
            <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <span class="step-pill">AI-assisted Decision Support</span>
                    <h2 class="section-title text-lg font-semibold text-slate-900">Planning and Resource Allocation Indicators</h2>
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
