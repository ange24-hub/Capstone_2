<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Inhabitant;
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
            : now(config('migration_forecast.timezone', 'Asia/Manila'))->year;

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

        $history = app(\App\Services\RecordedMigrationHistory::class)->build($selectedBarangayId, $selectedYear);
        $records = $history['events'];
        $monthlyTrend = $history['months'];
        $barangayStats = $history['coverage']->filter(fn ($area) => $area['total'] > 0)->sortByDesc('total')->values();

        /*
        |--------------------------------------------------------------------------
        | Population count
        |--------------------------------------------------------------------------
        */

        $totalInhabitants = Inhabitant::withoutMigrationDemo()->where('status', Inhabitant::STATUS_ACTIVE)
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

            'totalIn' => $history['in'],
            'totalOut' => $history['out'],
            'inKnown' => $history['in_known'],
            'outKnown' => $history['out_known'],
            'duplicateEvents' => $history['duplicates'],

        ]);
    }
}
