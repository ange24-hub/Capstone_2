<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use App\Services\DatedRbiHistorySample;
use App\Services\MunicipalRbiReportHistory;
use App\Services\RollingHistoryBacktest;
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

    public function __invoke(Request $request, MunicipalRbiReportHistory $source, RollingHistoryBacktest $engine): View
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['test_end' => ['nullable', 'date_format:Y-m', 'before:'.now()->startOfMonth()->format('Y-m')],
            'method' => ['nullable', 'in:mean_rate,poisson_jeffreys'], 'basis' => ['nullable', 'in:confirmed,age_estimates'],
            'scope' => ['nullable', 'in:dated,municipal']]);
        $scope = $data['scope'] ?? 'dated';
        $history = $source->build(($data['basis'] ?? ($scope === 'dated' ? 'age_estimates' : 'confirmed')) === 'age_estimates');
        $sample = app(DatedRbiHistorySample::class)->build($history);
        $trainingRows = $scope === 'dated' ? $sample['rows'] : $history['rows'];
        $end = $data['test_end'] ?? (collect($trainingRows)->last()['month'] ?? null);
        $window = [];
        foreach (array_reverse(array_values(array_filter($trainingRows, fn ($row) => $end && $row['month'] <= $end))) as $row) {
            if (! $row['complete']) {
                break;
            }
            array_unshift($window, $row);
        }
        if ($window && end($window)['month'] !== $end) {
            $window = [];
        }
        $method = $data['method'] ?? 'poisson_jeffreys';
        $evaluation = count($window) >= 12 ? $engine->evaluate($window, $method, count($window) - 6, 6)
            : ['ready' => false, 'required_months' => 12, 'available_months' => count($window)];

        return view('dashboards.municipal-prediction', compact('history', 'sample', 'scope', 'evaluation', 'method', 'end', 'window'));
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
