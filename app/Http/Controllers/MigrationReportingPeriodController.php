<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\MigrationReportingPeriod;
use App\Models\RegistryActivity;
use App\Models\User;
use App\Services\MigrationForecastHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MigrationReportingPeriodController extends Controller
{
    public function store(Request $request, MigrationForecastHistory $history): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole([User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU]) && $user->isApproved(), 403);
        $data = $request->validate([
            'barangay_id' => ['required', 'integer', 'exists:barangays,id'],
            'month' => ['required', 'date_format:Y-m'],
            'source_fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'movement_scope' => ['nullable', 'in:out'],
            'action' => ['required', 'in:verify,reopen'],
            'confirm_complete' => ['exclude_unless:action,verify', 'required', 'accepted'],
            'notes' => ['nullable', 'string', 'max:500'],
            'year' => ['required', 'integer', 'between:1900,9999'],
        ]);
        if ($user->hasRole(User::ROLE_BARANGAY)) {
            abort_unless((int) $data['barangay_id'] === (int) $user->barangay_id, 403);
        }
        $month = Carbon::createFromFormat('!Y-m', $data['month']);
        if ($month->year < 1900 || $month->gte(now()->startOfMonth())) {
            throw ValidationException::withMessages(['month' => 'Only completed calendar months from 1900 onward can be verified.']);
        }
        abort_unless(Schema::hasTable('migration_reporting_periods'), 503, 'Reporting-period storage is not ready. Contact the administrator.');
        DB::transaction(function () use ($data, $history, $month, $user) {
            Barangay::whereKey($data['barangay_id'])->lockForUpdate()->firstOrFail();
            $source = $history->build((int) $data['barangay_id'], $month, 1)['rows'][0];
            if (! hash_equals($source['fingerprint'], $data['source_fingerprint'])) {
                throw ValidationException::withMessages(['source_fingerprint' => 'The source records changed. Review the updated month before confirming.']);
            }
            if ($data['action'] === 'verify' && $source['duplicates']) {
                throw ValidationException::withMessages(['month' => 'Review possible duplicate departure events before confirming this month.']);
            }
            $period = MigrationReportingPeriod::firstOrNew(['barangay_id' => $data['barangay_id'], 'month' => $data['month']]);
            $fields = ['out_count', 'verified_at', 'verified_by', 'notes'];
            $before = $period->exists ? $period->only($fields) : [];
            $period->fill(['out_count' => $source['count'], 'source_fingerprint' => $source['fingerprint'],
                'verified_by' => $user->id, 'verified_at' => $data['action'] === 'verify' ? now() : null, 'notes' => $data['notes'] ?? null]);
            $period->save();
            RegistryActivity::create(['barangay_id' => $data['barangay_id'], 'user_id' => $user->id,
                'description' => 'Departure'.' reporting month '.$data['month'].' '.($data['action'] === 'verify' ? 'verified complete' : 'reopened for review'),
                'changes' => ['record_type' => 'migration_reporting_period', 'month' => $data['month'], 'movement_scope' => 'out', 'before' => $before,
                    'after' => $period->only($fields)]]);
        });

        return redirect()->route('migration.dashboard', ['section' => 'forecast', 'year' => $data['year'], 'barangay_id' => $data['barangay_id']])
            ->with('status', $data['action'] === 'verify' ? 'The '.'departure'.' reporting month is verified complete. Forecast readiness has been recalculated.' : 'The month has been reopened. It will not be used for forecasting until verified again.');
    }
}
