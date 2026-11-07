<?php

namespace Tests\Unit;

use App\Services\MigrationRateForecast;
use App\Services\ProvidedMigrationExperiment;
use App\Services\RollingHistoryBacktest;
use PHPUnit\Framework\TestCase;

class ProvidedMigrationExperimentTest extends TestCase
{
    private function records(): array
    {
        $in = [18, 22, 15, 27, 20, 35, 24, 17, 29, 12, 23, 35];
        $out = [11, 14, 19, 12, 16, 21, 10, 25, 15, 17, 9, 18];

        return array_map(fn ($i) => ['year' => 2025, 'month_num' => $i + 1, 'month' => (new \DateTimeImmutable('2025-01-01'))->modify('+'.$i.' months')->format('F'),
            'in_migrant' => $in[$i], 'out_migrant' => $out[$i], 'net_migration' => $in[$i] - $out[$i]], range(0, 11));
    }

    private function engine(): ProvidedMigrationExperiment
    {
        $model = new MigrationRateForecast;

        return new ProvidedMigrationExperiment(new RollingHistoryBacktest($model), $model);
    }

    public function test_six_reserved_tests_use_only_earlier_inputs_and_refit_is_separate(): void
    {
        $engine = $this->engine();
        $records = $this->records();
        $result = $engine->evaluate($records);
        $this->assertSame('2025-06', $result['initial_training_end']);
        $this->assertSame('2025-07', $result['test_start']);
        $this->assertSame('2025-12', $result['test_end']);
        $this->assertSame(range(6, 11), array_column($result['evaluations']['in_migrant']['backtest'], 'training_months'));
        $this->assertEqualsWithDelta(137.5 / 6, $result['evaluations']['in_migrant']['backtest'][0]['predicted'], 1e-12);
        $this->assertEqualsWithDelta(93.5 / 6, $result['evaluations']['out_migrant']['backtest'][0]['predicted'], 1e-12);
        $this->assertEqualsWithDelta(277.5 / 12, $result['refitted_models']['in_migrant']['prediction'], 1e-12);
        $this->assertSame('2026-01', $result['forecasts'][0]['month']);
        $this->assertSame('2026-06', $result['forecasts'][5]['month']);
        $this->assertNull($result['forecasts'][0]['actual']);
        $this->assertFalse($result['method_selected_using_test_scores']);
        $this->assertFalse($result['source_verified']);
        $records[11]['in_migrant'] += 100;
        $records[11]['net_migration'] += 100;
        $changed = $engine->evaluate($records);
        foreach (array_keys($result['evaluations']) as $key) {
            $this->assertSame(array_column($result['evaluations'][$key]['backtest'], 'predicted'), array_column($changed['evaluations'][$key]['backtest'], 'predicted'));
        }
        $this->assertNotSame($result['evaluations']['in_migrant']['metrics']['mae'], $changed['evaluations']['in_migrant']['metrics']['mae']);
        $this->assertNotSame($result['forecasts'][0]['in_migrant'], $changed['forecasts'][0]['in_migrant']);
        $records = $this->records();
        $records[6]['in_migrant'] += 10;
        $records[6]['net_migration'] += 10;
        $changed = $engine->evaluate($records);
        $this->assertSame($result['evaluations']['in_migrant']['backtest'][0]['predicted'], $changed['evaluations']['in_migrant']['backtest'][0]['predicted']);
        $this->assertNotSame($result['evaluations']['in_migrant']['backtest'][1]['predicted'], $changed['evaluations']['in_migrant']['backtest'][1]['predicted']);
    }

