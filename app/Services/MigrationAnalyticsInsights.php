<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Calculations and review flags are deterministic; local AI may prioritize verified observations only. */
class MigrationAnalyticsInsights
{
    public function build(?int $barangayId, int $year, Collection $months, int $demoCount): array
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
        $batches = Inhabitant::query()->selectRaw('household_id, DATE(created_at) as encoded_day, COUNT(*) as record_count')
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
        $undated = Inhabitant::movedOutRecords()
            ->when($barangayId, fn ($q) => $q->where('barangay_id', $barangayId))
            ->whereDoesntHave('migrationRecords', fn ($q) => $q->where('type', MigrationRecord::TYPE_OUT)->whereNotNull('movement_date'))
            ->count();
        if ($undated > 0) {
            $facts['undated'] = "{$undated} current moved-out/transfer profiles have no dated departure event. These profiles cannot be assigned to a monthly departure trend without verifying their movement dates.";
        }
        if ($demoCount > 0) {
            $facts['demo'] = "The selected year's departure graph includes {$demoCount} demo events. Its counts are not an actual-data-only migration result.";
        }

        $focus = $this->focus($facts, $barangayId, $year);

        return ['observations' => $facts, 'focus' => $focus, 'ai_used' => $focus !== [],
            'alerts' => $alerts, 'alert_count' => $batchCount, 'undated_count' => $undated];
    }

    private function focus(array $facts, ?int $barangayId, int $year): array
    {
        if (! config('assistant_ai.enabled') || count($facts) < 2) return [];
        $key = 'migration-insights:v1:'.hash('sha256', json_encode([
            $barangayId, $year, $facts, config('assistant_ai.url'), config('assistant_ai.model'),
        ], JSON_THROW_ON_ERROR));

        // Never accept generated prose or calculations: the model can select only existing fact IDs.
        return Cache::remember($key, 300, function () use ($facts) {
            $schema = ['type' => 'object', 'properties' => ['focus_ids' => ['type' => 'array',
                'items' => ['type' => 'string', 'enum' => array_keys($facts)], 'minItems' => 1, 'maxItems' => 2]],
                'required' => ['focus_ids'], 'additionalProperties' => false];
            $result = app(AssistantOllama::class)->classify(json_encode($facts, JSON_THROW_ON_ERROR), $schema,
                'Choose one or two supplied observation IDs that staff should review first. Prioritize data quality and meaningful changes. Return only focus_ids. Do not invent facts or infer causes.');
            if (! is_array($result) || array_keys($result) !== ['focus_ids']) return [];
            $ids = $result['focus_ids'];
            if (! is_array($ids) || ! array_is_list($ids) || count($ids) < 1 || count($ids) > 2) return [];
            foreach ($ids as $id) {
                if (! is_string($id) || ! array_key_exists($id, $facts)) return [];
            }

            return array_values(array_unique($ids));
        });
    }
}
