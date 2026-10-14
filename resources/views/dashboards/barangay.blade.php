@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/barangay-dashboard.css') }}?v={{ filemtime(public_path('css/barangay-dashboard.css')) }}" media="screen">
    @if(request()->routeIs('barangay.resident-approvals.index', 'barangay.document-requests.index'))
        <link rel="stylesheet" href="{{ asset('css/barangay-service-colors.css') }}?v={{ filemtime(public_path('css/barangay-service-colors.css')) }}" media="screen">
    @endif
@endpush

@section('content')
    @php
        $submittedRbi = $rbiUpdates->where('status', App\Models\BarangayRbiUpdate::STATUS_SUBMITTED)->count();
        $draftRbi = $rbiUpdates->where('status', App\Models\BarangayRbiUpdate::STATUS_DRAFT)->count();
    @endphp

    <section class="barangay-dashboard workspace-page workspace-page-{{ $workspacePage ?? 'overview' }}" aria-labelledby="barangay-dashboard-title">
        <header class="dashboard-page-header dashboard-page-header-with-actions flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 bg-transparent bg-none pb-6 shadow-none">
            <span class="barangay-hero-weave" aria-hidden="true"></span>
            <x-workspace-heading icon="building">
                <span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">{{ ($workspacePage ?? 'overview') === 'approvals' ? 'Resident Verification' : (($workspacePage ?? 'overview') === 'documents' ? 'Barangay E-Services' : 'Barangay Administration') }}</span>
                <h1 id="barangay-dashboard-title">{{ ($workspacePage ?? 'overview') === 'approvals' ? 'Resident approvals' : (($workspacePage ?? 'overview') === 'documents' ? 'Document requests' : 'Barangay '.($barangay?->name ?? 'Dashboard')) }}</h1>
                <p>{{ ($workspacePage ?? 'overview') === 'approvals' ? 'Review and verify resident accounts assigned to your barangay.' : (($workspacePage ?? 'overview') === 'documents' ? 'Review requests, confirm payments, and update their status.' : 'Manage RBI reports and resident services.') }}</p>
            </x-workspace-heading>
            @if ($barangay && ($workspacePage ?? 'overview') === 'overview')
                <div class="dashboard-header-actions">
                    <a class="button" href="{{ route('barangay.rbi-updates.index') }}"><x-app-icon name="form" /> RBI Forms</a>
                    <a class="button secondary-button" href="{{ route('barangay.registry.active') }}"><x-app-icon name="users" /> Resident registry</a>
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
            @if (($workspacePage ?? 'overview') === 'overview')
            <div class="admin-section-heading">
                <div><span>Today</span><h2>Needs attention</h2></div>
                <p>Start with the queues that need a review.</p>
            </div>
            <section class="civic-task-metrics" aria-label="Tasks requiring action">
                <x-summary-card label="Pending resident approvals" :value="number_format($residentApprovalRequests->count())" detail="Review registrations against RBI records" icon="shield" :tone="$residentApprovalRequests->isEmpty() ? 'neutral' : 'warning'" />
                <x-summary-card label="Pending document requests" :value="number_format($pendingDocuments)" detail="Review requests and verify submitted payments" icon="document" :tone="$pendingDocuments ? 'warning' : 'neutral'" />
            </section>
            <div class="barangay-overview-layout">
                <div class="admin-section-heading">
                    <div><span>Manage</span><h2>Reports and services</h2></div>
                </div>
                <div class="overview-work-row">
                @include('dashboards.partials.barangay-workspace')
                <section class="overview-tools" aria-labelledby="barangay-tools-title">
                    <h2 id="barangay-tools-title">Quick access</h2>
                    <nav class="brgy-quick-links" aria-label="Other barangay tools">
                        @foreach ([['concerns.index', 'activity', 'Resident concerns'], ['offline.household', 'location', 'Household survey'], ['spatial.index', 'map', 'Household map']] as [$routeName, $icon, $title])
                            <a href="{{ route($routeName) }}"><span class="brgy-tool-icon"><x-app-icon :name="$icon" /></span><strong>{{ $title }}</strong><span class="brgy-link-arrow" aria-hidden="true">&rarr;</span></a>
                        @endforeach
                    </nav>
                </section>
                </div>
                <section class="overview-community" aria-labelledby="community-summary-title">
                    <div class="admin-section-heading">
                        <div><span>Community</span><h2 id="community-summary-title">Resident summary</h2></div>
                        <p>Population records and resident groups at a glance.</p>
                    </div>
                    @if (isset($populationCounts))
                        <x-resident-population-charts :counts="$populationCounts" :barangay="$barangay" />
                    @endif
                </section>
            </div>

            @else
            <div class="barangay-dashboard-columns">
                <div class="barangay-dashboard-main">
                    @if (($workspacePage ?? 'overview') === 'approvals')
                    <section class="government-content-card workspace-approvals-panel rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 overflow-hidden" aria-labelledby="resident-approvals-title">
                        <header class="government-card-header">
                            <div><h2 id="resident-approvals-title">Pending registrations</h2><p>Residency is checked automatically against the consolidated RBI before account approval.</p></div>
                            <span class="government-count-badge {{ $residentApprovalRequests->isEmpty() ? 'is-clear' : 'is-pending' }}">{{ $residentApprovalRequests->count() }} pending</span>
                        </header>
                        @if ($residentApprovalRequests->isEmpty())
                            <div class="government-empty-state rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-slate-600"><span class="empty-state-mark" aria-hidden="true">&#10003;</span><div><strong>Verification queue is clear</strong><span>New resident registrations will appear here automatically.</span></div></div>
                        @else
                            <div class="government-record-list divide-y divide-slate-100 ">
                                @foreach ($residentApprovalRequests as $resident)
                                    @php($rbiCheck = $residentRbiChecks[$resident->id])
                                    <article class="government-record-row border-b border-slate-100 bg-white p-4">
                                        <span class="record-avatar">{{ str($resident->name)->substr(0, 1)->upper() }}</span>
                                        <div class="record-identity"><strong>{{ $resident->name }}</strong><span>{{ $resident->email }}</span><small>Registered {{ $resident->created_at->format('M d, Y') }}</small></div>
                                        <div class="approval-actions record-actions flex flex-wrap items-center gap-2">
                                            <div class="resident-rbi-note rounded-xl border p-4 {{ $rbiCheck['eligible'] ? 'border-blue-200 bg-blue-50 text-blue-900' : 'border-red-200 bg-red-50 text-red-900' }}" data-verification="{{ $rbiCheck['eligible'] ? 'matched' : 'review' }}" style="flex-basis: 100%; max-width: 36rem;" role="note">
                                                <strong>{{ $rbiCheck['label'] }}</strong>
                                                <p>{{ $rbiCheck['note'] }}</p>
                                                @foreach ($rbiCheck['matches'] as $match)
                                                    <p><small>RBI #{{ $match->id }}: {{ $match->fullName() }} · Household {{ $match->household?->household_number ?? 'Not recorded' }} · {{ $match->statusLabel() }} · {{ $match->residenceLabel() }}</small></p>
                                                @endforeach
                                                @unless ($rbiCheck['eligible'])
                                                    <a href="{{ route('barangay.registry.active', ['search' => $resident->name]) }}">Review consolidated RBI</a>
                                                @endunless
                                            </div>
                                            <form method="POST" action="{{ route('barangay.residents.approve', $resident) }}">
                                                @csrf
                                                <button type="submit" @disabled(! $rbiCheck['eligible']) @if (! $rbiCheck['eligible']) style="opacity: .5; cursor: not-allowed;" title="RBI verification has not cleared this request" @endif>Approve Resident</button>
                                            </form>
                                            <form method="POST" action="{{ route('barangay.residents.reject', $resident) }}" onsubmit="return confirm('Reject this resident registration?')">@csrf<button type="submit" class="danger-button">Reject</button></form>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    @endif
                    @if (($workspacePage ?? 'overview') === 'documents')
                    <section id="document-requests" class="government-content-card workspace-documents-panel rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 overflow-hidden" aria-labelledby="document-requests-title">
                        <header class="government-card-header">
                            <div><h2 id="document-requests-title">Requests to process</h2></div>
                            <span class="government-count-badge {{ $pendingDocuments === 0 ? 'is-clear' : 'is-pending' }}">{{ $pendingDocuments }} pending</span>
                        </header>
                        @if ($barangayDocumentRequests->total() === 0)
                            <div class="government-empty-state rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-slate-600"><span class="empty-state-mark" aria-hidden="true">&#10003;</span><div><strong>No document requests received</strong><span>Resident requests assigned to this barangay will appear here.</span></div></div>
                        @elseif ($barangayDocumentRequests->isEmpty())
                            <div class="government-empty-state"><div><strong>No requests on this page</strong><a href="{{ $barangayDocumentRequests->url(1) }}">Return to the first page</a></div></div>
                        @else
                            <p class="document-list-summary">Showing <strong>{{ $barangayDocumentRequests->firstItem() }}&ndash;{{ $barangayDocumentRequests->lastItem() }}</strong> of <strong>{{ $barangayDocumentRequests->total() }}</strong> requests</p>
                            <div class="table-wrap government-table-wrap w-full overflow-x-auto rounded-xl border border-slate-200">
                                <table class="government-data-table">
                                    <thead><tr><th scope="col">Reference</th><th scope="col">Resident</th><th scope="col">Document and Purpose</th><th scope="col">Payment</th><th scope="col">Status</th><th scope="col">Process Request</th></tr></thead>
                                    <tbody>@foreach ($barangayDocumentRequests as $documentRequest)
                                        <tr>
                                            <td><strong>{{ $documentRequest->reference_number }}</strong><small>{{ $documentRequest->created_at->format('M d, Y') }}</small></td>
                                            <td><strong>{{ $documentRequest->user->name }}</strong><small>{{ $documentRequest->user->email }}</small></td>
                                            <td><strong>{{ $documentRequest->typeLabel() }}</strong><small>{{ $documentRequest->purpose }}</small></td>
                                            <td class="document-payment-cell">
                                                @if ($documentRequest->requiresPayment())
                                                    <strong>{{ $documentRequest->amountDueLabel() }}</strong>
                                                    <span class="payment-status payment-status-{{ $documentRequest->payment_status }}">{{ $documentRequest->paymentStatusLabel() }}</span>
                                                    @if ($documentRequest->payment_method === App\Models\DocumentRequest::PAYMENT_METHOD_IN_PERSON)
                                                        <small>Payment method: In person</small>
                                                    @endif
                                                    @if ($documentRequest->paymentIsOpen() && ! in_array($documentRequest->payment_status, [App\Models\DocumentRequest::PAYMENT_PAID, App\Models\DocumentRequest::PAYMENT_PENDING], true))
                                                        <form class="payment-verification-form" method="POST" action="{{ route('barangay.document-payments.assess', $documentRequest) }}">
                                                            @csrf
                                                            <label for="amount-due-{{ $documentRequest->id }}">Set fee (PHP)</label>
                                                            <input id="amount-due-{{ $documentRequest->id }}" name="amount_due" type="number" min="0.01" max="99999999.99" step="0.01" value="{{ $documentRequest->hasAssessedFee() ? $documentRequest->amount_due : '' }}" required>
                                                            <label for="fee-note-{{ $documentRequest->id }}">Payment instructions (optional)</label>
                                                            <input id="fee-note-{{ $documentRequest->id }}" name="payment_remarks" maxlength="1000" value="{{ $documentRequest->payment_remarks }}">
                                                            <button type="submit">Set fee &amp; request payment</button>
                                                        </form>
                                                        @if ($documentRequest->hasAssessedFee())
                                                            <form class="payment-verification-form" method="POST" action="{{ route('barangay.document-payments.receive-in-person', $documentRequest) }}">
                                                                @csrf
                                                                <label><input type="checkbox" name="confirm_received" value="1" required> I received {{ $documentRequest->amountDueLabel() }} in person.</label>
                                                                <button type="submit">Confirm payment received</button>
                                                            </form>
                                                        @endif
                                                    @endif
                                                    @if ($documentRequest->payment_reference && $documentRequest->payment_method === App\Models\DocumentRequest::PAYMENT_METHOD_GCASH)
                                                        <small>Ref: {{ $documentRequest->payment_reference }}<br>Paid: {{ optional($documentRequest->payment_transaction_at)->format('M d, Y h:i A') }}<br>{{ $documentRequest->payer_name }} &middot; {{ $documentRequest->payer_mobile }}</small>
                                                    @endif
                                                    @if ($documentRequest->payment_proof_path && $documentRequest->payment_method === App\Models\DocumentRequest::PAYMENT_METHOD_GCASH)
                                                        <a href="{{ route('document-payments.proof', $documentRequest) }}">Open receipt proof</a>
                                                    @endif
                                                    @if ($documentRequest->payment_status === App\Models\DocumentRequest::PAYMENT_PENDING)
                                                        <small>Match the reference, amount, and time in the official GCash for Business portal before verifying.</small>
                                                        <form class="payment-verification-form" method="POST" action="{{ route('barangay.document-payments.verify', $documentRequest) }}">
                                                            @csrf
                                                            <label for="payment-remarks-{{ $documentRequest->id }}">Payment review note</label>
                                                            <input id="payment-remarks-{{ $documentRequest->id }}" name="payment_remarks" maxlength="1000" placeholder="Required reason when rejecting">
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
                                                <label for="request-status-{{ $documentRequest->id }}">Request status</label>
                                                <select id="request-status-{{ $documentRequest->id }}" name="status" aria-label="Status for {{ $documentRequest->reference_number }}" required>@foreach ($documentRequestStatuses as $value => $label)<option value="{{ $value }}" @selected($documentRequest->status === $value)>{{ $label }}</option>@endforeach</select>
                                                <label for="request-remarks-{{ $documentRequest->id }}">Remarks</label>
                                                <input id="request-remarks-{{ $documentRequest->id }}" name="remarks" type="text" maxlength="1000" value="{{ $documentRequest->remarks }}" placeholder="Add remarks" aria-label="Remarks for {{ $documentRequest->reference_number }}"><button type="submit">Save Update</button>
                                            </form></td>
                                        </tr>
                                    @endforeach</tbody>
                                </table>
                            </div>
                            {{ $barangayDocumentRequests->onEachSide(1)->links('dashboards.partials.document-pagination') }}
                        @endif
                    </section>
                    @endif
                </div>

            </div>
            @endif
        @endif
    </section>
@endsection

@section('footer-reminder')
    <div class="footer-privacy-reminder">
        <span class="footer-privacy-mark" aria-hidden="true">DPA</span>
        <div><strong>Data Privacy Reminder</strong><p>Access resident information only for authorized barangay functions. Keep account credentials confidential.</p></div>
    </div>
@endsection
