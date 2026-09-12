@extends('layouts.app')

@section('content')
    @php
        $pendingDocuments = $barangayDocumentRequests->where('status', App\Models\DocumentRequest::STATUS_PENDING)->count();
        $submittedRbi = $rbiUpdates->where('status', App\Models\BarangayRbiUpdate::STATUS_SUBMITTED)->count();
        $draftRbi = $rbiUpdates->where('status', App\Models\BarangayRbiUpdate::STATUS_DRAFT)->count();
        $sourceWorkbook = in_array($barangay?->name, ['Canlupao', 'Biasong', 'Cabascan', 'Camansi', 'Carnaga', 'Cawayan', 'Higosoan', 'Hinagtikan', 'Hinapo', 'Hugpa', 'Iniguihan', 'Looc', 'Luan', 'Mag-ata', 'Maslog', 'Punong', 'Rizal', 'San Agustin'], true) ? strtoupper($barangay->name).'.xlsx' : null;
        $newInhabitantsUrl = $sourceWorkbook
            ? route('barangay.registry.new-inhabitants')
            : route('barangay.rbi-updates.index');
    @endphp

    <section class="barangay-dashboard workspace-page workspace-page-{{ $workspacePage ?? 'overview' }}" aria-labelledby="barangay-dashboard-title">
        <header class="dashboard-page-header dashboard-page-header-with-actions flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 bg-transparent bg-none pb-6 shadow-none">
            <div class="dashboard-title-group">
                <span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">{{ ($workspacePage ?? 'overview') === 'approvals' ? 'Resident Verification' : (($workspacePage ?? 'overview') === 'documents' ? 'Barangay E-Services' : 'Barangay Administration') }}</span>
                <h1 id="barangay-dashboard-title">{{ ($workspacePage ?? 'overview') === 'approvals' ? 'Resident approvals' : (($workspacePage ?? 'overview') === 'documents' ? 'Document requests' : 'Barangay '.($barangay?->name ?? 'Dashboard')) }}</h1>
                <p>{{ ($workspacePage ?? 'overview') === 'approvals' ? 'Review and verify resident accounts assigned to your barangay.' : (($workspacePage ?? 'overview') === 'documents' ? 'Process resident requests, payments, and release updates in one focused workspace.' : 'Process resident services, maintain community records, and prepare monthly RBI reports.') }}</p>
            </div>
            @if ($barangay)
                <div class="dashboard-header-actions">
                    <a class="button" href="{{ route('barangay.registry.active') }}"><x-app-icon name="users" /> Resident registry</a>
@if($barangay->usesResidenceRegistry())
                    <a class="button secondary-button" href="{{ route('barangay.residence.index') }}"><x-app-icon name="home" /> Living in Barangay</a>
@endif
                </div>
            @endif
        </header>

        @if (session('status'))<div class="success rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert"><strong>Please review the following:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        @if (! $barangay)
            <div class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert">This account is not assigned to a barangay. Ask the municipal administrator to complete the account assignment.</div>
        @else
            <div class="barangay-welcome-panel barangay-focus-panel rounded-xl border border-slate-200 bg-white bg-none shadow-sm border-l-4 border-l-blue-600 p-5 sm:p-6">
                <div class="barangay-welcome-copy">
                    <h2>Good day, {{ str(auth()->user()->name)->before(' ') }}.</h2>
                    <p>You have {{ $residentApprovalRequests->count() }} resident {{ \Illuminate\Support\Str::plural('registration', $residentApprovalRequests->count()) }} and {{ $pendingDocuments }} document {{ \Illuminate\Support\Str::plural('request', $pendingDocuments) }} awaiting review.</p>
                    <div class="barangay-primary-actions">
                        <a class="button government-primary-button" href="{{ route('barangay.resident-approvals.index') }}">
                            <span class="action-icon"><x-app-icon name="users" /></span><span><strong>Review resident registrations</strong><small>{{ $residentApprovalRequests->count() }} awaiting verification</small></span>
                        </a>
                        <a class="button government-outline-button" href="{{ route('barangay.document-requests.index') }}">
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
                    <div><span class="government-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Community Overview</span><h2 id="community-summary-title">Current Barangay Records</h2></div>
                    <span class="data-freshness">Database totals as of today</span>
                </div>
                <div class="dashboard-metrics grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="metric-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md {{ $residentApprovalRequests->isEmpty() ? 'metric-success' : 'metric-warning' }}"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="users" /></span><div><span>Resident approvals</span><strong>{{ number_format($residentApprovalRequests->count()) }}</strong><small>Registrations requiring review</small></div></article>
                    <article class="metric-card metric-primary rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="users" /></span><div><span>Registered inhabitants</span><strong>{{ number_format($barangay->inhabitants_count) }}</strong><small>Individual registry records</small></div></article>
