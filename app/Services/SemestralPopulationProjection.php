<?php

namespace App\Services;

use App\Models\PopulationReportSubmission;
use Illuminate\Support\Carbon;
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
            'quality_issues' => 0, 'missing_periods' => [], 'excluded_reports' => [], 'mape' => null, 'historical_check' => null];
        if (! Schema::hasTable('population_report_submissions')) return $result;
        $snapshots = app(PopulationReportSubmissionSnapshot::class);
        $periods = [];
        $reports = PopulationReportSubmission::where('barangay_id', $barangayId)->orderBy('report_year')->orderBy('semester')->orderBy('id')->get();
        foreach ($reports as $report) {
            $archived = $report->is_historical_archive;
            if (! $archived && ! $report->is_municipal_submission) continue;
            $data = $report->snapshot;
            $period = $report->report_year * 2 + $report->semester - 1;
            // Dili apil ang current/incomplete o future semester; unknown dili zero.
            if ($period >= $current) continue;
            if (! hash_equals($report->snapshot_hash, $snapshots->hash($data))) {
                $result['reason'] = 'A saved report failed its integrity check. Review the source before projecting.';
                return $result;
            }

            if (! $archived && ($reason = $this->submissionExclusion($report, $data, $now))) {
                $result['excluded_reports'][] = ['id'=>$report->id, 'label'=>$this->label($period), 'reason'=>$reason];
                continue;
            }
            $population = $archived ? ($data['population'] ?? null) : ($data['totalRecords'] ?? null);
            $ageSum = $archived ? ($data['age_total_sum'] ?? null) : $this->ageSum($data['monitoring']['ages'] ?? null);
            if (! $this->isCount($population) || ! $this->isCount($ageSum) || $population !== $report->total_records
                || (! $archived && $population !== $ageSum)) {
                $result['reason'] = 'A saved report has missing or inconsistent population totals. Review the source before projecting.';
                return $result;
            }
            $row = ['id'=>$report->id, 'period'=>$period, 'label'=>$this->label($period),
                'population'=>$population, 'age_sum'=>$ageSum, 'source'=>$archived ? 'archive' : 'submission'];
            if (isset($periods[$period]) && $periods[$period]['population'] !== $row['population']) {
                $result['reason'] = 'Conflicting population totals exist for the same semester. Reconcile them first.';
                return $result;
            }
            // Equal totals are one observation, not two. Keep the original archive reference when present.
            if (! $archived && ($periods[$period]['source'] ?? null) === 'archive') continue;
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
        // Usa ka retrospective one-step check lang kung adunay upat ka sunod-sunod
        // nga actual reports. Dili kini pre-issued forecast o live MAPE.
        if (isset($periods[$latest - 3])) {
            $previous = array_map(fn ($p) => $periods[$p]['population'], range($latest - 3, $latest - 1));
            $actual = $periods[$latest]['population'];
            $historicalEstimate = ($previous[0] + 2 * $previous[1] + 3 * $previous[2]) / 6;
            $result['historical_check'] = [
                'label' => $this->label($latest), 'estimate' => $historicalEstimate, 'actual' => $actual,
                'absolute_error' => abs($actual - $historicalEstimate),
                'last_report_error' => abs($actual - $previous[2]),
                'percentage_error' => $actual > 0 ? abs($actual - $historicalEstimate) / $actual * 100 : null,
            ];
        }
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

    private function isCount(mixed $value): bool
    {
        return is_int($value) && $value >= 0;
    }

    private function ageSum(mixed $ages): ?int
    {
        if (! is_array($ages) || $ages === []) return null;
        $sum = 0;
        foreach ($ages as $age) {
            if (! is_array($age) || ! $this->isCount($age['total'] ?? null)) return null;
            $sum += $age['total'];
            if (! is_int($sum)) return null;
        }
        return $sum;
    }

    private function submissionExclusion(PopulationReportSubmission $report, array $data, Carbon $now): ?string
    {
        if (($data['monitoring']['year'] ?? null) !== $report->report_year
            || ($data['monitoring']['semester'] ?? null) !== $report->semester) {
            return 'The saved report period does not match its semester label.';
        }
        $timestamp = $data['generatedAt'] ?? null;
        if (! is_string($timestamp)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $timestamp)) {
            return 'A valid saved generation timestamp is required.';
        }
        $parsed = date_parse($timestamp);
        if ($parsed['error_count'] || $parsed['warning_count']) return 'A valid saved generation timestamp is required.';
        $generated = Carbon::parse($timestamp)->timezone(config('migration_forecast.timezone', 'Asia/Manila'));
        // Stored submission timestamps have second precision; snapshot JSON retains microseconds.
        if ($generated->gt($now) || $report->submitted_at->gt($now)
            || $generated->getTimestamp() > $report->submitted_at->getTimestamp()) {
            return 'The saved generation or submission date is not valid for this observation.';
        }
        if ($generated->year !== $report->report_year || ($generated->month <= 6 ? 1 : 2) !== $report->semester) {
            return 'The report was generated outside its labeled semester; its live-registry totals cannot represent that period.';
        }
        return null;
    }
}
