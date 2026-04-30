<?php

namespace App\Http\Controllers;

use App\Models\BarangayRbiUpdate;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return match ($request->user()->role) {
            User::ROLE_MUNICIPAL_LGU => redirect()->route('dashboard.municipal'),
            User::ROLE_BARANGAY => redirect()->route('dashboard.barangay'),
            default => redirect()->route('dashboard.resident'),
        };
    }

    public function municipal(): View
    {
        return view('dashboards.municipal', [
            'rbiUpdates' => BarangayRbiUpdate::with('barangayUser')
                ->where('status', BarangayRbiUpdate::STATUS_SUBMITTED)
                ->latest('submitted_at')
                ->get(),
        ]);
    }

    public function barangay(): View
    {
        $rbiUpdates = BarangayRbiUpdate::where('barangay_user_id', auth()->id())
            ->latest()
            ->get();

        return view('dashboards.barangay', [
            'rbiUpdates' => $rbiUpdates,
            'draftRbiUpdate' => $rbiUpdates->firstWhere('status', BarangayRbiUpdate::STATUS_DRAFT),
            'rbiRowFields' => BarangayRbiUpdate::rowFields(),
        ]);
    }

    public function resident(): View
    {
        return view('dashboards.resident', [
            'documentTypes' => DocumentRequest::typeLabels(),
            'documentRequests' => auth()->user()->documentRequests()->latest()->get(),
        ]);
    }
}
