<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use App\Services\MunicipalRbiReportHistory;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class MunicipalPredictionController extends Controller
{
    public function reports(Request $request, MunicipalRbiReportHistory $source): View
    {
        $this->authorizeAdmin($request);
        $history = $source->build();

        return view('dashboards.municipal-rbi-history', compact('history'));
    }

    public function history(Request $request, Barangay $barangay, MunicipalRbiReportHistory $source): View
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $inventory = $source->build();
        $dataset = $inventory['datasets'][$barangay->id];
        $month = $data['month'] ?? ($dataset['months'] ? end($dataset['months']) : null);
        $entries = collect($dataset['entries'][$month] ?? []);
        $page = max(1, $request->integer('page', 1));
        $entries = new LengthAwarePaginator($entries->forPage($page, 20)->values(), $entries->count(), 20, $page, ['path' => $request->url()]);
        $entries->appends($request->except('page'));

        return view('dashboards.municipal-prediction-history', compact('barangay', 'dataset', 'month', 'entries'));
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isApproved() && $request->user()->hasRole(User::ROLE_MUNICIPAL_LGU), 403);
    }
}