@if($barangay->usesResidenceRegistry())
                    <article class="metric-card metric-success rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="home" /></span><div><span>Living in barangay</span><strong>{{ number_format($barangay->local_residents_count) }}</strong><small>Registered residents living here</small></div></article>
@endif
                    <article class="metric-card metric-success rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="home" /></span><div><span>Households</span><strong>{{ number_format($barangay->households_count) }}</strong><small>Household profiles on record</small></div></article>
                    <article class="metric-card metric-info rounded-xl border border-slate-200 bg-white bg-none shadow-sm flex min-h-32 items-start justify-between gap-4 border-t-4 border-t-blue-600 p-5 transition-shadow duration-200 hover:shadow-md"><span class="metric-icon flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><x-app-icon name="trend" /></span><div><span>Migration events</span><strong>{{ number_format($barangay->migration_records_count) }}</strong><small>Recorded arrivals and departures</small></div></article>
                </div>
            </section>

            <div class="barangay-dashboard-columns">
                <main class="barangay-dashboard-main">
                    <section class="government-content-card workspace-approvals-panel rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 overflow-hidden" aria-labelledby="resident-approvals-title">
                        <header class="government-card-header">
                            <div><span class="government-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Resident Verification</span><h2 id="resident-approvals-title">Pending Resident Registrations</h2><p>Verify residency before granting access to barangay online services.</p></div>
                            <span class="government-count-badge {{ $residentApprovalRequests->isEmpty() ? 'is-clear' : 'is-pending' }}">{{ $residentApprovalRequests->count() }} pending</span>
                        </header>
                        @if ($residentApprovalRequests->isEmpty())
                            <div class="government-empty-state rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-slate-600"><span class="empty-state-mark">âœ“</span><div><strong>Verification queue is clear</strong><span>New resident registrations will appear here automatically.</span></div></div>
                        @else
                            <div class="government-record-list divide-y divide-slate-100 ">
                                @foreach ($residentApprovalRequests as $resident)
                                    <article class="government-record-row border-b border-slate-100 bg-white p-4">
                                        <span class="record-avatar">{{ str($resident->name)->substr(0, 1)->upper() }}</span>
                                        <div class="record-identity"><strong>{{ $resident->name }}</strong><span>{{ $resident->email }}</span><small>Registered {{ $resident->created_at->format('M d, Y Â· h:i A') }}</small></div>
                                        <div class="approval-actions record-actions flex flex-wrap items-center gap-2">
                                            <form method="POST" action="{{ route('barangay.residents.approve', $resident) }}">@csrf<button type="submit">Approve Resident</button></form>
                                            <form method="POST" action="{{ route('barangay.residents.reject', $resident) }}" onsubmit="return confirm('Reject this resident registration?')">@csrf<button type="submit" class="danger-button">Reject</button></form>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section class="government-content-card workspace-documents-panel rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 overflow-hidden" aria-labelledby="document-requests-title">
                        <header class="government-card-header">
                            <div><span class="government-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Barangay E-Services</span><h2 id="document-requests-title">Resident Document Requests</h2><p>Process requests submitted by approved Barangay {{ $barangay->name }} residents.</p></div>
                            <span class="government-count-badge {{ $pendingDocuments === 0 ? 'is-clear' : 'is-pending' }}">{{ $pendingDocuments }} pending</span>
                        </header>
                        @if ($barangayDocumentRequests->isEmpty())
                            <div class="government-empty-state rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-slate-600"><span class="empty-state-mark">âœ“</span><div><strong>No document requests received</strong><span>Resident requests assigned to this barangay will appear here.</span></div></div>
                        @else
                            <div class="table-wrap government-table-wrap w-full overflow-x-auto rounded-xl border border-slate-200">
                                <table class="government-data-table">
                                    <thead><tr><th>Reference</th><th>Resident</th><th>Document and Purpose</th><th>GCash Payment</th><th>Status</th><th>Process Request</th></tr></thead>
                                    <tbody>@foreach ($barangayDocumentRequests as $documentRequest)
                                        <tr>
                                            <td><strong>{{ $documentRequest->reference_number }}</strong><small>{{ $documentRequest->created_at->format('M d, Y') }}</small></td>
                                            <td><strong>{{ $documentRequest->user->name }}</strong><small>{{ $documentRequest->user->email }}</small></td>
                                            <td><strong>{{ $documentRequest->typeLabel() }}</strong><small>{{ $documentRequest->purpose }}</small></td>
                                            <td class="document-payment-cell">
                                                @if ($documentRequest->requiresPayment())
                                                    <strong>â‚±{{ number_format((float) $documentRequest->amount_due, 2) }}</strong>
                                                    <span class="payment-status payment-status-{{ $documentRequest->payment_status }}">{{ $documentRequest->paymentStatusLabel() }}</span>
                                                    @if ($documentRequest->payment_reference)
                                                        <small>Ref: {{ $documentRequest->payment_reference }}<br>Paid: {{ optional($documentRequest->payment_transaction_at)->format('M d, Y h:i A') }}<br>{{ $documentRequest->payer_name }} Â· {{ $documentRequest->payer_mobile }}</small>
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
                    <section class="government-side-card rbi-status-card">
                        <header><span class="government-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Reporting Status</span><h2>Monthly RBI Forms</h2></header>
                        <div class="rbi-status-summary"><div><strong>{{ $submittedRbi }}</strong><span>Submitted</span></div><div><strong>{{ $draftRbi }}</strong><span>Drafts</span></div></div>
                        @if ($rbiUpdates->isEmpty())
                            <p>No RBI monthly report has been created.</p>
                        @else
                            @php($latestRbi = $rbiUpdates->first())
                            <div class="latest-report"><span>Latest report</span><strong>{{ optional($latestRbi->reporting_month)->format('F Y') ?: 'Month not set' }}</strong><small>{{ $latestRbi->statusLabel() }} Â· {{ count($latestRbi->rows ?? []) }} inhabitant entries</small></div>
                        @endif
                        <a class="button government-outline-button primary-block" href="{{ $newInhabitantsUrl }}">Manage Monthly Reports</a>
                    </section>

                    <section class="government-side-card security-card"><span class="security-mark">DPA</span><div><strong>Data Privacy Reminder</strong><p>Access resident information only for authorized barangay functions. Keep account credentials confidential.</p></div></section>
                </aside>
            </div>

            <section class="government-content-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 overflow-hidden" aria-labelledby="rbi-history-title">
                <header class="government-card-header">
                    <div><span class="government-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Secretary Copies</span><h2 id="rbi-history-title">Monthly RBI Form History</h2><p>Review, update, and download the official copies retained by this barangay.</p></div>
                    <div class="row-actions"><a class="button government-outline-button" href="{{ route('barangay.rbi-updates.index') }}">Open RBI Forms</a><a class="button government-outline-button" href="{{ $newInhabitantsUrl }}">Open Monthly Reports</a></div>
                </header>
                @if ($rbiUpdates->isEmpty())
                    <div class="government-empty-state rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-slate-600"><span class="empty-state-mark">â€”</span><div><strong>No monthly RBI forms created yet</strong><span>Create the first monthly report through RBI Forms.</span></div></div>
                @else
                    <div class="table-wrap government-table-wrap w-full overflow-x-auto rounded-xl border border-slate-200"><table class="government-data-table">
                        <thead><tr><th>Reporting Month</th><th>Families</th><th>Entries</th><th>Status</th><th>Submitted</th><th>Available Actions</th></tr></thead>
                        <tbody>@foreach ($rbiUpdates as $update)<tr>
                            <td><strong>{{ optional($update->reporting_month)->format('F Y') ?: 'Not set' }}</strong><small>Barangay {{ $update->barangay_name ?: $barangay->name }}</small></td>
                            <td>{{ collect($update->rows ?? [])->pluck('household_head')->filter()->unique()->count() }}</td><td>{{ count($update->rows ?? []) }}</td>
                            <td><span class="request-status request-status-{{ $update->status }}">{{ $update->statusLabel() }}</span></td><td>{{ optional($update->submitted_at)->format('M d, Y Â· h:i A') ?: 'Not submitted' }}</td>
                            <td class="row-actions"><a href="{{ route('rbi-updates.show', $update) }}">View</a><a href="{{ route('barangay.rbi-updates.index', ['edit' => $update->id]) }}">{{ $update->status === App\Models\BarangayRbiUpdate::STATUS_DRAFT ? 'Continue Draft' : 'Update form' }}</a><a href="{{ route('rbi-updates.export-pdf', $update) }}">PDF</a><a href="{{ route('rbi-updates.export-word', $update) }}">Word</a>@if ($update->source_file_path)<a href="{{ route('rbi-updates.download', $update) }}">Original</a>@endif @include('rbi-updates._registry-action', ['report' => $update])</td>
                        </tr>@endforeach</tbody>
                    </table></div>
                @endif
            </section>
        @endif
    </section>
@endsection
