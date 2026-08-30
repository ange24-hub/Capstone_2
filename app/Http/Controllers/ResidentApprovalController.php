<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResidentApprovalController extends Controller
{
    public function approve(Request $request, User $resident): RedirectResponse
    {
        $this->authorizeResident($request, $resident);

        $resident->update([
            'approval_status' => User::APPROVAL_APPROVED,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('status', $resident->name.' has been approved as a resident of Barangay '.$resident->barangay->name.'.');
    }

    public function reject(Request $request, User $resident): RedirectResponse
    {
        $this->authorizeResident($request, $resident);

        $resident->update([
            'approval_status' => User::APPROVAL_REJECTED,
            'approved_at' => null,
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('status', $resident->name.'\'s registration has been rejected.');
    }

    private function authorizeResident(Request $request, User $resident): void
    {
        abort_unless($resident->hasRole(User::ROLE_RESIDENT), 404);
        abort_unless($request->user()->barangay_id, 403);
        abort_unless($request->user()->barangay_id === $resident->barangay_id, 403);
    }
}
