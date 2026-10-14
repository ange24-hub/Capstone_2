<section class="dashboard-card civic-request-overview" aria-labelledby="resident-recent-title">
    <header class="dashboard-card-header"><div><h2 id="resident-recent-title">Your requests</h2><p>Check document and payment statuses separately.</p></div><a class="button secondary-button" href="{{ route('resident.document-requests.index') }}">View all requests</a></header>
    @forelse($documentRequests->take(3) as $request)
        <article class="civic-request-row">
            <div><strong>{{ $request->typeLabel() }}</strong><small>{{ $request->reference_number }} · {{ $request->created_at->format('M d, Y') }}</small></div>
            <div class="civic-request-states"><div><span class="civic-status-label">Document</span><x-status-badge :status="$request->status">{{ $request->statusLabel() }}</x-status-badge></div>@if($request->requiresPayment())<div><span class="civic-status-label">Payment</span><x-status-badge :status="$request->payment_status">{{ $request->paymentStatusLabel() }}</x-status-badge></div>@endif</div>
            <p class="field-help">
                @if($request->status === App\Models\DocumentRequest::STATUS_REJECTED)
                    Check the barangay remarks in My requests.
                @elseif($request->status === App\Models\DocumentRequest::STATUS_COMPLETED)
                    This request is complete.
                @elseif($request->paymentIsOpen() && $request->requiresPayment() && !$request->hasAssessedFee())
                    Wait for the barangay to set the fee before paying.
                @elseif($request->paymentIsOpen() && $request->requiresPayment() && in_array($request->payment_status, [App\Models\DocumentRequest::PAYMENT_UNPAID, App\Models\DocumentRequest::PAYMENT_REJECTED], true))
                    Open My requests to pay {{ $request->amountDueLabel() }} or review the payment remarks.
                @elseif($request->requiresPayment() && $request->payment_status === App\Models\DocumentRequest::PAYMENT_PENDING)
                    Your payment is awaiting barangay verification.
                @elseif($request->status === App\Models\DocumentRequest::STATUS_READY)
                    Visit your barangay office to collect the document.
                @else
                    Your barangay is reviewing or processing this request.
                @endif
            </p>
        </article>
    @empty
        <x-empty-state title="No requests yet" description="Your first document request will appear here." icon="document"><a class="button" href="{{ route('resident.document-requests.create') }}">Request a document</a></x-empty-state>
    @endforelse
</section>
