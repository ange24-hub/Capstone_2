<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->barangay_id, 403, 'Your resident account is not assigned to a barangay.');

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(DocumentRequest::typeLabels()))],
            'purpose' => ['required', 'string', 'max:255'],
        ]);

        $amountDue = DocumentRequest::feeFor($validated['document_type']);

        if ($amountDue > 0 && ! $request->user()->barangay?->gcashIsReady()) {
            return back()->withInput()->withErrors([
                'document_type' => 'Online GCash collection is not yet active for this paid document. Please contact your Barangay office.',
            ]);
        }

        $documentRequest = $request->user()->documentRequests()->create([
            'barangay_id' => $request->user()->barangay_id,
            'reference_number' => $this->makeReferenceNumber(),
            'document_type' => $validated['document_type'],
            'purpose' => $validated['purpose'],
            'status' => DocumentRequest::STATUS_PENDING,
            'amount_due' => $amountDue,
            'payment_method' => $amountDue > 0 ? DocumentRequest::PAYMENT_METHOD_GCASH : null,
            'payment_status' => $amountDue > 0 ? DocumentRequest::PAYMENT_UNPAID : DocumentRequest::PAYMENT_NOT_REQUIRED,
        ]);

        $message = 'Your request '.$documentRequest->reference_number.' was sent to Barangay '.$request->user()->barangay->name.'.';
        $message .= $amountDue > 0
            ? ' Complete the ₱'.number_format($amountDue, 2).' GCash payment shown below before processing.'
            : ' No online payment is required for this document.';

        return back()->with('status', $message);
    }

    private function makeReferenceNumber(): string
    {
        do {
            $referenceNumber = 'REQ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (DocumentRequest::where('reference_number', $referenceNumber)->exists());

        return $referenceNumber;
    }
}
