<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SecretaryApprovalController extends Controller
{
    public function approve(Request $request, User $secretary): RedirectResponse
    {
        $this->authorizeSecretary($secretary);

        $secretary->update([
            'approval_status' => User::APPROVAL_APPROVED,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('status', $secretary->name.' has been approved as secretary of Barangay '.$secretary->barangay->name.'.');
    }

    public function reject(Request $request, User $secretary): RedirectResponse
    {
        $this->authorizeSecretary($secretary);

        $secretary->update([
            'approval_status' => User::APPROVAL_REJECTED,
            'approved_at' => null,
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('status', $secretary->name.'\'s secretary registration has been rejected.');
    }

    private function authorizeSecretary(User $secretary): void
    {
        abort_unless($secretary->hasRole(User::ROLE_BARANGAY), 404);
    }
}
