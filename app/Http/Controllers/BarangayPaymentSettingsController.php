<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BarangayPaymentSettingsController extends Controller
{
    public function update(Request $request, Barangay $barangay): RedirectResponse
    {
        $validated = $request->validate([
            'gcash_enabled' => ['nullable', 'boolean'],
            'gcash_merchant_name' => ['required_if:gcash_enabled,1', 'nullable', 'string', 'max:100'],
            'gcash_account_identifier' => ['required_if:gcash_enabled,1', 'nullable', 'string', 'max:100'],
            'gcash_qr' => [
                $request->boolean('gcash_enabled') && ! $barangay->gcash_qr_path ? 'required' : 'nullable',
                'image',
                'mimes:jpg,jpeg,png',
                'max:5120',
            ],
        ]);

        $enabled = $request->boolean('gcash_enabled');
        $profileChanged = $enabled !== $barangay->gcash_enabled
            || ($validated['gcash_merchant_name'] ?? null) !== $barangay->gcash_merchant_name
            || ($validated['gcash_account_identifier'] ?? null) !== $barangay->gcash_account_identifier
            || $request->hasFile('gcash_qr');

        $hasOpenPayments = DocumentRequest::where('barangay_id', $barangay->id)
            ->where('amount_due', '>', 0)
            ->whereIn('payment_status', [
                DocumentRequest::PAYMENT_UNPAID,
                DocumentRequest::PAYMENT_PENDING,
                DocumentRequest::PAYMENT_REJECTED,
            ])
            ->exists();

        if ($profileChanged && $hasOpenPayments) {
            return back()->withErrors([
                'gcash_merchant_name' => 'This GCash profile cannot be changed while the Barangay has unpaid or unverified document requests.',
            ]);
        }

        $qrPath = $barangay->gcash_qr_path;
        $previousQrPath = $qrPath;
        if ($request->hasFile('gcash_qr')) {
            $qrPath = $request->file('gcash_qr')->store("barangay-gcash-qr/{$barangay->id}", 'local');
        }

        $barangay->update([
            'gcash_enabled' => $enabled,
            'gcash_merchant_name' => $validated['gcash_merchant_name'] ?? $barangay->gcash_merchant_name,
            'gcash_account_identifier' => $validated['gcash_account_identifier'] ?? $barangay->gcash_account_identifier,
            'gcash_qr_path' => $qrPath,
            'gcash_approved_by' => $request->user()->id,
            'gcash_approved_at' => now(),
        ]);

        if ($previousQrPath && $qrPath !== $previousQrPath) {
            Storage::disk('local')->delete($previousQrPath);
        }

        return back()->with('status', 'The official GCash payment profile for Barangay '.$barangay->name.' was updated.');
    }

    public function qr(Request $request, Barangay $barangay): StreamedResponse
    {
        $user = $request->user();
        $canView = $user->hasRole(User::ROLE_MUNICIPAL_LGU)
            || ($user->hasAnyRole([User::ROLE_BARANGAY, User::ROLE_RESIDENT]) && $user->barangay_id === $barangay->id);

        abort_unless($canView, 403);
        abort_unless($barangay->gcash_qr_path, 404);
        abort_unless(Storage::disk('local')->exists($barangay->gcash_qr_path), 404);

        return Storage::disk('local')->response($barangay->gcash_qr_path, 'barangay-'.$barangay->id.'-gcash-qr.png', [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
