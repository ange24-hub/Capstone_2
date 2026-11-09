<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Calculations and review flags are deterministic; local AI may prioritize verified observations only. */
class MigrationAnalyticsInsights
{
    public function build(?int $barangayId, int $year, Collection $months): array
    {
        $closed = $months->filter(fn ($row) => $row['month'] < now()->timezone('Asia/Manila')->format('Y-m'))->values();
        $facts = [];
        if ($closed->isEmpty()) {
            $facts['period'] = 'No completed calendar months are available in the selected reporting year.';
        } else {
            $facts['totals'] = sprintf('Completed months in %d contain %d classified RBI arrival entries and %d recorded departure events.',
                $year, $closed->sum('in'), $closed->sum('out'));
            foreach (['in' => 'classified RBI arrival entries', 'out' => 'recorded departure events'] as $type => $label) {
                $peak = (int) $closed->max($type);
                if ($peak > 0) {
                    $peakMonths = $closed->where($type, $peak)->pluck('month')->implode(', ');
                    $facts[$type.'_peak'] = "The highest completed-month count of {$label} is {$peak} in {$peakMonths}.";
                }
                if ($closed->count() < 2) continue;
                $previous = $closed[$closed->count() - 2];
                $latest = $closed->last();
                $before = (int) $previous[$type];
                $after = (int) $latest[$type];
                $comparison = "{$label}: {$previous['month']} = {$before}; {$latest['month']} = {$after}.";
                if ($before === 0) {
                    $comparison .= ' Percentage change is unavailable because the earlier recorded count is zero.';
                } elseif ($after === $before) {
                    $comparison .= ' The recorded count is unchanged (0%).';
                } else {
                    $percent = number_format(abs($after - $before) / $before * 100, 1);
                    $comparison .= ' The recorded count '.($after > $before ? 'increased' : 'decreased')." by {$percent}%.";
                }
                $facts[$type.'_change'] = ucfirst($comparison);
            }
        }

        // Registration timestamps describe encoding activity, never physical migration dates.
        $batches = Inhabitant::withoutMigrationDemo()->selectRaw('household_id, DATE(created_at) as encoded_day, COUNT(*) as record_count')
            ->whereNotNull('household_id')->whereYear('created_at', $year)->where('created_at', '<=', now())
            ->when($barangayId, fn ($q) => $q->where('barangay_id', $barangayId))
            ->whereHas('household', fn ($q) => $q->whereColumn('households.barangay_id', 'inhabitants.barangay_id'))
            ->groupBy('household_id')->groupByRaw('DATE(created_at)')->havingRaw('COUNT(*) >= 5');
        $batchCount = DB::query()->fromSub((clone $batches)->toBase(), 'encoding_batches')->count();
        $batchRows = (clone $batches)->orderByDesc('record_count')->orderByDesc('encoded_day')->orderBy('household_id')->limit(10)->get();
        $houses = Household::with('barangay:id,name')->whereIn('id', $batchRows->pluck('household_id'))->get()->keyBy('id');
        $alerts = $batchRows->map(function ($row) use ($houses) {
            $house = $houses->get($row->household_id);

            return ['household_id' => $house->id, 'household' => $house->household_number,
                'barangay' => $house->barangay->name, 'day' => $row->encoded_day, 'count' => (int) $row->record_count];
        })->all();
        if ($batchCount > 0) {
            $facts['encoding_review'] = "{$batchCount} household/day groups contain at least 5 encoded resident profiles in {$year}. Verify household assignments; valid bulk imports can trigger this rule.";
        }
        $undated = Inhabitant::withoutMigrationDemo()->movedOutRecords()
            ->when($barangayId, fn ($q) => $q->where('barangay_id', $barangayId))
            ->whereDoesntHave('migrationRecords', fn ($q) => $q->withoutDemo()->where('type', MigrationRecord::TYPE_OUT)->whereNotNull('movement_date'))
            ->count();
        if ($undated > 0) {
            $facts['undated'] = "{$undated} current moved-out/transfer profiles have no dated departure event. These profiles cannot be assigned to a monthly departure trend without verifying their movement dates.";
        }

        $focus = $this->focus($facts, $barangayId, $year);

        return ['observations' => $facts, 'focus' => $focus, 'ai_used' => $focus !== [],
            'alerts' => $alerts, 'alert_count' => $batchCount, 'undated_count' => $undated];
    }

    private function focus(array $facts, ?int $barangayId, int $year): array
    {
        // No ML: ang interpretation puro verified PHP calculations ug explicit rules.
        return [];
    }
}
