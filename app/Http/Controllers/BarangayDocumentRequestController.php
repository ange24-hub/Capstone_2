<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BarangayDocumentRequestController extends Controller
{
    public function update(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        abort_unless($request->user()->barangay_id, 403);
        abort_unless($request->user()->barangay_id === $documentRequest->barangay_id, 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(DocumentRequest::statusLabels()))],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $needsVerifiedPayment = in_array($validated['status'], [
            DocumentRequest::STATUS_PROCESSING,
            DocumentRequest::STATUS_READY,
            DocumentRequest::STATUS_COMPLETED,
        ], true);

        if ($needsVerifiedPayment && ! $documentRequest->isPaid()) {
            return back()->withErrors([
                'status' => 'Verify the GCash payment before processing or releasing this document.',
            ]);
        }

        $documentRequest->update([
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
            'reviewed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        return back()->with('status', 'Document request '.$documentRequest->reference_number.' was updated to '.$documentRequest->statusLabel().'.');
    }
}
