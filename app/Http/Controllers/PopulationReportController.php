<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use App\Services\PopulationSummary;
use App\Services\PopulationSpreadsheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class PopulationReportController extends Controller
{
    public function __invoke(Request $request, PopulationSummary $summary, PopulationSpreadsheet $spreadsheet)
    {
        $request->validate([
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
            'section' => ['nullable', Rule::in(array_keys(PopulationSummary::SECTIONS))],
            'page' => ['nullable', 'integer', 'min:1'],
            'print' => ['nullable', 'boolean'],
        ]);
        $isSecretary = $request->user()->hasRole(User::ROLE_BARANGAY);
        if ($isSecretary) {
            abort_unless($request->user()->barangay_id, 403, 'Your account must be assigned to a barangay.');
            abort_if($request->filled('barangay_id') && $request->integer('barangay_id') !== $request->user()->barangay_id, 403);
        }
        $barangayId = $isSecretary ? $request->user()->barangay_id : ($request->filled('barangay_id') ? $request->integer('barangay_id') : null);
        $selectedSection = $request->input('section') ?: 'summary';
        $printMode = $request->boolean('print');
        $report = $summary->build($barangayId, $selectedSection);
        $sectionLabels = PopulationSummary::SECTIONS;
        $reportTitle = $selectedSection === 'summary' ? 'Population Summary' : $sectionLabels[$selectedSection];
        $detailGroups = $report['detailGroups'];
        if ($request->routeIs('reports.population.excel')) {
            $filename = 'population-'.$selectedSection.'-'.($barangayId ? 'barangay-'.$barangayId : 'municipal').'-'.now()->format('Y-m-d').'.xlsx';

            return response($spreadsheet->build($report, $selectedSection, $reportTitle), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'private, no-store',
            ]);
        }
        if ($request->routeIs('reports.population.pdf')) {
            return Pdf::loadView('reports.population-pdf', compact('report', 'selectedSection', 'reportTitle', 'detailGroups'))
                ->setPaper('a4', in_array($selectedSection, ['coverage', 'area-ages'], true) || ($report['totalBarangays'] > 1 && in_array($selectedSection, ['summary', 'sex', 'ages'], true)) ? 'landscape' : 'portrait')
                ->download('population-'.$selectedSection.'-'.($barangayId ? 'barangay-'.$barangayId : 'municipal').'-'.now()->format('Y-m-d').'.pdf');
        }
        $detailPagination = null;
        if ($detailGroups->isNotEmpty() && ! $printMode) {
            $grouped = in_array($selectedSection, ['families', 'households'], true);
            $items = $grouped ? $detailGroups : $detailGroups->flatMap(fn ($group) => $group['members'])->values();
            $perPage = $grouped ? 15 : 50;
            $page = $request->integer('page', 1);
            $detailPagination = new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page,
                ['path' => $request->url(), 'query' => $request->except('page')]);
            $detailGroups = $grouped ? collect($detailPagination->items())
                : collect($detailPagination->items())->groupBy('barangay_id')->map(fn ($members) => [
                    'barangay' => $members->first()['barangay'], 'members' => $members,
                ])->values();
        }
        return view('reports.population', [
            'selectedSection' => $selectedSection, 'sectionLabels' => $sectionLabels, 'reportTitle' => $reportTitle,
            'detailGroups' => $detailGroups, 'detailPagination' => $detailPagination,
            'printMode' => $printMode,
            'showReportNotes' => ! $printMode,
            'report' => $report, 'selectedBarangayId' => $barangayId, 'isSecretary' => $isSecretary,
            'barangays' => $isSecretary ? collect() : Barangay::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
