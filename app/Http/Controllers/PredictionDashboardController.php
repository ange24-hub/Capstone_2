<?php

namespace App\Http\Controllers;

use App\Models\{Barangay, Inhabitant, MigrationLog, MigrationRecord, User};
use App\Services\{MigrationPredictionService, StatisticalMigrationLogSync};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PredictionDashboardController extends Controller
{
    // Dependency Injection: Laravel maoy mohimo sa local PHP service.
    public function __construct(private MigrationPredictionService $prediction) {}

    private function area(Request $request, ?string $defaultBarangay = null): Barangay
    {
        $user = $request->user();
        abort_unless($user && $user->isApproved() && $user->hasAnyRole([User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU]), 403);
        $request->validate(['barangay_id' => ['nullable', 'integer', 'exists:barangays,id']]);
        if ($user->hasRole(User::ROLE_BARANGAY)) {
            // Dili masulod ang laing barangay pinaagi sa pag-usab sa URL o POST body.
            abort_unless($user->barangay_id, 403);
            abort_if($request->filled('barangay_id') && $request->integer('barangay_id') !== (int) $user->barangay_id, 403);
            return Barangay::findOrFail($user->barangay_id);
        }
        if ($request->filled('barangay_id')) return Barangay::findOrFail($request->integer('barangay_id'));
        if ($user->barangay_id) return Barangay::findOrFail($user->barangay_id);
        if ($defaultBarangay) return Barangay::where('name', $defaultBarangay)->firstOrFail();
        return Barangay::orderBy('name')->firstOrFail();
    }

    private function ready(): void
    {
        abort_unless(Schema::hasTable('migration_logs') && Schema::hasTable('forecast_logs'), 503,
            'Statistical forecast storage is not ready. Run php artisan migration:prepare-statistical-storage.');
    }

    public function index(Request $request)
    {
        $forecastPage = $request->routeIs('prediction.migration') ? 'records' : 'population';
        $barangay = $this->area($request, $forecastPage === 'population' ? 'Carnaga' : null);
        $barangays = $request->user()->hasRole(User::ROLE_MUNICIPAL_LGU) ? Barangay::orderBy('name')->get(['id', 'name']) : collect([$barangay]);
        $chartData = app(\App\Services\PredictionChartData::class);
        if ($forecastPage === 'population') {
            $populationProjection = app(\App\Services\SemestralPopulationProjection::class)->forBarangay($barangay->id);
            $populationChart = $chartData->population($populationProjection);

            return view('barangay.dashboard.prediction', compact('barangay', 'barangays', 'forecastPage', 'populationProjection', 'populationChart'));
        }

        $this->ready();
        $history = [];
        foreach (['in', 'out'] as $type) $history[$type] = $this->prediction->history($barangay->id, $type);
        $movements = MigrationLog::actualRecords()->with('resident')->where('barangay_id', $barangay->id)->orderByDesc('transaction_date')->orderByDesc('id')->limit(20)->get();
        $residents = Inhabitant::withoutMigrationDemo()->where('barangay_id', $barangay->id)->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix']);
        $reviewError = session()->hasOldInput('log_month') || session()->hasOldInput('confirm_complete');
        $movementError = session()->hasOldInput('resident_id') || session()->hasOldInput('transaction_date');
        return view('barangay.dashboard.migration-records', compact('barangay', 'barangays', 'history', 'movements', 'residents', 'reviewError', 'movementError'));
    }

    public function verify(Request $request)
    {
        $barangay = $this->area($request); $this->ready();
        $data = $request->validate(['migration_type' => ['required', 'in:in,out'], 'log_month' => ['required', 'date_format:Y-m'],
            'source_hash' => ['required', 'regex:/^[a-f0-9]{64}$/'], 'confirm_complete' => ['required', 'accepted']]);
        $this->prediction->confirmActualMonth($barangay->id, $data['migration_type'], $data['log_month'], $request->user()->id, $data['source_hash']);
        return redirect()->route('prediction.migration', ['barangay_id' => $barangay->id])->with('status', 'Na-confirm ang complete month. Ang actual count gikan mismo sa dated movement logs.');
    }

    public function retiredStore(Request $request)
    {
        $barangay = $this->area($request, 'Carnaga');
        return redirect()->route('prediction.dashboard', ['barangay_id' => $barangay->id])
            ->with('status', 'Migration forecasting is retired. Population forecast remains available.');
    }

    public function sync(Request $request, StatisticalMigrationLogSync $sync)
    {
        $barangay = $this->area($request); $this->ready();
        DB::transaction(fn () => $sync->syncBarangay($barangay->id));
        return redirect()->route('prediction.migration', ['barangay_id' => $barangay->id])->with('status', 'Na-sync ang existing dated movement records. Wala gi-imbento nga dates o gi-confirm nga coverage.');
    }

    public function recordMovement(Request $request)
    {
        $barangay = $this->area($request); $this->ready();
        $data = $request->validate(['resident_id' => ['required', 'integer'], 'migration_type' => ['required', 'in:in,out'],
            'transaction_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:'.$this->prediction->calendar()->toDateString()],
            'confirm_date' => ['required', 'accepted']]);
        $person = Inhabitant::withoutMigrationDemo()->where('barangay_id', $barangay->id)->findOrFail($data['resident_id']);
        DB::transaction(function () use ($barangay, $person, $data, $request) {
            Barangay::whereKey($barangay->id)->lockForUpdate()->firstOrFail();
            $duplicate = MigrationRecord::withoutDemo()->where('inhabitant_id', $person->id)->where('barangay_id', $barangay->id)
                ->where('type', $data['migration_type'])->whereDate('movement_date', $data['transaction_date'])->exists();
            if ($duplicate) throw ValidationException::withMessages(['transaction_date' => 'Naa na kini nga resident/type/date. I-review ang existing event.']);
            MigrationRecord::create(['barangay_id' => $barangay->id, 'inhabitant_id' => $person->id, 'type' => $data['migration_type'],
                'movement_date' => $data['transaction_date'], 'recorded_by' => $request->user()->id]);
        });
        return redirect()->route('prediction.migration', ['barangay_id' => $barangay->id])->with('status', 'Na-save ang dated movement event. Ang residency status i-update pinaagi sa Resident Registry kung gikinahanglan.');
    }
}
