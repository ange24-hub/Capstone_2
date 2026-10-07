<?php

namespace App\Http\Controllers;

use App\Models\DocumentPaymentHistory;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentPaymentController extends Controller
{
    public function assess(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        abort_unless($request->user()->barangay_id && $request->user()->barangay_id === $documentRequest->barangay_id, 403);
        $validated = $request->validate([
            'amount_due' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'payment_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($request, $documentRequest, $validated) {
            $documentRequest = DocumentRequest::lockForUpdate()->findOrFail($documentRequest->id);
            abort_unless($documentRequest->requiresPayment(), 422, 'Barangay Indigency is free.');
            abort_unless($documentRequest->paymentIsOpen(), 422, 'This request is already closed.');
            abort_if(in_array($documentRequest->payment_status, [DocumentRequest::PAYMENT_PAID, DocumentRequest::PAYMENT_PENDING], true), 422, 'A submitted or verified payment cannot be reassessed.');
            $documentRequest->update([
                'amount_due' => $validated['amount_due'], 'payment_status' => DocumentRequest::PAYMENT_UNPAID,
                'payment_remarks' => $validated['payment_remarks'] ?? null,
                'status' => DocumentRequest::STATUS_PROCESSING,
                'reviewed_by' => $request->user()->id, 'processed_at' => now(),
            ]);

            return back()->with('status', 'Fee set for '.$documentRequest->reference_number.'. The resident can now see the amount due and choose GCash or in-person payment.');
        });
    }

    public function inPerson(Request $request, DocumentRequest $documentRequest): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()->id === $documentRequest->resident_id, 403);
        if ($request->routeIs('api.resident.*')) {
            abort_unless((int) $request->user()->barangay_id === (int) $documentRequest->barangay_id, 403);
        }

        return DB::transaction(function () use ($request, $documentRequest) {
            $documentRequest = DocumentRequest::lockForUpdate()->findOrFail($documentRequest->id);
            $this->ensurePaymentCanBeSubmitted($documentRequest);
            $documentRequest->update(['payment_method' => DocumentRequest::PAYMENT_METHOD_IN_PERSON, 'payment_status' => DocumentRequest::PAYMENT_UNPAID]);
            $message = 'In-person payment selected. Pay '.$documentRequest->amountDueLabel().' at the barangay office. The secretary must confirm receipt before release.';

            return $request->routeIs('api.resident.*') ? response()->json(['message' => $message]) : back()->with('status', $message);
        });
    }

    public function receiveInPerson(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        abort_unless($request->user()->barangay_id && $request->user()->barangay_id === $documentRequest->barangay_id, 403);
        $validated = $request->validate([
            'confirm_received' => ['required', 'accepted'],
            'payment_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($request, $documentRequest, $validated) {
            $documentRequest = DocumentRequest::lockForUpdate()->findOrFail($documentRequest->id);
            $this->ensurePaymentCanBeSubmitted($documentRequest);
            $documentRequest->update([
                'payment_method' => DocumentRequest::PAYMENT_METHOD_IN_PERSON,
                'payment_status' => DocumentRequest::PAYMENT_PAID, 'paid_at' => now(),
                'payment_reviewed_by' => $request->user()->id, 'payment_reviewed_at' => now(),
                'payment_transaction_at' => now(), 'payment_reference' => null,
                'payment_remarks' => $validated['payment_remarks'] ?? 'Payment received in person at the barangay office.',
            ]);

            DocumentPaymentHistory::record($documentRequest, 'received', $request->user()->id);

            return back()->with('status', 'In-person payment received for '.$documentRequest->reference_number.'. The document can now be marked ready for release.');
        });
    }

    private function ensurePaymentCanBeSubmitted(DocumentRequest $documentRequest): void
    {
        abort_unless($documentRequest->requiresPayment() && $documentRequest->hasAssessedFee(), 422, 'Wait for the secretary to set the fee first.');
        abort_unless($documentRequest->paymentIsOpen(), 422, 'This request is already closed.');
        abort_if(in_array($documentRequest->payment_status, [DocumentRequest::PAYMENT_PAID, DocumentRequest::PAYMENT_PENDING], true), 422, 'This payment is already submitted or verified.');
    }

    public function submit(Request $request, DocumentRequest $documentRequest): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        return DB::transaction(function () use ($request, $documentRequest) {
            $documentRequest = DocumentRequest::lockForUpdate()->findOrFail($documentRequest->id);
            abort_unless($request->user()->id === $documentRequest->resident_id, 403);
            if ($request->routeIs('api.resident.*')) {
                abort_unless((int) $request->user()->barangay_id === (int) $documentRequest->barangay_id, 403);
            }
            $this->ensurePaymentCanBeSubmitted($documentRequest);
            abort_unless($documentRequest->barangay?->gcashIsReady(), 503, 'GCash payment is not yet activated by this Barangay.');

            $validated = $request->validate([
                'payer_name' => ['required', 'string', 'max:100'],
                'payer_mobile' => ['required', 'regex:/^09\d{9}$/'],
                'payment_reference' => [
                    'required',
                    'digits:13',
                    Rule::unique('document_requests', 'payment_reference')->ignore($documentRequest->id),
                ],
                'payment_transaction_at' => ['required', 'date', 'before_or_equal:now'],
                'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            ], [
                'payer_mobile.regex' => 'Enter an 11-digit Philippine mobile number beginning with 09.',
                'payment_reference.digits' => 'Enter the 13-digit GCash transaction reference number.',
                'payment_reference.unique' => 'This GCash reference number has already been submitted.',
            ]);

            $proofPath = $request->file('payment_proof')->store("gcash-payment-proofs/{$documentRequest->id}", 'local');

            $documentRequest->update([
                'payment_method' => DocumentRequest::PAYMENT_METHOD_GCASH,
                'payment_status' => DocumentRequest::PAYMENT_PENDING,
                'payment_reference' => $validated['payment_reference'],
                'payment_transaction_at' => $validated['payment_transaction_at'],
                'payer_name' => $validated['payer_name'],
                'payer_mobile' => $validated['payer_mobile'],
                'payment_proof_path' => $proofPath,
                'payment_submitted_at' => now(),
                'paid_at' => null,
                'payment_reviewed_by' => null,
                'payment_reviewed_at' => null,
                'payment_remarks' => null,
            ]);
            DocumentPaymentHistory::record($documentRequest, 'submitted', $request->user()->id);
            if ($request->routeIs('api.resident.*')) {
                return response()->json(['message' => 'Payment submitted for verification. Do not pay again while verification is pending.']);
            }

            return back()->with('status', 'Your GCash payment was submitted for Barangay verification. Do not pay again while verification is pending.');
        });
    }

    public function verify(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        return DB::transaction(function () use ($request, $documentRequest) {
            $documentRequest = DocumentRequest::lockForUpdate()->findOrFail($documentRequest->id);
            abort_unless($request->user()->barangay_id, 403);
            abort_unless($request->user()->barangay_id === $documentRequest->barangay_id, 403);
            abort_unless($documentRequest->payment_status === DocumentRequest::PAYMENT_PENDING, 422, 'This payment is not awaiting verification.');
            abort_unless($documentRequest->paymentIsOpen() && $documentRequest->requiresPayment() && $documentRequest->hasAssessedFee(), 422, 'This request cannot accept payment.');
            abort_unless($documentRequest->payment_method === DocumentRequest::PAYMENT_METHOD_GCASH, 422);

            $validated = $request->validate([
                'decision' => ['required', Rule::in(['verify', 'reject'])],
                'payment_remarks' => ['nullable', 'string', 'max:1000', 'required_if:decision,reject'],
            ]);

            $verified = $validated['decision'] === 'verify';

            $documentRequest->update([
                'payment_status' => $verified ? DocumentRequest::PAYMENT_PAID : DocumentRequest::PAYMENT_REJECTED,
                'paid_at' => $verified ? now() : null,
                'payment_reviewed_by' => $request->user()->id,
                'payment_reviewed_at' => now(),
                'payment_remarks' => $validated['payment_remarks'] ?? null,
            ]);

            DocumentPaymentHistory::record($documentRequest, $verified ? 'verified' : 'rejected', $request->user()->id);

            return back()->with('status', $verified
                ? 'GCash payment '.$documentRequest->payment_reference.' was verified.'
                : 'The GCash payment was returned to the resident for correction.');
        });
    }

    public function proof(Request $request, DocumentRequest $documentRequest): StreamedResponse
    {
        $user = $request->user();
        $isResidentOwner = $user->hasRole(User::ROLE_RESIDENT) && $user->id === $documentRequest->resident_id;
        $isBarangayReviewer = $user->hasRole(User::ROLE_BARANGAY) && $user->barangay_id === $documentRequest->barangay_id;

        abort_unless($isResidentOwner || $isBarangayReviewer, 403);
        abort_unless($documentRequest->payment_proof_path, 404);
        abort_unless(Storage::disk('local')->exists($documentRequest->payment_proof_path), 404);

        return Storage::disk('local')->download(
            $documentRequest->payment_proof_path,
            'gcash-proof-'.$documentRequest->reference_number.'.'.pathinfo($documentRequest->payment_proof_path, PATHINFO_EXTENSION)
        );
    }
}
