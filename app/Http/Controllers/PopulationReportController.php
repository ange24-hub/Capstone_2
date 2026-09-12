<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use App\Services\PopulationSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PopulationReportController extends Controller
{
    public function __invoke(Request $request, PopulationSummary $summary)
    {
        $request->validate(['barangay_id' => ['nullable', 'integer', 'exists:barangays,id']]);
        $isSecretary = $request->user()->hasRole(User::ROLE_BARANGAY);
        if ($isSecretary) {
            abort_unless($request->user()->barangay_id, 403, 'Your account must be assigned to a barangay.');
            abort_if($request->filled('barangay_id') && $request->integer('barangay_id') !== $request->user()->barangay_id, 403);
        }
        $barangayId = $isSecretary ? $request->user()->barangay_id : ($request->filled('barangay_id') ? $request->integer('barangay_id') : null);
        $report = $summary->build($barangayId);
        if ($request->routeIs('reports.population.pdf')) {
            return Pdf::loadView('reports.population-pdf', compact('report'))->setPaper('a4')
                ->download('population-summary-'.($barangayId ? 'barangay-'.$barangayId : 'municipal').'-'.now()->format('Y-m-d').'.pdf');
        }
        return view('reports.population', [
            'report' => $report, 'selectedBarangayId' => $barangayId, 'isSecretary' => $isSecretary,
            'barangays' => $isSecretary ? collect() : Barangay::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
