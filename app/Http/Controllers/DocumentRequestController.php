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
        $validated = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(DocumentRequest::typeLabels()))],
            'purpose' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->documentRequests()->create([
            'reference_number' => $this->makeReferenceNumber(),
            'document_type' => $validated['document_type'],
            'purpose' => $validated['purpose'],
            'status' => DocumentRequest::STATUS_PENDING,
        ]);

        return back()->with('status', 'Your form request has been submitted.');
    }

    private function makeReferenceNumber(): string
    {
        do {
            $referenceNumber = 'REQ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (DocumentRequest::where('reference_number', $referenceNumber)->exists());

        return $referenceNumber;
    }
}
