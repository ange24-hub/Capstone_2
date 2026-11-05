<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\User;
use App\Services\MigrationForecastHistory;
use App\Services\MigrationForecastReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MigrationDashboardController extends Controller
{
    public function __invoke(Request $request, MigrationForecastReport $forecastReport): View
    {
        $request->validate([
            'year' => ['nullable', 'integer', 'between:1900,9999'],
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
        ]);

        $selectedYear = $request->filled('year')
            ? $request->integer('year')
            : now()->year;

        $isBarangaySecretary = $request->user()->hasRole(User::ROLE_BARANGAY);

        if ($isBarangaySecretary) {
            abort_unless(
                $request->user()->barangay_id,
                403,
                'Your secretary account is not assigned to a barangay.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Barangay scope
        |--------------------------------------------------------------------------
        */

        $selectedBarangayId = $isBarangaySecretary
            ? $request->user()->barangay_id
            : ($request->filled('barangay_id')
                ? $request->integer('barangay_id')
                : null);

        /*
        |--------------------------------------------------------------------------
        | Records for selected reporting year
        |--------------------------------------------------------------------------
        */

        $records = MigrationRecord::with([
            'barangay',
            'inhabitant.household',
        ])
            ->whereYear('movement_date', $selectedYear)
            ->when(
                $selectedBarangayId,
                fn ($query) => $query->where(
                    'barangay_id',
                    $selectedBarangayId
                )
            )
            ->latest('movement_date')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Barangay statistics
        |--------------------------------------------------------------------------
        */

        $barangayStats = $records
            ->groupBy('barangay_id')
            ->map(function ($items) {
                $in = $items
                    ->where('type', MigrationRecord::TYPE_IN)
                    ->count();

                $out = $items
                    ->where('type', MigrationRecord::TYPE_OUT)
                    ->count();

                return [
                    'barangay' => $items->first()->barangay,
                    'in' => $in,
                    'out' => $out,
                    'net' => $in - $out,
                    'total' => $items->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Monthly migration trend
        |--------------------------------------------------------------------------
        */

        $recordsByMonth = $records->groupBy(
            fn (MigrationRecord $record): int => $record->movement_date->month
        );

        $monthlyTrend = collect(range(1, 12))
            ->map(function (int $month) use (
                $recordsByMonth,
                $selectedYear
            ): array {
                $items = $recordsByMonth->get(
                    $month,
                    collect()
                );

                return [
                    'month' => sprintf(
                        '%04d-%02d',
                        $selectedYear,
                        $month
                    ),
                    'in' => $items
                        ->where('type', MigrationRecord::TYPE_IN)
                        ->count(),
                    'out' => $items
                        ->where('type', MigrationRecord::TYPE_OUT)
                        ->count(),
                ];
            });

        $forecast = $forecastReport->build($selectedBarangayId, $selectedYear);

        /*
        |--------------------------------------------------------------------------
        | Population count
        |--------------------------------------------------------------------------
        */

        $totalInhabitants = Inhabitant::query()
            ->when(
                $selectedBarangayId,
                fn ($query) => $query->where(
                    'barangay_id',
                    $selectedBarangayId
                )
            )
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Return dashboard
        |--------------------------------------------------------------------------
        */

        return view('dashboards.migration', [
            'selectedYear' => $selectedYear,

            'selectedBarangayId' => $selectedBarangayId,

            'barangays' => $isBarangaySecretary
                ? Barangay::whereKey(
                    $request->user()->barangay_id
                )->get()
                : Barangay::orderBy('name')->get(),

            'records' => $records->take(12),

            'barangayStats' => $barangayStats,

            'monthlyTrend' => $monthlyTrend,

            'totalInhabitants' => $totalInhabitants,

            'totalIn' => $records
                ->where('type', MigrationRecord::TYPE_IN)
                ->count(),

            'totalOut' => $records
                ->where('type', MigrationRecord::TYPE_OUT)
                ->count(),

            ...$forecast,
            'demoRecordCount' => $records->where('reason', MigrationForecastHistory::DEMO_MARKER)->count(),
        ]);
    }
}
