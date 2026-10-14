@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/barangay-service-colors.css') }}?v={{ filemtime(public_path('css/barangay-service-colors.css')) }}" media="screen">
@endpush

@section('content')
<section class="payment-history-page" aria-labelledby="payment-history-title">
    <div class="dashboard-card payment-history-header rounded-xl border border-slate-200 bg-white p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 id="payment-history-title" class="text-2xl font-bold">{{ $secretary ? 'Barangay payment history' : 'My payment history' }}</h1>
            <p class="text-sm text-slate-600">GCash submissions and reviews, and payments received in person. Pending or rejected entries are not confirmed payments.</p></div>
            <a class="button secondary-button" href="{{ route($secretary ? 'barangay.document-requests.index' : 'resident.document-requests.index') }}">Document requests</a>
        </div>
    </div>
    <div class="dashboard-card payment-history-card rounded-xl border border-slate-200 bg-white p-5">
        <div class="payment-history-table-wrap table-wrap overflow-x-auto">
            <table class="payment-history-table w-full text-sm">
                <thead><tr><th>Date (PhST)</th>@if($secretary)<th>Resident</th>@endif<th>Request / document</th><th>Amount</th><th>Method / reference</th><th>Update</th><th>Recorded by / remarks</th><th>Receipt</th></tr></thead>
                <tbody>
                @forelse($histories as $history)
                    @php($payment = $history->snapshot)
                    <tr>
                        <td>{{ $history->occurred_at->timezone('Asia/Manila')->format('M d, Y g:i A') }}</td>
                        @if($secretary)<td>{{ $history->documentRequest->user?->name ?? 'Resident unavailable' }}</td>@endif
                        <td><strong>{{ $payment['reference_number'] }}</strong><br>{{ App\Models\DocumentRequest::typeLabels()[$payment['document_type']] ?? 'Document' }}</td>
                        <td>₱{{ number_format((float) $payment['amount_due'], 2) }}</td>
                        <td>{{ ($payment['payment_method'] ?? '') === 'gcash' ? 'GCash' : 'In person' }}<br>{{ $payment['payment_reference'] ?? '—' }}</td>
                        <td><strong>{{ $history->eventLabel() }}</strong><br><span class="payment-history-status" data-status="{{ $payment['payment_status'] ?? '' }}">{{ App\Models\DocumentRequest::paymentStatusLabels()[$payment['payment_status']] ?? $payment['payment_status'] }}</span></td>
                        <td>{{ $history->actor?->name ?? 'Existing record' }}@if(!empty($payment['payment_remarks']))<br>{{ $payment['payment_remarks'] }}@endif</td>
                        <td>@if(!empty($payment['payment_proof_path']) && ($payment['payment_method'] ?? '') === 'gcash')<a href="{{ route('document-payments.history.proof', $history) }}">View receipt</a>@else — @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $secretary ? 8 : 7 }}"><x-empty-state title="No payment history yet." description="Payments will appear here when submitted or received." icon="payment" /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $histories->links() }}</div>
    </div>
</section>
@endsection
