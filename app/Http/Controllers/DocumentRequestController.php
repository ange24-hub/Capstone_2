<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentRequestController extends Controller
{
    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()->barangay_id, 403, 'Your resident account is not assigned to a barangay.');

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(DocumentRequest::typeLabels()))],
            'purpose' => ['required', 'string', 'max:255'],
        ]);

        $isFree = $validated['document_type'] === DocumentRequest::TYPE_INDIGENCY;

        $documentRequest = $request->user()->documentRequests()->create([
            'barangay_id' => $request->user()->barangay_id,
            'reference_number' => $this->makeReferenceNumber(),
            'document_type' => $validated['document_type'],
            'purpose' => $validated['purpose'],
            'status' => DocumentRequest::STATUS_PENDING,
            'amount_due' => 0,
            'payment_method' => null,
            'payment_status' => $isFree ? DocumentRequest::PAYMENT_NOT_REQUIRED : DocumentRequest::PAYMENT_AWAITING_ASSESSMENT,
        ]);

        $message = 'Your request '.$documentRequest->reference_number.' was sent to Barangay '.$request->user()->barangay->name.'.';
        $message .= $isFree ? ' Barangay Indigency is free.'
            : ' The secretary will set the fee. You can then pay through GCash or in person before release.';

        if ($request->routeIs('api.resident.*')) {
            return response()->json(['message' => $message, 'id' => $documentRequest->id], 201);
        }

        return redirect()->route('resident.document-requests.index')->with('status', $message);
    }

    private function makeReferenceNumber(): string
    {
        do {
            $referenceNumber = 'REQ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (DocumentRequest::where('reference_number', $referenceNumber)->exists());

        return $referenceNumber;
    }
}
