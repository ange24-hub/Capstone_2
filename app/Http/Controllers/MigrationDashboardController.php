<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MigrationDashboardController extends Controller
{
    public function __invoke(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        if ($request->input('section') === 'forecast') {
            abort_unless($request->user()->isApproved(), 403);
            return redirect()->route('prediction.dashboard', $request->only('barangay_id'));
        }
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

        $records = MigrationRecord::withoutDemo()->with([
            'barangay',
            'inhabitant.household',
        ])
            ->whereYear('movement_date', $selectedYear)
            ->where('type', MigrationRecord::TYPE_OUT)
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

        $arrivalHistory = app(\App\Services\RbiSavedArrivalHistory::class)->build($selectedBarangayId, \Illuminate\Support\Carbon::create($selectedYear, 12, 1), 12);
        $arrivalByMonth = collect($arrivalHistory['rows'])->keyBy('month');
        $outByArea = $records->groupBy('barangay_id');
        $barangayStats = collect($arrivalHistory['coverage'])->map(function ($area) use ($outByArea) {
            $out = $outByArea->get($area['id'], collect())->count();

            return ['barangay' => Barangay::find($area['id']), 'in' => $area['events'], 'out' => $out, 'net' => $area['events'] - $out, 'total' => $area['events'] + $out];
        })->filter(fn ($row) => $row['total'] > 0)->sortByDesc('total')->values();

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
                $selectedYear, $arrivalByMonth
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
                    'in' => $arrivalByMonth->get(sprintf('%04d-%02d', $selectedYear, $month))['count'] ?? 0,
                    'out' => $items
                        ->where('type', MigrationRecord::TYPE_OUT)
                        ->count(),
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Population count
        |--------------------------------------------------------------------------
        */

        $totalInhabitants = Inhabitant::withoutMigrationDemo()
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

            'analyticsInsights' => ! in_array($request->input('section'), ['forecast', 'events'], true)
                ? app(\App\Services\MigrationAnalyticsInsights::class)->build(
                    $selectedBarangayId, $selectedYear, $monthlyTrend
                ) : null,

            'totalInhabitants' => $totalInhabitants,

            'totalIn' => array_sum(array_column($arrivalHistory['rows'], 'count')),

            'totalOut' => $records
                ->where('type', MigrationRecord::TYPE_OUT)
                ->count(),

        ]);
    }
}
