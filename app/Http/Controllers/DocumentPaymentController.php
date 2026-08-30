<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentPaymentController extends Controller
{
    public function submit(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        abort_unless($request->user()->id === $documentRequest->resident_id, 403);
        abort_unless($documentRequest->requiresPayment(), 422, 'This request does not require payment.');
        abort_if($documentRequest->payment_status === DocumentRequest::PAYMENT_PAID, 422, 'This payment is already verified.');
        abort_if($documentRequest->payment_status === DocumentRequest::PAYMENT_PENDING, 422, 'This payment is already awaiting verification.');
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

        if ($documentRequest->payment_proof_path) {
            Storage::disk('local')->delete($documentRequest->payment_proof_path);
        }

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

        return back()->with('status', 'Your GCash payment was submitted for Barangay verification. Do not pay again while verification is pending.');
    }

    public function verify(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        abort_unless($request->user()->barangay_id, 403);
        abort_unless($request->user()->barangay_id === $documentRequest->barangay_id, 403);
        abort_unless($documentRequest->payment_status === DocumentRequest::PAYMENT_PENDING, 422, 'This payment is not awaiting verification.');

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

        return back()->with('status', $verified
            ? 'GCash payment '.$documentRequest->payment_reference.' was verified.'
            : 'The GCash payment was returned to the resident for correction.');
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