    public function test_signed_net_and_its_errors_are_calculated_after_subtraction(): void
    {
        $result = $this->engine()->evaluate($this->records());
        $net = $result['evaluations']['net_migration'];
        $this->assertSame([14, -8, 14, -5, 14, 17], array_column($net['backtest'], 'actual'));
        foreach ($net['backtest'] as $i => $row) {
            $this->assertEqualsWithDelta($result['evaluations']['in_migrant']['backtest'][$i]['predicted'] - $result['evaluations']['out_migrant']['backtest'][$i]['predicted'], $row['predicted'], 1e-12);
        }
        $errors = array_map(fn ($row) => abs($row['actual'] - $row['predicted']), $net['backtest']);
        $this->assertEqualsWithDelta(array_sum($errors) / 6, $net['metrics']['mae'], 1e-12);
        $this->assertEqualsWithDelta(100 * array_sum($errors) / 72, $net['metrics']['wape_percent'], 1e-12);
        $negative = array_map(fn ($row) => array_replace($row, ['in_migrant' => 1, 'out_migrant' => 10, 'net_migration' => -9]), $this->records());
        $this->assertEqualsWithDelta(-9, $this->engine()->evaluate($negative)['forecasts'][0]['net_migration'], 1e-12);
        $zeros = array_map(fn ($row) => array_replace($row, ['in_migrant' => 0, 'out_migrant' => 0, 'net_migration' => 0]), $this->records());
        $this->assertNull($this->engine()->evaluate($zeros)['evaluations']['net_migration']['metrics']['wape_percent']);
    }

    public function test_invalid_net_missing_months_and_mismatched_month_labels_are_rejected(): void
    {
        foreach (['net', 'gap', 'name', 'negative'] as $problem) {
            $records = $this->records();
            if ($problem === 'net') {
                $records[3]['net_migration'] = 999;
            } elseif ($problem === 'gap') {
                $records[3]['year'] = 2024;
            } elseif ($problem === 'name') {
                $records[3]['month'] = 'May';
            } else {
                $records[3]['in_migrant'] = -1;
            }
            try {
                $this->engine()->evaluate($records);
                $this->fail('Invalid history was accepted: '.$problem);
            } catch (\InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_full_year_training_uses_every_month_and_does_not_report_training_as_test_accuracy(): void
    {
        $records = array_map(fn ($row) => ['dataset' => 'train'] + $row, $this->records());
        $result = $this->engine()->train($records);
        $this->assertSame('train_only', $result['mode']);
        $this->assertSame(12, $result['initial_training_months']);
        $this->assertSame('2025-12', $result['initial_training_end']);
        $this->assertSame(12, $result['refitted_models']['in_migrant']['training_months']);
        $this->assertSame(277, $result['refitted_models']['in_migrant']['training_total']);
        $this->assertFalse($result['accuracy_ready']);
        $this->assertSame(0, $result['test_months']);
        $this->assertSame([], $result['evaluations']);
        $this->assertNull($result['test_start']);
        $this->assertNull($result['test_end']);
        $this->assertCount(12, $result['forecasts']);
        $this->assertSame('2026-01', $result['forecasts'][0]['month']);
        $this->assertSame('2026-12', $result['forecasts'][11]['month']);
        foreach ($result['forecasts'] as $row) {
            $this->assertNull($row['actual']);
            $this->assertEqualsWithDelta(277.5 / 12, $row['in_migrant'], 1e-12);
            $this->assertEqualsWithDelta(187.5 / 12, $row['out_migrant'], 1e-12);
            $this->assertEqualsWithDelta(7.5, $row['net_migration'], 1e-12);
        }
        $this->assertEqualsWithDelta(277.5, $result['annual_forecast']['in_migrant'], 1e-12);
        $this->assertEqualsWithDelta(187.5, $result['annual_forecast']['out_migrant'], 1e-12);
        $this->assertEqualsWithDelta(90, $result['annual_forecast']['net_migration'], 1e-12);
        $records[11]['in_migrant'] += 12;
        $records[11]['net_migration'] += 12;
        $changed = $this->engine()->train($records);
        $this->assertEqualsWithDelta($result['forecasts'][0]['in_migrant'] + 1, $changed['forecasts'][0]['in_migrant'], 1e-12);
    }

    public function test_explicit_train_labels_never_silently_become_a_holdout(): void
    {
        $records = array_map(fn ($row) => ['dataset' => 'train'] + $row, $this->records());
        $this->expectException(\InvalidArgumentException::class);
        $this->engine()->evaluate($records);
    }

    public function test_labeled_test_records_cannot_enter_full_year_training(): void
    {
        $records = $this->records();
        $records[11]['dataset'] = 'test';
        $this->expectException(\InvalidArgumentException::class);
        $this->engine()->train($records);
    }
}
