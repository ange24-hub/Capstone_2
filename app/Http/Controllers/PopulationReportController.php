<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use App\Services\PopulationSummary;
use App\Services\PopulationSpreadsheet;
use App\Services\PopulationReportSubmissionSnapshot;
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
            'report_year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'semester' => ['nullable', Rule::in([1,2])],
        ]);
        $isSecretary = $request->user()->hasRole(User::ROLE_BARANGAY);
        if ($isSecretary) {
            abort_unless($request->user()->barangay_id, 403, 'Your account must be assigned to a barangay.');
            abort_if($request->filled('barangay_id') && $request->integer('barangay_id') !== (int) $request->user()->barangay_id, 403);
        }
        $barangayId = $isSecretary ? $request->user()->barangay_id : ($request->filled('barangay_id') ? $request->integer('barangay_id') : null);
        $selectedSection = $request->input('section') ?: 'summary';
        $printMode = $request->boolean('print');
        // Kung adunay original report sa gipiling period, mao kana ang historical source.
        // Dili ilisan ang historical counts sa current registry totals.
        if ($barangayId && $selectedSection === 'summary' && $request->filled('report_year') && $request->filled('semester')
            && \Illuminate\Support\Facades\Schema::hasTable('population_report_submissions')) {
            $archive = \App\Models\PopulationReportSubmission::where('barangay_id', $barangayId)
                ->where('report_year', $request->integer('report_year'))->where('semester', $request->integer('semester'))
                ->where('snapshot->kind', 'historical_population_photo')->orderByDesc('id')->first();
            if ($archive) return redirect()->route('reports.population.submissions.show', $archive);
        }
        $report = $summary->build($barangayId, $selectedSection);
        $reportFilters=array_filter(['barangay_id'=>$barangayId,'report_year'=>$request->integer('report_year',now()->year),
            'semester'=>$request->integer('semester',now()->month<=6?1:2)]);
        if($selectedSection==='summary') {
            $report['monitoring']['year']=$reportFilters['report_year'];
            $report['monitoring']['semester']=$reportFilters['semester'];
        }
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
                ->setPaper('a4', in_array($selectedSection, ['coverage', 'area-ages'], true) || ($report['totalBarangays'] > 1 && in_array($selectedSection, ['sex', 'ages'], true)) ? 'landscape' : 'portrait')
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
        $submissionPreviewToken = null;
        if ($isSecretary && $request->user()->isApproved() && $selectedSection === 'summary' && ! $printMode) {
            $previews = array_filter($request->session()->get('population_submission_previews', []), fn ($item) => $item['expires_at'] >= time());
            $previews = array_slice($previews, -4, null, true);
            $submissionPreviewToken = (string) Str::uuid();
            $previews[$submissionPreviewToken] = ['user_id' => $request->user()->id, 'barangay_id' => $barangayId,
                'expires_at' => time() + 3600, 'snapshot' => app(PopulationReportSubmissionSnapshot::class)->capture($report)];
            $request->session()->put('population_submission_previews', $previews);
        }
        return view('reports.population', [
            'submissionPreviewToken' => $submissionPreviewToken,
            'selectedSection' => $selectedSection, 'sectionLabels' => $sectionLabels, 'reportTitle' => $reportTitle,
            'detailGroups' => $detailGroups, 'detailPagination' => $detailPagination,
            'printMode' => $printMode,
            'showReportNotes' => ! $printMode,
            'reportFilters'=>$reportFilters,
            'report' => $report, 'selectedBarangayId' => $barangayId, 'isSecretary' => $isSecretary,
            'barangays' => $isSecretary ? collect() : Barangay::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
