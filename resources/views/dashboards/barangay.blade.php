@extends('layouts.app')

@section('content')
    @php
        $pendingDocuments = $barangayDocumentRequests->where('status', App\Models\DocumentRequest::STATUS_PENDING)->count();
        $submittedRbi = $rbiUpdates->where('status', App\Models\BarangayRbiUpdate::STATUS_SUBMITTED)->count();
        $draftRbi = $rbiUpdates->where('status', App\Models\BarangayRbiUpdate::STATUS_DRAFT)->count();
    @endphp

    <section class="barangay-dashboard" aria-labelledby="barangay-dashboard-title">
        <header class="dashboard-page-header dashboard-page-header-with-actions">
            <div class="dashboard-title-group">
                <span class="dashboard-eyebrow">Barangay Administration</span>
                <h1 id="barangay-dashboard-title">Barangay {{ $barangay?->name ?? 'Dashboard' }}</h1>
                <p>Process resident services, maintain community records, and prepare monthly RBI reports.</p>
            </div>
            @if ($barangay)
                <div class="dashboard-header-actions">
                    <a class="button" href="{{ route('barangay.rbi-updates.index') }}"><x-app-icon name="form" /> New RBI report</a>
                    <a class="button secondary-button" href="{{ route('registry.index') }}"><x-app-icon name="users" /> Resident registry</a>
                </div>
            @endif
        </header>

        @if (session('status'))<div class="success" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="errors" role="alert"><strong>Please review the following:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        @if (! $barangay)
            <div class="errors" role="alert">This account is not assigned to a barangay. Ask the municipal administrator to complete the account assignment.</div>
        @else
            <div class="barangay-welcome-panel barangay-focus-panel">
                <div class="barangay-welcome-copy">
                    <span class="government-eyebrow">Today’s focus</span>
                    <h2>Good day, {{ str(auth()->user()->name)->before(' ') }}.</h2>
                    <p>You have {{ $residentApprovalRequests->count() }} resident {{ \Illuminate\Support\Str::plural('registration', $residentApprovalRequests->count()) }} and {{ $pendingDocuments }} document {{ \Illuminate\Support\Str::plural('request', $pendingDocuments) }} awaiting review.</p>
                    <div class="barangay-primary-actions">
                        <a class="button government-primary-button" href="#resident-approvals-title">
                            <span class="action-icon"><x-app-icon name="users" /></span><span><strong>Review resident registrations</strong><small>{{ $residentApprovalRequests->count() }} awaiting verification</small></span>
                        </a>
                        <a class="button government-outline-button" href="#document-requests-title">
                            <span class="action-icon"><x-app-icon name="document" /></span><span><strong>Process document requests</strong><small>{{ $pendingDocuments }} awaiting action</small></span>
                        </a>
                    </div>
                </div>
                <div class="barangay-office-card" aria-label="Barangay office information">
                    <span class="office-card-label">Assigned Office</span>
                    <strong>Barangay {{ $barangay->name }}</strong>
                    <small>{{ $barangay->municipality ?: 'Municipality of Tomas Oppus' }}</small>
                    <dl>
                        <div><dt>Secretary</dt><dd>{{ $barangay->secretary_name ?: auth()->user()->name }}</dd></div>
                        <div><dt>Punong Barangay</dt><dd>{{ $barangay->punong_barangay_name ?: 'Not yet configured' }}</dd></div>
                    </dl>
                </div>
            </div>

            <section aria-labelledby="community-summary-title">
                <div class="dashboard-section-heading compact-heading">
                    <div><span class="government-eyebrow">Community Overview</span><h2 id="community-summary-title">Current Barangay Records</h2></div>
                    <span class="data-freshness">Database totals as of today</span>
                </div>
                <div class="dashboard-metrics">
                    <article class="metric-card {{ $residentApprovalRequests->isEmpty() ? 'metric-success' : 'metric-warning' }}"><span class="metric-icon"><x-app-icon name="users" /></span><div><span>Resident approvals</span><strong>{{ number_format($residentApprovalRequests->count()) }}</strong><small>Registrations requiring review</small></div></article>
                    <article class="metric-card metric-primary"><span class="metric-icon"><x-app-icon name="users" /></span><div><span>Registered inhabitants</span><strong>{{ number_format($barangay->inhabitants_count) }}</strong><small>Individual registry records</small></div></article>
                    <article class="metric-card metric-success"><span class="metric-icon"><x-app-icon name="home" /></span><div><span>Households</span><strong>{{ number_format($barangay->households_count) }}</strong><small>Household profiles on record</small></div></article>
                    <article class="metric-card metric-info"><span class="metric-icon"><x-app-icon name="trend" /></span><div><span>Migration events</span><strong>{{ number_format($barangay->migration_records_count) }}</strong><small>Recorded arrivals and departures</small></div></article>
                </div>
            </section>

            <div class="barangay-dashboard-columns">
                <main class="barangay-dashboard-main">
                    <section class="government-content-card" aria-labelledby="resident-approvals-title">
                        <header class="government-card-header">
                            <div><span class="government-eyebrow">Resident Verification</span><h2 id="resident-approvals-title">Pending Resident Registrations</h2><p>Verify residency before granting access to barangay online services.</p></div>
                            <span class="government-count-badge {{ $residentApprovalRequests->isEmpty() ? 'is-clear' : 'is-pending' }}">{{ $residentApprovalRequests->count() }} pending</span>
                        </header>
                        @if ($residentApprovalRequests->isEmpty())
                            <div class="government-empty-state"><span class="empty-state-mark">✓</span><div><strong>Verification queue is clear</strong><span>New resident registrations will appear here automatically.</span></div></div>
                        @else
                            <div class="government-record-list">
                                @foreach ($residentApprovalRequests as $resident)
                                    <article class="government-record-row">
                                        <span class="record-avatar">{{ str($resident->name)->substr(0, 1)->upper() }}</span>
                                        <div class="record-identity"><strong>{{ $resident->name }}</strong><span>{{ $resident->email }}</span><small>Registered {{ $resident->created_at->format('M d, Y · h:i A') }}</small></div>
                                        <div class="approval-actions record-actions">
                                            <form method="POST" action="{{ route('barangay.residents.approve', $resident) }}">@csrf<button type="submit">Approve Resident</button></form>
                                            <form method="POST" action="{{ route('barangay.residents.reject', $resident) }}" onsubmit="return confirm('Reject this resident registration?')">@csrf<button type="submit" class="danger-button">Reject</button></form>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section class="government-content-card" aria-labelledby="document-requests-title">
                        <header class="government-card-header">
                            <div><span class="government-eyebrow">Barangay E-Services</span><h2 id="document-requests-title">Resident Document Requests</h2><p>Process requests submitted by approved Barangay {{ $barangay->name }} residents.</p></div>
                            <span class="government-count-badge {{ $pendingDocuments === 0 ? 'is-clear' : 'is-pending' }}">{{ $pendingDocuments }} pending</span>
                        </header>
                        @if ($barangayDocumentRequests->isEmpty())
                            <div class="government-empty-state"><span class="empty-state-mark">✓</span><div><strong>No document requests received</strong><span>Resident requests assigned to this barangay will appear here.</span></div></div>
                        @else
                            <div class="table-wrap government-table-wrap">
                                <table class="government-data-table">
                                    <thead><tr><th>Reference</th><th>Resident</th><th>Document and Purpose</th><th>GCash Payment</th><th>Status</th><th>Process Request</th></tr></thead>
                                    <tbody>@foreach ($barangayDocumentRequests as $documentRequest)
                                        <tr>
                                            <td><strong>{{ $documentRequest->reference_number }}</strong><small>{{ $documentRequest->created_at->format('M d, Y') }}</small></td>
                                            <td><strong>{{ $documentRequest->user->name }}</strong><small>{{ $documentRequest->user->email }}</small></td>
                                            <td><strong>{{ $documentRequest->typeLabel() }}</strong><small>{{ $documentRequest->purpose }}</small></td>
                                            <td class="document-payment-cell">
                                                @if ($documentRequest->requiresPayment())
                                                    <strong>₱{{ number_format((float) $documentRequest->amount_due, 2) }}</strong>
                                                    <span class="payment-status payment-status-{{ $documentRequest->payment_status }}">{{ $documentRequest->paymentStatusLabel() }}</span>
                                                    @if ($documentRequest->payment_reference)
                                                        <small>Ref: {{ $documentRequest->payment_reference }}<br>Paid: {{ optional($documentRequest->payment_transaction_at)->format('M d, Y h:i A') }}<br>{{ $documentRequest->payer_name }} · {{ $documentRequest->payer_mobile }}</small>
                                                    @endif
                                                    @if ($documentRequest->payment_proof_path)
                                                        <a href="{{ route('document-payments.proof', $documentRequest) }}">Open receipt proof</a>
                                                    @endif
                                                    @if ($documentRequest->payment_status === App\Models\DocumentRequest::PAYMENT_PENDING)
                                                        <small>Match the reference, amount, and time in the official GCash for Business portal before verifying.</small>
                                                        <form class="payment-verification-form" method="POST" action="{{ route('barangay.document-payments.verify', $documentRequest) }}">
                                                            @csrf
                                                            <input name="payment_remarks" maxlength="1000" placeholder="Required reason when rejecting">
                                                            <div>
                                                                <button type="submit" name="decision" value="verify">Verify Payment</button>
                                                                <button class="danger-button" type="submit" name="decision" value="reject">Reject</button>
                                                            </div>
                                                        </form>
                                                    @elseif ($documentRequest->payment_remarks)
                                                        <small>Review note: {{ $documentRequest->payment_remarks }}</small>
                                                    @endif
                                                @else
                                                    <span class="payment-status payment-status-not_required">No payment required</span>
                                                @endif
                                            </td>
                                            <td><span class="request-status request-status-{{ $documentRequest->status }}">{{ $documentRequest->statusLabel() }}</span></td>
                                            <td><form class="request-status-form" method="POST" action="{{ route('barangay.document-requests.update', $documentRequest) }}">@csrf @method('PUT')
                                                <select name="status" aria-label="Status for {{ $documentRequest->reference_number }}" required>@foreach ($documentRequestStatuses as $value => $label)<option value="{{ $value }}" @selected($documentRequest->status === $value)>{{ $label }}</option>@endforeach</select>
                                                <input name="remarks" type="text" value="{{ $documentRequest->remarks }}" placeholder="Add remarks" aria-label="Remarks for {{ $documentRequest->reference_number }}"><button type="submit">Save Update</button>
                                            </form></td>
                                        </tr>
                                    @endforeach</tbody>
                                </table>
                            </div>
                        @endif
                    </section>
                </main>

                <aside class="barangay-dashboard-aside" aria-label="Quick links and report status">
                    <section class="government-side-card">
                        <header><span class="government-eyebrow">Quick Access</span><h2>Barangay Tools</h2></header>
                        <nav class="government-quick-links" aria-label="Barangay tools">
                            <a href="{{ route('registry.index') }}"><span class="quick-link-icon">CR</span><span><strong>Central Registry</strong><small>Inhabitants and households</small></span><b>→</b></a>
                            <a href="{{ route('barangay.rbi-updates.index') }}"><span class="quick-link-icon">RBI</span><span><strong>Monthly RBI Forms</strong><small>Create and download reports</small></span><b>→</b></a>
                            <a href="{{ route('migration.dashboard') }}"><span class="quick-link-icon">MM</span><span><strong>Migration Monitor</strong><small>Population movement records</small></span><b>→</b></a>
                            <a href="{{ route('spatial.index') }}"><span class="quick-link-icon">SM</span><span><strong>Spatial Map</strong><small>Household location overview</small></span><b>→</b></a>
                        </nav>
                    </section>

                    <section class="government-side-card rbi-status-card">
                        <header><span class="government-eyebrow">Reporting Status</span><h2>Monthly RBI Forms</h2></header>
                        <div class="rbi-status-summary"><div><strong>{{ $submittedRbi }}</strong><span>Submitted</span></div><div><strong>{{ $draftRbi }}</strong><span>Drafts</span></div></div>
                        @if ($rbiUpdates->isEmpty())
                            <p>No RBI monthly report has been created.</p>
                        @else
                            @php($latestRbi = $rbiUpdates->first())
                            <div class="latest-report"><span>Latest report</span><strong>{{ optional($latestRbi->reporting_month)->format('F Y') ?: 'Month not set' }}</strong><small>{{ $latestRbi->statusLabel() }} · {{ count($latestRbi->rows ?? []) }} inhabitant entries</small></div>
                        @endif
                        <a class="button government-outline-button primary-block" href="{{ route('barangay.rbi-updates.index') }}">Manage RBI Reports</a>
                    </section>

                    <section class="government-side-card security-card"><span class="security-mark">DPA</span><div><strong>Data Privacy Reminder</strong><p>Access resident information only for authorized barangay functions. Keep account credentials confidential.</p></div></section>
                </aside>
            </div>

            <section class="government-content-card" aria-labelledby="rbi-history-title">
                <header class="government-card-header">
                    <div><span class="government-eyebrow">Secretary Copies</span><h2 id="rbi-history-title">Monthly RBI Form History</h2><p>Review, update, and download the official copies retained by this barangay.</p></div>
                    <a class="button government-outline-button" href="{{ route('barangay.rbi-updates.index') }}">Open RBI Forms</a>
                </header>
                @if ($rbiUpdates->isEmpty())
                    <div class="government-empty-state"><span class="empty-state-mark">—</span><div><strong>No monthly RBI forms created yet</strong><span>Create the first monthly report through RBI Forms.</span></div></div>
                @else
                    <div class="table-wrap government-table-wrap"><table class="government-data-table">
                        <thead><tr><th>Reporting Month</th><th>Families</th><th>Entries</th><th>Status</th><th>Submitted</th><th>Available Actions</th></tr></thead>
                        <tbody>@foreach ($rbiUpdates as $update)<tr>
                            <td><strong>{{ optional($update->reporting_month)->format('F Y') ?: 'Not set' }}</strong><small>Barangay {{ $update->barangay_name ?: $barangay->name }}</small></td>
                            <td>{{ collect($update->rows ?? [])->pluck('household_head')->filter()->unique()->count() }}</td><td>{{ count($update->rows ?? []) }}</td>
                            <td><span class="request-status request-status-{{ $update->status }}">{{ $update->statusLabel() }}</span></td><td>{{ optional($update->submitted_at)->format('M d, Y · h:i A') ?: 'Not submitted' }}</td>
                            <td class="row-actions"><a href="{{ route('rbi-updates.show', $update) }}">View</a><a href="{{ route('barangay.rbi-updates.index', ['edit' => $update->id]) }}">{{ $update->status === App\Models\BarangayRbiUpdate::STATUS_DRAFT ? 'Continue Draft' : 'Update form' }}</a><a href="{{ route('rbi-updates.export-pdf', $update) }}">PDF</a><a href="{{ route('rbi-updates.export-word', $update) }}">Word</a>@if ($update->source_file_path)<a href="{{ route('rbi-updates.download', $update) }}">Original</a>@endif</td>
                        </tr>@endforeach</tbody>
                    </table></div>
                @endif
            </section>
        @endif
    </section>
@endsection
