<?php

namespace App\Services;

use App\Models\Barangay;
use Illuminate\Support\Carbon;

/** A fixed retrospective experiment on report-history labels, separate from live movement accuracy. */
class TinagoReportHistoryEvaluation
{
    public const TRAIN_START = '2020-06';
    public const TRAIN_END = '2021-04';
    public const TEST_MONTHS = ['2021-05', '2021-06', '2021-07'];
    public const WINDOW = 6;

    public function __construct(private MunicipalRbiReportHistory $source) {}

    public function forBarangay(Barangay $barangay): ?array
    {
        if (strcasecmp($barangay->name, 'Tinago') !== 0) return null;
        $inventory = $this->source->build(true, $barangay->id);
        $dataset = $inventory['datasets'][$barangay->id];
        $unmappedMonths = array_column($inventory['unmapped_reports'], 'month');
        $rows = [];
        for ($date = Carbon::createFromFormat('!Y-m', self::TRAIN_START); $date->format('Y-m') <= '2021-07'; $date->addMonth()) {
            $month = $date->format('Y-m');
            $entries = $dataset['entries'][$month] ?? [];
            $pending = count(array_filter($entries, fn ($entry) => $entry['type'] === null));
            $known = in_array($month, $dataset['months'], true);
            $ambiguous = ! empty($dataset['duplicates'][$month]) || count($dataset['reports'][$month] ?? []) > 1
                || in_array($month, $unmappedMonths, true);
            $rows[] = [
                'month' => $month, 'label' => $date->format('M Y'),
                'partition' => $month <= self::TRAIN_END ? 'training' : 'test',
                'count' => $known && ! $pending && ! $ambiguous
                    ? count(array_filter($entries, fn ($entry) => $entry['type'] === 'in_migration')) : null,
                'confirmed_labels' => count(array_filter($entries, fn ($entry) => $entry['confirmed'] && $entry['type'] === 'in_migration')),
                'estimated_labels' => count(array_filter($entries, fn ($entry) => $entry['estimated'] && $entry['type'] === 'in_migration')),
                'pending' => $pending, 'missing' => ! $known, 'ambiguous' => $ambiguous,
            ];
        }
        $result = $this->evaluate($rows);
        // Identity-free result; original report data only contributes to a one-way evidence hash.
        $result['source_sha256'] = hash('sha256', json_encode([$dataset['entries'], $dataset['reports']], JSON_THROW_ON_ERROR));
        return $result;
    }

    public function evaluate(array $rows): array
    {
        $indexed = collect($rows)->keyBy('month')->sortKeys();
        $expected = [];
        for ($date = Carbon::createFromFormat('!Y-m', self::TRAIN_START); $date->format('Y-m') <= '2021-07'; $date->addMonth()) {
            $expected[] = $date->format('Y-m');
        }
        $result = [
            'ready' => false, 'protocol' => 'tinago_report_history_wma_fixed_origin_v1',
            'training_start' => self::TRAIN_START, 'training_end' => self::TRAIN_END,
            'training_months' => 11, 'test_months' => self::TEST_MONTHS,
            'weights' => range(1, self::WINDOW), 'rows' => $rows, 'test_results' => [], 'chart' => [],
            'metrics' => null, 'baseline_metrics' => null,
            'verified_movement_accuracy' => false,
            'estimated_labels' => array_sum(array_column($rows, 'estimated_labels')),
            'reason' => 'Complete report-history labels are needed for June 2020–July 2021.',
        ];
        if ($indexed->count() !== count($rows)) return $result;
        foreach ($expected as $month) {
            $row = $indexed->get($month);
            if (! $row || ! is_int($row['count']) || $row['count'] < 0 || ($row['ambiguous'] ?? false)) return $result;
        }

        $training = $indexed->filter(fn ($row, $month) => $month >= self::TRAIN_START && $month <= self::TRAIN_END);
        $values = $training->pluck('count')->all();
        $baseline = end($values);
        $predictions = [];
        // Freeze all three forecasts before accessing any held-out outcome.
        foreach (self::TEST_MONTHS as $month) {
            $window = array_slice($values, -self::WINDOW);
            $estimate = array_sum(array_map(fn ($value, $weight) => $value * $weight, $window, $result['weights'])) / array_sum($result['weights']);
            $predictions[$month] = $estimate;
            $values[] = $estimate; // Recursive estimate, never the actual test count.
        }
        foreach (self::TEST_MONTHS as $month) {
            $actual = $indexed[$month]['count'];
            $result['test_results'][] = [
                'month' => $month, 'label' => $indexed[$month]['label'],
                'actual' => $actual, 'predicted' => $predictions[$month], 'baseline' => $baseline,
                'absolute_error' => abs($actual - $predictions[$month]),
            ];
        }
        foreach ($expected as $month) {
            $result['chart'][] = [
                'period' => $month, 'label' => Carbon::createFromFormat('!Y-m', $month)->format('M y'),
                'actual' => $indexed[$month]['count'], 'predicted' => $predictions[$month] ?? null,
                'assumption' => false,
            ];
        }
        $result['ready'] = true;
        $result['reason'] = null;
        $result['metrics'] = $this->metrics($result['test_results'], 'predicted');
        $result['baseline_metrics'] = $this->metrics($result['test_results'], 'baseline');
        return $result;
    }

    private function metrics(array $rows, string $prediction): array
    {
        $errors = array_map(fn ($row) => abs($row['actual'] - $row[$prediction]), $rows);
        $positive = array_filter($rows, fn ($row) => $row['actual'] > 0);
        return [
            'mae' => array_sum($errors) / count($errors),
            'rmse' => sqrt(array_sum(array_map(fn ($error) => $error ** 2, $errors)) / count($errors)),
            'mape' => $positive ? array_sum(array_map(fn ($row) => abs($row['actual'] - $row[$prediction]) / $row['actual'] * 100, $positive)) / count($positive) : null,
            'evaluated_months' => count($rows), 'mape_months' => count($positive),
            'zero_actual_months' => count($rows) - count($positive),
        ];
    }
}
