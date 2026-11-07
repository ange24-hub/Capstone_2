<?php

namespace Tests\Unit;

use App\Services\FrozenMigrationForecastEvaluation;
use App\Services\MigrationRateForecast;
use App\Services\ProvidedMigrationExperiment;
use App\Services\RollingHistoryBacktest;
use PHPUnit\Framework\TestCase;

class FrozenMigrationForecastEvaluationTest extends TestCase
{
    private function records(int $year, array $in, array $out, string $label): array
    {
        return array_map(fn ($i) => ['dataset' => $label, 'year' => $year, 'month_num' => $i + 1,
            'month' => (new \DateTimeImmutable($year.'-01-01'))->modify('+'.$i.' months')->format('F'),
            'in_migrant' => $in[$i], 'out_migrant' => $out[$i], 'net_migration' => $in[$i] - $out[$i]], range(0, 11));
    }

    private function model(): array
    {
        $rate = new MigrationRateForecast;

        return (new ProvidedMigrationExperiment(new RollingHistoryBacktest($rate), $rate))->train($this->records(2023,
            [14, 19, 24, 17, 28, 33, 22, 16, 26, 13, 21, 34], [10, 12, 15, 18, 13, 20, 11, 21, 14, 16, 10, 17], 'train'));
    }

    private function actuals(): array
    {
        return $this->records(2024, [17, 20, 26, 15, 23, 31, 25, 14, 28, 11, 24, 35], [12, 11, 17, 14, 15, 22, 12, 23, 13, 18, 10, 16], 'actual');
    }

    public function test_monthly_errors_and_annual_total_errors_remain_distinct(): void
    {
        $model = $this->model();
        $before = $model;
        $result = (new FrozenMigrationForecastEvaluation)->evaluate($model, $this->actuals());
        $this->assertSame($before, $model);
        $this->assertFalse($result['model_refitted']);
        $this->assertSame($model['forecasts'], $result['forecasts']);
        $this->assertSame($model['refitted_models'], $result['refitted_models']);
        $this->assertSame('2024-01', $result['test_start']);
        $this->assertSame('2024-12', $result['test_end']);
        $evaluations = $result['evaluations'];
        $this->assertSame([269, 183, 86], array_column(array_column($evaluations, 'annual'), 'actual'));
        $this->assertEqualsWithDelta(5.868055555555556, $evaluations['in_migrant']['metrics']['mae'], 1e-12);
        $this->assertEqualsWithDelta(3.25, $evaluations['out_migrant']['metrics']['mae'], 1e-12);
        $this->assertEqualsWithDelta(76 / 12, $evaluations['net_migration']['metrics']['mae'], 1e-12);
        $this->assertEqualsWithDelta(100 * 76 / 118, $evaluations['net_migration']['metrics']['wape_percent'], 1e-12);
        $this->assertEqualsWithDelta(100 * 4 / 86, $evaluations['net_migration']['annual']['absolute_percentage_error'], 1e-12);
        $this->assertSame(range(1, 12), array_column($evaluations['in_migrant']['backtest'], 'forecast_horizon'));
        $this->assertSame(array_fill(0, 12, '2023-12'), array_column($evaluations['in_migrant']['backtest'], 'training_through'));
        $this->assertSame(-9, $evaluations['net_migration']['backtest'][7]['actual']);
    }

    public function test_changing_target_actuals_changes_errors_but_never_any_frozen_prediction(): void
    {
        $evaluator = new FrozenMigrationForecastEvaluation;
        $model = $this->model();
        $actuals = $this->actuals();
        $before = $evaluator->evaluate($model, $actuals);
        $actuals[0]['in_migrant'] += 100;
        $actuals[0]['net_migration'] += 100;
        $after = $evaluator->evaluate($model, $actuals);
        foreach (array_keys($before['evaluations']) as $key) {
            $this->assertSame(array_column($before['evaluations'][$key]['backtest'], 'predicted'), array_column($after['evaluations'][$key]['backtest'], 'predicted'));
        }
        $this->assertNotSame($before['evaluations']['in_migrant']['metrics']['mae'], $after['evaluations']['in_migrant']['metrics']['mae']);
    }

    public function test_missing_months_wrong_years_bad_net_and_training_labels_are_rejected(): void
    {
        foreach (['missing', 'year', 'net', 'label', 'duplicate'] as $problem) {
            $actuals = $this->actuals();
            if ($problem === 'missing') {
                array_pop($actuals);
            } elseif ($problem === 'year') {
                $actuals[0]['year'] = 2023;
            } elseif ($problem === 'net') {
                $actuals[0]['net_migration'] = 100;
            } elseif ($problem === 'label') {
                $actuals[0]['dataset'] = 'train';
            } else {
                $actuals[1] = $actuals[0];
            }
            try {
                (new FrozenMigrationForecastEvaluation)->evaluate($this->model(), $actuals);
                $this->fail('Invalid actuals were accepted: '.$problem);
            } catch (\InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_zero_actuals_do_not_produce_a_fabricated_percentage_score(): void
    {
        $result = (new FrozenMigrationForecastEvaluation)->evaluate($this->model(), $this->records(2024, array_fill(0, 12, 0), array_fill(0, 12, 0), 'actual'));
        foreach ($result['evaluations'] as $evaluation) {
            $this->assertNull($evaluation['metrics']['wape_percent']);
            $this->assertNull($evaluation['metrics']['skill_vs_zero_percent']);
            $this->assertNull($evaluation['annual']['absolute_percentage_error']);
        }
        $this->assertNull($result['pooled_in_out']['skill_vs_zero_percent']);
    }
}
