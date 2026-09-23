<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use App\Services\PopulationSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class PopulationReportController extends Controller
{
    public function __invoke(Request $request, PopulationSummary $summary)
    {
        $request->validate([
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
            'section' => ['nullable', Rule::in(array_keys(PopulationSummary::SECTIONS))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $isSecretary = $request->user()->hasRole(User::ROLE_BARANGAY);
        if ($isSecretary) {
            abort_unless($request->user()->barangay_id, 403, 'Your account must be assigned to a barangay.');
            abort_if($request->filled('barangay_id') && $request->integer('barangay_id') !== $request->user()->barangay_id, 403);
        }
        $barangayId = $isSecretary ? $request->user()->barangay_id : ($request->filled('barangay_id') ? $request->integer('barangay_id') : null);
        $selectedSection = $request->input('section') ?: 'summary';
        $report = $summary->build($barangayId, $selectedSection);
        $sectionLabels = PopulationSummary::SECTIONS;
        $reportTitle = $selectedSection === 'summary' ? 'Population Summary' : $sectionLabels[$selectedSection];
        $detailGroups = $report['detailGroups'];
        if ($request->routeIs('reports.population.pdf')) {
            return Pdf::loadView('reports.population-pdf', compact('report', 'selectedSection', 'reportTitle', 'detailGroups'))->setPaper('a4')
                ->download('population-'.$selectedSection.'-'.($barangayId ? 'barangay-'.$barangayId : 'municipal').'-'.now()->format('Y-m-d').'.pdf');
        }
        $detailPagination = null;
        if ($detailGroups->isNotEmpty()) {
            $grouped = in_array($selectedSection, ['families', 'households'], true);
            $items = $grouped ? $detailGroups : $detailGroups->first()['members'];
            $perPage = $grouped ? 15 : 50;
            $page = $request->integer('page', 1);
            $detailPagination = new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page,
                ['path' => $request->url(), 'query' => $request->except('page')]);
            $detailGroups = $grouped ? collect($detailPagination->items())
                : collect([array_merge($detailGroups->first(), ['members' => collect($detailPagination->items())])]);
        }
        return view('reports.population', [
            'selectedSection' => $selectedSection, 'sectionLabels' => $sectionLabels, 'reportTitle' => $reportTitle,
            'detailGroups' => $detailGroups, 'detailPagination' => $detailPagination,
            'report' => $report, 'selectedBarangayId' => $barangayId, 'isSecretary' => $isSecretary,
            'barangays' => $isSecretary ? collect() : Barangay::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
