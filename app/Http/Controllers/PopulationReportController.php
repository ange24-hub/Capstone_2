<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use App\Services\PopulationSummary;
use App\Services\PopulationSpreadsheet;
use App\Services\PopulationReportSubmissionSnapshot;
use App\Services\PopulationReportPeriod;
use Illuminate\Support\Str;
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
            'report_year' => ['nullable', 'integer', 'min:1900', 'max:'.PopulationReportPeriod::maximumYear()],
            'semester' => ['nullable', Rule::in([1,2])],
        ]);
        $isSecretary = $request->user()->hasRole(User::ROLE_BARANGAY);
        if ($request->user()->hasRole(User::ROLE_MUNICIPAL_LGU)) {
            $period = ['report_year'=>($request->filled('report_year') ? $request->integer('report_year') : now()->year),
                'semester'=>($request->filled('semester') ? $request->integer('semester') : (now()->month <= 6 ? 1 : 2))];
            if ($request->routeIs('reports.population')) {
                return redirect()->route('reports.population.submissions.index', $period + $request->only('barangay_id'));
            }
            // A municipal export must resolve to an actually submitted snapshot;
            // never build a live barangay report through an alternate URL.
            abort_unless($request->input('section', 'summary') === 'summary' && $request->filled('barangay_id'), 404);
            $saved = \App\Models\PopulationReportSubmission::receivedByMunicipal()
                ->where('barangay_id', $request->integer('barangay_id'))->where($period)
                ->orderByDesc('submitted_at')->orderByDesc('id')->firstOrFail();
            return redirect()->route('reports.population.submissions.'.($request->routeIs('reports.population.pdf') ? 'pdf' : 'excel'), $saved);
        }
        if ($isSecretary) {
            abort_unless($request->user()->barangay_id, 403, 'Your account must be assigned to a barangay.');
            abort_if($request->filled('barangay_id') && $request->integer('barangay_id') !== (int) $request->user()->barangay_id, 403);
        }
        $barangayId = $isSecretary ? $request->user()->barangay_id : ($request->filled('barangay_id') ? $request->integer('barangay_id') : null);
        $selectedSection = $request->input('section') ?: 'summary';
        $printMode = $request->boolean('print');
        $reportFilters=array_filter(['barangay_id'=>$barangayId,'report_year'=>$request->integer('report_year',now()->year),
            'semester'=>$request->integer('semester',now()->month<=6?1:2)]);
        if ($selectedSection === 'summary' && app(PopulationReportPeriod::class)->usesHistory($reportFilters['report_year'], $reportFilters['semester'])) {
            $saved = $barangayId && \Illuminate\Support\Facades\Schema::hasTable('population_report_submissions')
                ? app(PopulationReportPeriod::class)->nearestSaved($barangayId, $reportFilters['report_year'], $reportFilters['semester']) : null;
            if ($saved) {
                $action = $request->routeIs('reports.population.pdf') ? 'pdf' : ($request->routeIs('reports.population.excel') ? 'excel' : 'show');
                $parameters = ['submission'=>$saved->id] + ($printMode && $action === 'show' ? ['print'=>1] : []);
                $redirect = redirect()->route('reports.population.submissions.'.$action, $parameters);
                if ($saved->report_year !== $reportFilters['report_year'] || $saved->semester !== $reportFilters['semester']) {
                    $redirect->with('population_period_notice', 'No exact saved report for '.($reportFilters['semester']===1?'1st':'2nd')
                        .' Semester '.$reportFilters['report_year'].'. Opened the nearest earlier report: '
                        .($saved->semester===1?'1st':'2nd').' Semester '.$saved->report_year.'. Its original period and counts are preserved.');
                }
                return $redirect;
            }
            return response()->view('reports.population-period-empty', [
                'reportFilters'=>$reportFilters, 'isSecretary'=>$isSecretary, 'areaId'=>$barangayId,
                'barangays'=>$isSecretary ? collect() : Barangay::orderBy('name')->get(['id','name']),
            ], $request->routeIs('reports.population') ? 200 : 404)->header('Cache-Control','private, no-store');
        }
        // New periods use the latest Consolidated data. Saved historical copies
        // are opened above without changing their actual reporting period.
        $report = $summary->build($barangayId, $selectedSection);
        if($selectedSection==='summary') {
            $report['monitoring']['year']=$reportFilters['report_year'];
            $report['monitoring']['semester']=$reportFilters['semester'];
        }
        // Keep stale-data validation tied to registry data, before displaying
        // the current user's remembered names and signatures in the preview.
        $submissionReport = $report;
        $usingSavedSignatures = false;
        if ($selectedSection === 'summary' && $isSecretary && $request->user()->isApproved()
            && \Illuminate\Support\Facades\Schema::hasTable('population_report_submissions')) {
            foreach (app(\App\Services\PopulationReportSignatures::class)->savedFor($request->user()) as $key => $signature) {
                $report['signatures'][$key] = array_intersect_key($signature, array_flip(['name', 'image', 'method']));
                $report['monitoring'][$key === 'prepared' ? 'preparedBy' : 'submittedBy'] = $signature['name'];
                $usingSavedSignatures = true;
            }
        }
        $sectionLabels = PopulationSummary::SECTIONS;
        $reportTitle = $selectedSection === 'summary' ? 'Population Summary' : $sectionLabels[$selectedSection];
        $detailGroups = $report['detailGroups'];
        if ($request->routeIs('reports.population.excel')) {
            $filename = 'population-'.$selectedSection.'-'.($barangayId ? 'barangay-'.$barangayId : 'municipal')
                .'-'.$reportFilters['report_year'].'-semester-'.$reportFilters['semester'].'-'.now()->format('Y-m-d').'.xlsx';

            return response($spreadsheet->build($report, $selectedSection, $reportTitle), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'private, no-store',
            ]);
        }
        if ($request->routeIs('reports.population.pdf')) {
            return Pdf::loadView('reports.population-pdf', compact('report', 'selectedSection', 'reportTitle', 'detailGroups'))
                ->setPaper('a4', in_array($selectedSection, ['coverage', 'area-ages'], true) || ($report['totalBarangays'] > 1 && in_array($selectedSection, ['sex', 'ages'], true)) ? 'landscape' : 'portrait')
                ->download('population-'.$selectedSection.'-'.($barangayId ? 'barangay-'.$barangayId : 'municipal')
                    .'-'.$reportFilters['report_year'].'-semester-'.$reportFilters['semester'].'-'.now()->format('Y-m-d').'.pdf')
                ->header('Cache-Control', 'private, no-store');
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
        $submissionPreviewToken = null;
        $periodSubmission = $selectedSection === 'summary' && $barangayId
            && \Illuminate\Support\Facades\Schema::hasTable('population_report_submissions')
            ? app(PopulationReportPeriod::class)->submitted($barangayId, $reportFilters['report_year'], $reportFilters['semester']) : null;
        if ($isSecretary && $request->user()->isApproved() && $selectedSection === 'summary' && ! $printMode && !$periodSubmission) {
            $previews = array_filter($request->session()->get('population_submission_previews', []), fn ($item) => $item['expires_at'] >= time());
            $previews = array_slice($previews, -4, null, true);
            $submissionPreviewToken = (string) Str::uuid();
            $previews[$submissionPreviewToken] = ['user_id' => $request->user()->id, 'barangay_id' => $barangayId,
                'expires_at' => time() + 3600, 'snapshot' => app(PopulationReportSubmissionSnapshot::class)->capture($submissionReport)];
            $request->session()->put('population_submission_previews', $previews);
        }
        return response()->view('reports.population', [
            'submissionPreviewToken' => $submissionPreviewToken,
            'periodSubmission' => $periodSubmission,
            'usingSavedSignatures' => $usingSavedSignatures,
            'selectedSection' => $selectedSection, 'sectionLabels' => $sectionLabels, 'reportTitle' => $reportTitle,
            'detailGroups' => $detailGroups, 'detailPagination' => $detailPagination,
            'printMode' => $printMode,
            'showReportNotes' => ! $printMode,
            'reportFilters'=>$reportFilters,
            'report' => $report, 'selectedBarangayId' => $barangayId, 'isSecretary' => $isSecretary,
            'barangays' => $isSecretary ? collect() : Barangay::orderBy('name')->get(['id', 'name']),
        ])->header('Cache-Control', 'private, no-store');
    }
}
