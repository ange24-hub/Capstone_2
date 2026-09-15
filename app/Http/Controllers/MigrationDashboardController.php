<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class MigrationDashboardController extends Controller
{
    public function __invoke(Request $request): View
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
            fn (MigrationRecord $record): int =>
                $record->movement_date->month
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

        /*
        |--------------------------------------------------------------------------
        | Out-Migration prediction
        |
        | The Python model expects:
        | - out_migration_count
        | - previous_month_out
        | - out_3month_avg
        | - year
        | - month
        |--------------------------------------------------------------------------
        */

        $predictionMonth = now()->month;

        /*
         * Get the latest available migration data before the prediction.
         *
         * We intentionally retrieve the last 4 months so that the model
         * can calculate:
         *   - current/latest out-migration
         *   - previous month
         *   - three-month average
         */

        $predictionRecords = MigrationRecord::query()
            ->when(
                $selectedBarangayId,
                fn ($query) => $query->where(
                    'barangay_id',
                    $selectedBarangayId
                )
            )
            ->where('movement_date', '<=', now())
            ->where(
                'movement_date',
                '>=',
                now()->copy()->subMonths(4)->startOfMonth()
            )
            ->get();

        $outByMonth = $predictionRecords
            ->groupBy(
                fn (MigrationRecord $record): string =>
                    $record->movement_date->format('Y-m')
            )
            ->map(
                fn ($items) =>
                    $items
                        ->where('type', MigrationRecord::TYPE_OUT)
                        ->count()
            );

        $currentMonthKey = now()->format('Y-m');
        $previousMonthKey = now()->copy()->subMonth()->format('Y-m');

        $currentMonthOut = (int) (
            $outByMonth->get($currentMonthKey, 0)
        );

        $previousMonthOut = (int) (
            $outByMonth->get($previousMonthKey, 0)
        );

        $threeMonthValues = collect([
            now()->copy()->subMonths(3)->format('Y-m'),
            now()->copy()->subMonths(2)->format('Y-m'),
            now()->copy()->subMonth()->format('Y-m'),
        ])->map(
            fn (string $month) =>
                (int) $outByMonth->get($month, 0)
        );

        $threeMonthAverage = round(
            $threeMonthValues->avg(),
            2
        );

        $predictedOutMigration = null;
        $predictionError = null;

        /*
         * Call the local Python ML service.
         */

        try {
            $response = Http::timeout(5)
                ->post(
                    'http://127.0.0.1:5001/predict',
                    [
                        'out_migration_count' => $currentMonthOut,
                        'previous_month_out' => $previousMonthOut,
                        'out_3month_avg' => $threeMonthAverage,
                        'year' => now()->year,
                        'month' => $predictionMonth,
                    ]
                );

            if ($response->successful()) {
                $predictedOutMigration = $response->json(
                    'predicted_next_month_out_migration'
                );

                if ($predictedOutMigration !== null) {
                    $predictedOutMigration = max(
                        0,
                        round((float) $predictedOutMigration, 2)
                    );
                }
            } else {
                $predictionError = 'Migration prediction service returned an error.';
            }
        } catch (ConnectionException) {
            $predictionError = 'Migration prediction service is unavailable.';
        } catch (\Throwable $exception) {
            $predictionError = 'Unable to retrieve migration prediction.';
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
        ]);
    }
}