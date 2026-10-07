<?php

namespace App\Http\Controllers;

use App\Models\DocumentPaymentHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentPaymentHistoryController extends Controller
{
    public function index(Request $request)
    {
        $secretary = $request->user()->hasRole(User::ROLE_BARANGAY);
        $histories = DocumentPaymentHistory::with(['documentRequest.user', 'actor'])
            ->whereHas('documentRequest', function ($query) use ($request, $secretary) {
                if ($secretary) {
                    $query->where('barangay_id', $request->user()->barangay_id ?? 0);
                } else {
                    $query->where('resident_id', $request->user()->id);
                }
            })->orderByDesc('occurred_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('payments.history', compact('histories', 'secretary'));
    }

    public function proof(Request $request, DocumentPaymentHistory $paymentHistory)
    {
        $document = $paymentHistory->documentRequest;
        $user = $request->user();
        abort_unless(($user->hasRole(User::ROLE_RESIDENT) && $user->id === $document->resident_id)
            || ($user->hasRole(User::ROLE_BARANGAY) && $user->barangay_id && $user->barangay_id === $document->barangay_id), 403);
        $path = $paymentHistory->snapshot['payment_proof_path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'payment-proof-'.$paymentHistory->id.'.'.pathinfo($path, PATHINFO_EXTENSION));
    }
}
