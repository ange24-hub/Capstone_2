<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\User;
use App\Services\MigrationPredictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MigrationDashboardController extends Controller
{
    public function __invoke(Request $request, MigrationPredictionService $predictionService): View
    {
        $request->validate([
            'year' => ['nullable', 'integer', 'between:1900,9999'],
            'barangay_id' => ['nullable', 'integer'],
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

        // Use completed calendar months, anchored to the selected reporting year.
        $currentMonth = now()->startOfMonth();
        $anchor = $selectedYear < $currentMonth->year
            ? Carbon::create($selectedYear, 12, 1)->startOfDay()
            : $currentMonth->copy()->subMonth();
        $predictionMonthLabel = $anchor->copy()->addMonth()->format('F Y');
        $sourceMonthLabel = $anchor->format('F Y');
        $previousMonthLabel = $anchor->copy()->subMonth()->format('F Y');
        $history = MigrationRecord::query()
            ->when($selectedBarangayId, fn ($query) => $query->where('barangay_id', $selectedBarangayId))
            ->whereBetween('movement_date', [
                $anchor->copy()->subMonths(3)->toDateString(),
                $anchor->copy()->endOfMonth()->toDateString(),
            ])->get();
        $outByMonth = $history->where('type', MigrationRecord::TYPE_OUT)
            ->groupBy(fn (MigrationRecord $record) => $record->movement_date->format('Y-m'))
            ->map->count();
        $countAt = fn (int $offset): int => (int) $outByMonth->get(
            $anchor->copy()->subMonths($offset)->format('Y-m'), 0
        );
        $currentMonthOut = $countAt(0);
        $previousMonthOut = $countAt(1);
        $threeMonthAverage = round(($countAt(0) + $countAt(1) + $countAt(2)) / 3, 2);
        $predictedOutMigration = null;
        $predictionMethod = null;
        $predictionError = null;

        if ($selectedYear > $currentMonth->year) {
            $predictionError = 'Choose the current year or a past year to estimate migration.';
        } elseif ($history->filter(fn ($record) => $record->movement_date->gte($anchor->copy()->subMonths(2)))->isEmpty()) {
            $predictionError = 'No migration events recorded in the three completed source months. Add dated movement records to view an estimate.';
        } else {
            $predictedOutMigration = $predictionService->predictOutMigration([
                'out_migration_count' => $currentMonthOut,
                'previous_month_out' => $previousMonthOut,
                'out_3month_avg' => round(($countAt(1) + $countAt(2) + $countAt(3)) / 3, 2),
                'year' => $anchor->year,
                'month' => $anchor->month,
            ]);
            $predictionMethod = $predictedOutMigration === null ? '3-month moving average' : 'Gradient Boosting prototype';
            $predictedOutMigration ??= $threeMonthAverage;
        }

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

            /*
             * ML prediction data
             */

            'currentMonthOut' => $currentMonthOut,

            'previousMonthOut' => $previousMonthOut,

            'threeMonthAverage' => $threeMonthAverage,

            'predictedOutMigration' => $predictedOutMigration,

            'predictionError' => $predictionError,
            'predictionMethod' => $predictionMethod,
            'predictionMonthLabel' => $predictionMonthLabel,
            'sourceMonthLabel' => $sourceMonthLabel,
            'previousMonthLabel' => $previousMonthLabel,
            'demoRecordCount' => $records->merge($history)->where('reason', 'RBIM migration demo v1')->count(),
        ]);
    }
}
