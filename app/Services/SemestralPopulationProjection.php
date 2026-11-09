<?php

namespace App\Services;

use App\Models\PopulationReportSubmission;
use Illuminate\Support\Facades\Schema;

class SemestralPopulationProjection
{
    private function label(int $period): string
    {
        return intdiv($period, 2).' · '.($period % 2 === 0 ? '1st' : '2nd').' semester';
    }

    public function forBarangay(int $barangayId): array
    {
        $now = now(config('migration_forecast.timezone', 'Asia/Manila'));
        $current = $now->year * 2 + ($now->month <= 6 ? 0 : 1);
        $target = $current + 1;
        $result = ['target' => $this->label($target), 'estimate' => null, 'history' => [],
            'inputs' => [], 'steps' => [], 'reason' => 'Need at least 3 consecutive completed semester reports.',
            'quality_issues' => 0, 'missing_periods' => [], 'mape' => null];
        if (! Schema::hasTable('population_report_submissions')) return $result;
        $snapshots = app(PopulationReportSubmissionSnapshot::class);
        $periods = [];
        $reports = PopulationReportSubmission::where('barangay_id', $barangayId)->orderBy('report_year')->orderBy('semester')->orderBy('id')->get();
        foreach ($reports as $report) {
            if (! $report->is_historical_archive) continue;
            $data = $report->snapshot;
            $period = $report->report_year * 2 + $report->semester - 1;
            // Dili apil ang current/incomplete o future semester; unknown dili zero.
            if ($period >= $current) continue;
            if (! hash_equals($report->snapshot_hash, $snapshots->hash($data))) {
                $result['reason'] = 'An archived report failed its integrity check. Review the source before projecting.';
                return $result;
            }
            $row = ['id'=>$report->id, 'period'=>$period, 'label'=>$this->label($period),
                'population'=>(int) $data['population'], 'age_sum'=>(int) $data['age_total_sum']];
            if (isset($periods[$period]) && $periods[$period]['population'] !== $row['population']) {
                $result['reason'] = 'Conflicting population totals exist for the same semester. Reconcile them first.';
                return $result;
            }
            $periods[$period] = $row;
        }
        ksort($periods);
        $result['history'] = array_values($periods);
        $result['quality_issues'] = count(array_filter($periods, fn ($row) => $row['population'] !== $row['age_sum']));
        if (! $periods) return $result;
        for ($p = array_key_first($periods); $p < array_key_last($periods); $p++) {
            if (! isset($periods[$p])) $result['missing_periods'][] = $this->label($p);
        }
        $latest = array_key_last($periods);
        $result['data_through'] = $this->label($latest);
        $inputs = [];
        for ($p = $latest - 2; $p <= $latest; $p++) {
            if (! isset($periods[$p])) return $result;
            $inputs[] = $periods[$p];
        }
        $result['inputs'] = $inputs;
        if ($target - $latest > 6) {
            $result['reason'] = 'Latest source is too old: more than 6 projection steps are needed. Add recent reports.';
            return $result;
        }
        $values = array_column($inputs, 'population');
        // Fixed weights 1,2,3 gikan sa karaan ngadto sa bag-o. Wala gi-tune sa test data.
        // Ang bridge semesters kay projected values lamang, dili actual historical records.
        for ($p = $latest + 1; $p <= $target; $p++) {
            $estimate = ($values[0] + 2 * $values[1] + 3 * $values[2]) / 6;
            $result['steps'][] = ['label'=>$this->label($p), 'estimate'=>$estimate, 'is_target'=>$p === $target];
            $values = [$values[1], $values[2], $estimate];
        }
        $result['estimate'] = $estimate;
        $result['reason'] = null;
        // Walay stored pre-period forecast + verified outcome: dili mag-imbento og MAPE.
        return $result;
    }
}
