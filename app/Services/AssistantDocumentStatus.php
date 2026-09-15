<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\User;

class AssistantDocumentStatus
{
    public function respond(User $user, string $question): ?array
    {
        $q = mb_strtolower($question);
        // Explanations belong to the guide, not the record lookup.
        if (preg_match('/\b(unsaon|explain|meaning|mean|pasabut|how to|how do)\b/u', $q)) return null;
        $document = preg_match('/\b(documents?|requests?|certificate|clearance|indigency|payment|gcash|bayad)\b/u', $q);
        $status = preg_match('/\b(pending|processing|ready|completed|rejected|unpaid|paid|verified|verification|status|bayad)\b/u', $q);
        if (! $document || ! $status) return null;
        if (! $user->hasAnyRole([User::ROLE_RESIDENT, User::ROLE_BARANGAY])) {
            return $this->answer('Document and payment status records are available to the requesting resident and their assigned barangay staff.');
        }
        if (! $user->isApproved() || ($user->hasRole(User::ROLE_BARANGAY) && ! $user->barangay_id)) {
            return $this->answer('An approved account is required to view document and payment status records. Barangay staff also need an assigned barangay.');
        }
        $resident = $user->hasRole(User::ROLE_RESIDENT);
        $query = DocumentRequest::query()->when($resident,
            fn ($query) => $query->where('resident_id', $user->id),
            fn ($query) => $query->where('barangay_id', $user->barangay_id));
        $payment = preg_match('/\b(payment|gcash|bayad|paid|unpaid)\b/u', $q);
        $labels = $payment ? DocumentRequest::paymentStatusLabels() : DocumentRequest::statusLabels();
        $column = $payment ? 'payment_status' : 'status';
        $terms = $payment
            ? ['not_required' => 'no payment required|not required', 'unpaid' => 'unpaid|wala pa.*bayad', 'pending_verification' => 'pending|verification', 'paid' => 'paid|verified', 'rejected' => 'rejected']
            : ['pending' => 'pending', 'processing' => 'processing', 'ready' => 'ready', 'completed' => 'completed', 'rejected' => 'rejected'];
        $filters = array_keys(array_filter($terms, fn ($pattern) => preg_match('/\b('.$pattern.')\b/u', $q)));
        // Questions about one's request report its actual latest status, rather than
        // filtering away a request which is not yet ready/paid.
        $latest = $resident && ! preg_match('/\b(pila|how many|count|list|show)\b/u', $q);
        if ($latest) {
            $request = $query->orderByDesc('created_at')->orderByDesc('id')->first(['reference_number', 'document_type', 'status', 'payment_status', 'amount_due']);
            $reply = $request
                ? 'Your latest request '.$request->reference_number.' ('.$request->typeLabel().') is '.$request->statusLabel().'. Payment: '.($request->requiresPayment() ? $request->paymentStatusLabel().' (PHP '.number_format((float) $request->amount_due, 2).')' : 'No payment required').'. Check My Requests if you mean another request.'
                : 'You have no document requests recorded.';
            return $this->answer($reply, $user);
        }
        if ($filters) $query->whereIn($column, $filters);
        $counts = $query->selectRaw($column.', COUNT(*) as total')->groupBy($column)->pluck('total', $column);
        $selected = $filters ? array_intersect_key($labels, array_flip($filters)) : $labels;
        $reply = ($resident ? 'Your document requests' : 'Document requests for your assigned barangay')." (all recorded dates):\n";
        foreach ($selected as $value => $label) $reply .= $label.': '.(int) ($counts[$value] ?? 0)."\n";
        $reply .= $payment ? 'Payment verification and document release are separate statuses.' : 'These counts describe request status, not payment verification.';
        return $this->answer($reply, $user);
    }

    private function answer(string $reply, ?User $user = null): array
    {
        return ['reply' => $reply, 'scope' => 'RBIM system only',
            'suggestions' => ['Pila ang pending document requests?', 'Show payment status counts', 'What do request statuses mean?'],
            'actions' => $user ? [['label' => 'Open Document Requests', 'url' => route($user->hasRole(User::ROLE_RESIDENT) ? 'resident.document-requests.index' : 'barangay.document-requests.index')]] : []];
    }
}
