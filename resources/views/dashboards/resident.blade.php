@extends('layouts.app')

@section('content')
    @php
        $pendingRequests = $documentRequests->where('status', App\Models\DocumentRequest::STATUS_PENDING)->count();
        $activeRequests = $documentRequests->whereIn('status', [App\Models\DocumentRequest::STATUS_PROCESSING, App\Models\DocumentRequest::STATUS_READY])->count();
        $completedRequests = $documentRequests->where('status', App\Models\DocumentRequest::STATUS_COMPLETED)->count();
    @endphp

    <section class="dashboard-page resident-dashboard workspace-page workspace-page-{{ $workspacePage ?? 'overview' }}" aria-labelledby="resident-dashboard-title">
        <header class="dashboard-page-header">
            <div class="dashboard-title-group">
                <span class="dashboard-eyebrow">{{ ($workspacePage ?? 'overview') === 'create' ? 'New Transaction' : (($workspacePage ?? 'overview') === 'history' ? 'Transaction History' : 'Resident Services') }}</span>
                <h1 id="resident-dashboard-title">{{ ($workspacePage ?? 'overview') === 'create' ? 'Request a document' : (($workspacePage ?? 'overview') === 'history' ? 'My document requests' : 'Good day, '.str(auth()->user()->name)->before(' ').'.') }}</h1>
                <p>{{ ($workspacePage ?? 'overview') === 'create' ? 'Choose a barangay document and submit its purpose.' : (($workspacePage ?? 'overview') === 'history' ? 'Track statuses, payment verification, and barangay remarks.' : 'Request barangay documents and follow every update from one place.') }}</p>
            </div>
            <div class="dashboard-context-card">
                <span class="context-icon"><x-app-icon name="location" /></span>
                <div><small>Your assigned barangay</small><strong>Barangay {{ auth()->user()->barangay?->name ?? 'Not assigned' }}</strong><span>Approved resident account</span></div>
            </div>
        </header>

        @if (session('status'))<div class="success" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="errors" role="alert"><strong>Please check the following:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <section class="dashboard-metrics" aria-label="Request summary">
            <article class="metric-card metric-primary"><span class="metric-icon"><x-app-icon name="document" /></span><div><span>All requests</span><strong>{{ number_format($documentRequests->count()) }}</strong><small>Your complete request history</small></div></article>
            <article class="metric-card metric-warning"><span class="metric-icon"><x-app-icon name="clock" /></span><div><span>Awaiting review</span><strong>{{ number_format($pendingRequests) }}</strong><small>Submitted to your barangay</small></div></article>
            <article class="metric-card metric-info"><span class="metric-icon"><x-app-icon name="activity" /></span><div><span>In progress</span><strong>{{ number_format($activeRequests) }}</strong><small>Processing or ready to claim</small></div></article>
            <article class="metric-card metric-success"><span class="metric-icon"><x-app-icon name="check" /></span><div><span>Completed</span><strong>{{ number_format($completedRequests) }}</strong><small>Finished transactions</small></div></article>
        </section>

        <nav class="workspace-launcher" aria-label="Resident service shortcuts">
            <a href="{{ route('resident.document-requests.create') }}"><span class="workspace-launcher-icon"><x-app-icon name="form" /></span><span><small>Start a transaction</small><strong>Request a document</strong><em>Submit a new barangay document request</em></span><x-app-icon name="arrow-right" /></a>
            <a href="{{ route('resident.document-requests.index') }}"><span class="workspace-launcher-icon"><x-app-icon name="document" /></span><span><small>Track transactions</small><strong>View my requests</strong><em>Check status, payment, and remarks</em></span><x-app-icon name="arrow-right" /></a>
        </nav>

        @if (auth()->user()->hasRole(App\Models\User::ROLE_RESIDENT))
            <div class="resident-action-layout">
                <section class="dashboard-card request-form-card" aria-labelledby="request-form-title">
                    <header class="dashboard-card-header">
                        <div><span class="dashboard-eyebrow">Start a transaction</span><h2 id="request-form-title">Request a document</h2><p>Choose a form and tell the barangay how you will use it.</p></div>
                        <span class="section-number">01</span>
                    </header>

                    @if (! auth()->user()->barangay?->gcashIsReady())
                        <div class="payment-notice">Online payment is not yet active for your barangay. Documents with a fee will become available after the official GCash profile is approved.</div>
                    @endif

                    <form method="POST" action="{{ route('resident.document-requests.store') }}" class="clean-form">
                        @csrf
                        <div class="field-group">
                            <label for="document_type">Document type</label>
                            <select id="document_type" name="document_type" required>
                                <option value="">Choose a document</option>
                                @foreach ($documentTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }} — {{ App\Models\DocumentRequest::feeFor($value) > 0 ? '₱'.number_format(App\Models\DocumentRequest::feeFor($value), 2) : 'No fee' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="purpose">Purpose</label>
                            <textarea id="purpose" name="purpose" maxlength="255" placeholder="Example: Employment requirement" required>{{ old('purpose') }}</textarea>
                            <small class="field-help">Keep the purpose brief and specific.</small>
                        </div>
                        <div class="form-actions"><button type="submit">Submit request <x-app-icon name="arrow-right" /></button></div>
                    </form>
                </section>

                <aside class="dashboard-card process-guide-card" aria-labelledby="request-process-title">
                    <header class="dashboard-card-header"><div><span class="dashboard-eyebrow">What happens next</span><h2 id="request-process-title">Request process</h2></div></header>
                    <ol class="process-list">
                        <li><span>1</span><div><strong>Submit your request</strong><small>Your barangay receives it immediately.</small></div></li>
                        <li><span>2</span><div><strong>Complete payment, if required</strong><small>Use only the official GCash details shown here.</small></div></li>
                        <li><span>3</span><div><strong>Wait for confirmation</strong><small>Track the status and barangay remarks below.</small></div></li>
                        <li><span>4</span><div><strong>Claim your document</strong><small>Visit the barangay when its status is Ready.</small></div></li>
                    </ol>
                    <div class="privacy-note"><x-app-icon name="shield" /><span><strong>Your information is protected</strong><small>Only authorized barangay personnel can process your request.</small></span></div>
                </aside>
            </div>
        @endif

        <section class="dashboard-card" aria-labelledby="request-history-title">
            <header class="dashboard-card-header">
                <div><span class="dashboard-eyebrow">Transaction history</span><h2 id="request-history-title">My document requests</h2><p>Latest requests appear first. Open payment details only when action is required.</p></div>
                <span class="count-chip">{{ $documentRequests->count() }} total</span>
            </header>

            @if ($documentRequests->isEmpty())
                <div class="dashboard-empty-state"><span class="empty-icon"><x-app-icon name="inbox" /></span><strong>No requests yet</strong><span>Your first document request will appear here.</span></div>
            @else
                <div class="request-card-list">
                    @foreach ($documentRequests as $request)
                        <article class="resident-request-card">
                            <div class="request-card-main">
                                <div class="request-reference"><small>Reference</small><strong>{{ $request->reference_number }}</strong></div>
                                <div class="request-document"><strong>{{ $request->typeLabel() }}</strong><span>{{ $request->purpose }}</span><small>Requested {{ $request->created_at->format('M d, Y · h:i A') }}</small></div>
                                <div class="request-status-stack">
                                    <span class="request-status request-status-{{ $request->status }}">{{ $request->statusLabel() }}</span>
                                    @if ($request->requiresPayment())<span class="payment-status payment-status-{{ $request->payment_status }}">{{ $request->paymentStatusLabel() }}</span>@endif
                                </div>
                            </div>
                            <div class="request-card-details">
                                <div><span>Receiving office</span><strong>Barangay {{ $request->barangay?->name ?? auth()->user()->barangay?->name }}</strong></div>
                                <div><span>Barangay remarks</span><strong>{{ $request->remarks ?: 'No remarks yet' }}</strong></div>
                                <div><span>Amount due</span><strong>{{ $request->requiresPayment() ? '₱'.number_format((float) $request->amount_due, 2) : 'No fee' }}</strong></div>
                            </div>

                            @if ($request->requiresPayment())
                                <div class="request-payment-area">
                                    @if (in_array($request->payment_status, [App\Models\DocumentRequest::PAYMENT_UNPAID, App\Models\DocumentRequest::PAYMENT_REJECTED], true))
                                        @if ($request->payment_remarks)<div class="payment-feedback">Barangay note: {{ $request->payment_remarks }}</div>@endif
                                        @if ($request->barangay?->gcashIsReady())
                                            <details class="gcash-payment-details">
                                                <summary>Pay ₱{{ number_format((float) $request->amount_due, 2) }} with official GCash</summary>
                                                <div class="payment-layout">
                                                    <div>
                                                        <div class="gcash-merchant-card"><span>Official recipient</span><strong>{{ $request->barangay->gcash_merchant_name }}</strong><small>{{ $request->barangay->gcash_account_identifier }}</small><img src="{{ route('barangays.gcash.qr', $request->barangay) }}" alt="Official Barangay {{ $request->barangay->name }} GCash merchant QR code"><em>Barangay {{ $request->barangay->name }}</em><b>Pay exactly ₱{{ number_format((float) $request->amount_due, 2) }}</b></div>
                                                        <ol class="payment-steps"><li>Confirm the official recipient and exact amount.</li><li>Pay once and copy the 13-digit reference.</li><li>Upload the receipt for verification.</li></ol>
                                                    </div>
                                                    <form class="gcash-proof-form" method="POST" enctype="multipart/form-data" action="{{ route('resident.document-payments.submit', $request) }}">
                                                        @csrf
                                                        <label for="payer_name_{{ $request->id }}">GCash account name</label><input id="payer_name_{{ $request->id }}" name="payer_name" maxlength="100" required>
                                                        <label for="payer_mobile_{{ $request->id }}">GCash mobile number</label><input id="payer_mobile_{{ $request->id }}" name="payer_mobile" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" required>
                                                        <label for="payment_reference_{{ $request->id }}">13-digit reference</label><input id="payment_reference_{{ $request->id }}" name="payment_reference" inputmode="numeric" pattern="[0-9]{13}" maxlength="13" required>
                                                        <label for="payment_transaction_at_{{ $request->id }}">Payment date and time</label><input id="payment_transaction_at_{{ $request->id }}" name="payment_transaction_at" type="datetime-local" max="{{ now()->format('Y-m-d\TH:i') }}" required>
                                                        <label for="payment_proof_{{ $request->id }}">Receipt screenshot</label><input id="payment_proof_{{ $request->id }}" name="payment_proof" type="file" accept="image/png,image/jpeg" required>
                                                        <button type="submit">Submit payment for verification</button>
                                                    </form>
                                                </div>
                                            </details>
                                        @else
                                            <small>Online collection is awaiting official GCash setup.</small>
                                        @endif
                                    @elseif ($request->payment_status === App\Models\DocumentRequest::PAYMENT_PENDING)
                                        <div class="payment-summary"><span>Payment submitted for verification</span><strong>Reference {{ $request->payment_reference }}</strong><small>{{ optional($request->payment_transaction_at)->format('M d, Y · h:i A') }}</small>@if ($request->payment_proof_path)<a href="{{ route('document-payments.proof', $request) }}">View submitted receipt</a>@endif</div>
                                    @elseif ($request->payment_status === App\Models\DocumentRequest::PAYMENT_PAID)
                                        <div class="payment-summary is-paid"><span>Payment verified</span><strong>Reference {{ $request->payment_reference }}</strong><small>Verified {{ optional($request->paid_at)->format('M d, Y · h:i A') }}</small></div>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </section>
@endsection
