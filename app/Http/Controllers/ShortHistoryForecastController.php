<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use App\Services\RbiShortHistorySource;
use App\Services\ShortHistoryForecast;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShortHistoryForecastController extends Controller
{
    public function __invoke(Request $request, RbiShortHistorySource $source, ShortHistoryForecast $engine): View
    {
        abort_unless($request->user()->isApproved() && $request->user()->hasRole(User::ROLE_MUNICIPAL_LGU), 403);
        $data = $request->validate(['barangay_id' => ['nullable', 'integer', 'exists:barangays,id'], 'end_month' => ['nullable', 'date_format:Y-m', 'before:'.now()->startOfMonth()->format('Y-m')], 'mode' => ['nullable', 'in:short,holdout,rolling'],
            'method' => ['nullable', 'in:selected,mean_rate,poisson_jeffreys'], 'horizon' => ['nullable', 'integer', 'in:1,3,6'],
            'training_months' => ['nullable', 'integer', 'in:6,12']]);
        $rolling = ($data['mode'] ?? 'short') === 'rolling';
        $secretary = $request->user()->hasRole(User::ROLE_BARANGAY);
        if ($secretary) {
            abort_if(isset($data['barangay_id']) && (int) $data['barangay_id'] !== $request->user()->barangay_id, 403);
            $area = $request->user()->barangay ?? abort(403);
        } else {
            $area = isset($data['barangay_id']) ? Barangay::findOrFail($data['barangay_id']) : (Barangay::where('name', $rolling ? 'Cabascan' : 'Cawayan')->first() ?? Barangay::orderBy('name')->firstOrFail());
        }
        $holdout = ($data['mode'] ?? 'short') === 'holdout';
        $method = $data['method'] ?? ($rolling ? 'poisson_jeffreys' : 'mean_rate');
        $horizon = (int) ($data['horizon'] ?? ($method !== 'selected' ? 6 : 1));
        $trainingMonths = (int) ($data['training_months'] ?? ($rolling ? 6 : 12));
        if ($rolling && $method === 'selected') {
            throw \Illuminate\Validation\ValidationException::withMessages(['method' => 'Rolling backtesting uses a fixed Mean-rate or Bayesian Poisson method.']);
        }
        if ($holdout && $method === 'selected' && ($horizon !== 1 || $trainingMonths !== 12)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['horizon' => 'Automatic selection needs twelve training months and one target. Choose Mean-rate baseline or Bayesian Poisson for other windows.']);
        }
        $firstMonth = $data['end_month'] ?? null;
        if ($rolling && $firstMonth === null) {
            if ($area->name === 'Cabascan') {
                $firstMonth = '2020-12';
            } else {
                $latest = $source->build($area);
                $firstMonth = $latest['rows'] ? \Carbon\Carbon::createFromFormat('!Y-m', end($latest['rows'])['month'])->subMonths($horizon - 1)->format('Y-m') : null;
            }
        }
        $history = $holdout || $rolling ? $source->forecastWindow($area, $firstMonth, $trainingMonths, $horizon) : $source->build($area, $firstMonth);
        $evaluation = $rolling ? app(\App\Services\RollingHistoryBacktest::class)->evaluate($history['rows'], $method, $trainingMonths, $horizon)
            : ($holdout ? app(\App\Services\TwelveMonthHoldoutForecast::class)->evaluate($history['rows'], $method, $horizon, $trainingMonths) : $engine->evaluate($history['rows']));

        return view($rolling ? 'dashboards.migration-rolling-experiment' : ($holdout ? 'dashboards.migration-holdout-experiment' : 'dashboards.migration-history-experiment'), ['barangay' => $area,
            'barangays' => $secretary ? collect([$area]) : Barangay::orderBy('name')->get(),
            'history' => $history, 'method' => $method, 'horizon' => $horizon, 'trainingMonths' => $trainingMonths,
            'evaluation' => $evaluation]);
    }
}
