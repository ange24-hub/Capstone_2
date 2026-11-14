<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\RbiArrivalPeriod;
use App\Models\RegistryActivity;
use App\Models\User;
use App\Services\RbiArrivalReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RbiArrivalPeriodController extends Controller
{
    private function area(Request $request): Barangay
    {
        abort_unless($request->user()->isApproved(), 403);
        if ($request->user()->hasRole(User::ROLE_BARANGAY)) {
            abort_if($request->filled('barangay_id') && $request->integer('barangay_id') !== $request->user()->barangay_id, 403);

            return $request->user()->barangay ?? abort(403);
        }

        return Barangay::findOrFail($request->validate(['barangay_id' => ['required', 'integer', 'exists:barangays,id']])['barangay_id']);
    }

    public function index(Request $request)
    {
        $area = $this->area($request);

        $history = \App\Models\NewInhabitant::where('barangay_id', $area->id)->orderBy('reporting_month')->orderBy('source_position')->orderBy('id')
            ->get(['id', 'reporting_month', 'household_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'birth_date']);
        $classifications = [];
        foreach (\App\Models\BarangayRbiUpdate::where('barangay_name', $area->name)->orderBy('updated_at')->orderBy('id')->get() as $report) {
            foreach ($report->rows ?? [] as $row) {
                $record = $history->firstWhere('id', (int) ($row['history_record_id'] ?? 0));
                if (! $record || ! $record->reporting_month || ! $report->reporting_month || $record->reporting_month->format('Y-m') !== $report->reporting_month->format('Y-m')) {
                    continue;
                }
                $label = \App\Support\RbiEntryClassification::labels()[$row['entry_type'] ?? ''] ?? 'Needs review';
                if (! empty($row['entry_type']) && ! \App\Support\RbiEntryClassification::isClassified($row, $record->reporting_month->format('Y-m'))) {
                    $label .= ' — details needed';
                }
                $classifications[$record->id] = ['label' => $label, 'status' => $report->statusLabel()];
            }
        }

        return view('rbi-updates.arrival-periods', ['barangay' => $area,
            'historyMonths' => $history->whereNotNull('reporting_month')->groupBy(fn ($row) => $row->reporting_month->format('Y-m'))->map(fn ($rows) => $rows->count()),
            'historyEntries' => $history->whereNotNull('reporting_month')->groupBy(fn ($row) => $row->reporting_month->format('Y-m')),
            'historyClassifications' => $classifications,
            'undatedHistoryCount' => $history->whereNull('reporting_month')->count()]);
    }

    public function store(Request $request, RbiArrivalReview $review)
    {
        $area = $this->area($request);
        abort_unless(Schema::hasTable('rbi_arrival_periods'), 503, 'Run the local arrival-period migration.');
        $data = $request->validate(['start_month' => ['required', 'date_format:Y-m'], 'fingerprint' => ['required', 'string', 'size:64'],
            'counts' => ['required', 'array', 'size:3'], 'counts.*' => ['required', 'integer', 'min:0'], 'confirmed' => ['required', 'accepted'], 'notes' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($area, $review, $request, $data) {
            Barangay::whereKey($area->id)->lockForUpdate()->firstOrFail();
            $period = collect($review->periods($area))->firstWhere('start', $data['start_month']);
            if (! $period || ! $period['complete'] || ! hash_equals($period['fingerprint'], $data['fingerprint'])) {
                throw ValidationException::withMessages(['period' => 'The submitted source changed or this 3-month period is not complete. Reload and review again.']);
            }
            $counts = [];
            foreach ($period['months'] as $month) {
                $value = $data['counts'][$month['month']] ?? null;
                if ($value === null || $value > $month['eligible']) {
                    throw ValidationException::withMessages(['counts' => 'Arrival counts must match these three months and cannot exceed eligible submitted residents.']);
                }
                $counts[$month['month']] = (int) $value;
            }
            $saved = RbiArrivalPeriod::firstOrNew(['barangay_id' => $area->id, 'start_month' => $period['start']]);
            $before = $saved->exists ? $saved->getAttributes() : null;
            $saved->fill(['end_month' => $period['end'], 'source_fingerprint' => $period['fingerprint'], 'monthly_counts' => $counts, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'notes' => $data['notes'] ?? null])->save();
            RegistryActivity::create(['barangay_id' => $area->id, 'user_id' => $request->user()->id, 'description' => 'Reviewed 3-month RBI arrivals: '.$period['label'],
                'changes' => ['record_type' => 'rbi_arrival_period', 'before' => $before, 'after' => $saved->getAttributes()]]);
        });

        return back()->with('status', 'Three-month arrival review saved. The live forecast groups classified RBI entries by their first save month.');
    }

    public function reopen(Request $request)
    {
        $area = $this->area($request);
        $data = $request->validate(['start_month' => ['required', 'date_format:Y-m']]);
        DB::transaction(function () use ($area, $data, $request) {
            $period = RbiArrivalPeriod::where('barangay_id', $area->id)->where('start_month', $data['start_month'])->lockForUpdate()->firstOrFail();
            $period->update(['reviewed_at' => null]);
            RegistryActivity::create(['barangay_id' => $area->id, 'user_id' => $request->user()->id, 'description' => 'Reopened RBI arrival period '.$period->start_month,
                'changes' => ['record_type' => 'rbi_arrival_period', 'start_month' => $period->start_month, 'reviewed_at' => null]]);
        });

        return back()->with('status', 'Arrival period reopened. These counts are excluded from forecasting until reviewed again.');
    }
}
