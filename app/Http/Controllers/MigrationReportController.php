<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use App\Services\MigrationSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MigrationReportController extends Controller
{
    public function __invoke(Request $request, MigrationSummary $summary)
    {
        $request->validate([
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
            'year' => ['nullable', 'integer', 'between:1900,9999'],
        ]);
        $isSecretary = $request->user()->hasRole(User::ROLE_BARANGAY);
        if ($isSecretary) {
            abort_unless($request->user()->barangay_id, 403);
            abort_if($request->filled('barangay_id') && $request->integer('barangay_id') !== $request->user()->barangay_id, 403);
        }
        $selectedBarangayId = $isSecretary ? $request->user()->barangay_id : ($request->filled('barangay_id') ? $request->integer('barangay_id') : null);
        $selectedYear = $request->filled('year') ? $request->integer('year') : now()->year;
        $report = $summary->build($selectedBarangayId, $selectedYear);
        if ($request->routeIs('reports.migration.pdf')) {
            return Pdf::loadView('reports.migration-pdf', compact('report'))->setPaper('a4')
                ->download('migration-report-'.($selectedBarangayId ? 'barangay-'.$selectedBarangayId : 'municipal').'-'.$selectedYear.'.pdf');
        }
        $barangays = $isSecretary ? collect() : Barangay::orderBy('name')->get(['id', 'name']);

        return view('reports.migration', compact('report', 'selectedBarangayId', 'selectedYear', 'isSecretary', 'barangays'));
    }
}
