<?php

namespace Tests\Unit;

use App\Services\FrozenMigrationForecastEvaluation;
use App\Services\MigrationRateForecast;
use App\Services\ProvidedMigrationExperiment;
use App\Services\RollingHistoryBacktest;
use App\Services\SeasonalNaiveMigrationExperiment;
use PHPUnit\Framework\TestCase;

class SeasonalNaiveMigrationExperimentTest extends TestCase
{
    private function records(int $year = 2023, string $label = 'train'): array
    {
        $in = [14, 19, 24, 17, 28, 33, 22, 16, 26, 13, 21, 34];
        $out = [10, 12, 15, 18, 13, 20, 11, 21, 14, 16, 10, 17];

        return array_map(fn ($i) => ['dataset' => $label, 'year' => $year, 'month_num' => $i + 1,
            'month' => (new \DateTimeImmutable($year.'-01-01'))->modify('+'.$i.' months')->format('F'),
            'in_migrant' => $in[$i], 'out_migrant' => $out[$i], 'net_migration' => $in[$i] - $out[$i]], range(0, 11));
    }

    private function engine(): SeasonalNaiveMigrationExperiment
    {
        $rate = new MigrationRateForecast;

        return new SeasonalNaiveMigrationExperiment(new ProvidedMigrationExperiment(new RollingHistoryBacktest($rate), $rate));
    }

    public function test_all_twelve_training_months_supply_the_corresponding_forecast_without_a_holdout(): void
    {
        $records = $this->records();
        $model = $this->engine()->train($records);
        $this->assertSame('seasonal_naive_12', $model['method_key']);
        $this->assertSame('2023-12', $model['source_end']);
        $this->assertSame('2024-01', $model['forecasts'][0]['month']);
        $this->assertSame('2024-12', $model['forecasts'][11]['month']);
        $this->assertSame(12, $model['initial_training_months']);
        $this->assertSame(0, $model['test_months']);
        $this->assertSame([], $model['evaluations']);
        $this->assertFalse($model['method_selected_using_test_scores']);
        $this->assertFalse($model['tuned_on_target_actuals']);
        $this->assertTrue($model['target_year_previously_inspected']);
        $this->assertFalse($model['independent_final_test']);
        $this->assertArrayNotHasKey('posterior', $model['refitted_models']['in_migrant']);
        foreach ($records as $i => $record) {
            foreach (['in_migrant', 'out_migrant', 'net_migration'] as $key) {
                $this->assertSame($record[$key], $model['forecasts'][$i][$key]);
            }
            $this->assertNull($model['forecasts'][$i]['actual']);
        }
        $this->assertSame(-5, $model['forecasts'][7]['net_migration']);
        $records[0]['in_migrant'] += 5;
        $records[0]['net_migration'] += 5;
        $changed = $this->engine()->train($records);
        $this->assertSame($model['forecasts'][0]['in_migrant'] + 5, $changed['forecasts'][0]['in_migrant']);
        $this->assertSame(array_slice($model['forecasts'], 1), array_slice($changed['forecasts'], 1));
    }

    public function test_two_training_cycles_use_the_latest_matching_month_and_zeros_remain_zero(): void
    {
        $older = $this->records(2022);
        $newer = array_map(fn ($row) => array_replace($row, ['in_migrant' => $row['in_migrant'] + 10, 'net_migration' => $row['net_migration'] + 10]), $this->records());
        $model = $this->engine()->train(array_merge($older, $newer));
        $this->assertSame(24, $model['initial_training_months']);
        $this->assertSame(array_column($newer, 'in_migrant'), array_column($model['forecasts'], 'in_migrant'));
        $zeros = array_map(fn ($row) => array_replace($row, ['in_migrant' => 0, 'out_migrant' => 0, 'net_migration' => 0]), $this->records());
        $this->assertSame(array_fill(0, 12, 0), array_column($this->engine()->train($zeros)['forecasts'], 'in_migrant'));
    }

    public function test_external_scoring_preserves_the_fixed_seasonal_predictions_and_pooled_score_does_not_double_count_net(): void
    {
        $actuals = $this->records(2024, 'actual');
        $in = [17, 20, 26, 15, 23, 31, 25, 14, 28, 11, 24, 35];
        $out = [12, 11, 17, 14, 15, 22, 12, 23, 13, 18, 10, 16];
        foreach ($actuals as $i => &$row) {
            $row['in_migrant'] = $in[$i];
            $row['out_migrant'] = $out[$i];
            $row['net_migration'] = $in[$i] - $out[$i];
        }
        unset($row);
        $model = $this->engine()->train($this->records());
        $evaluation = (new FrozenMigrationForecastEvaluation)->evaluate($model, $actuals);
        $this->assertSame($model['forecasts'], $evaluation['forecasts']);
        $this->assertFalse($evaluation['model_refitted']);
        $this->assertTrue($evaluation['target_year_previously_inspected']);
        $this->assertEqualsWithDelta(28 / 12, $evaluation['evaluations']['in_migrant']['metrics']['mae'], 1e-12);
        $this->assertEqualsWithDelta(20 / 12, $evaluation['evaluations']['out_migrant']['metrics']['mae'], 1e-12);
        $this->assertEqualsWithDelta(34 / 12, $evaluation['evaluations']['net_migration']['metrics']['mae'], 1e-12);
        $this->assertSame(48, $evaluation['pooled_in_out']['absolute_error_total']);
        $this->assertSame(452, $evaluation['pooled_in_out']['absolute_actual_total']);
        $this->assertEqualsWithDelta(100 * (1 - 48 / 452), $evaluation['pooled_in_out']['skill_vs_zero_percent'], 1e-12);
        $this->assertEqualsWithDelta(100 * (1 - 34 / 118), $evaluation['evaluations']['net_migration']['metrics']['skill_vs_zero_percent'], 1e-12);
    }

    public function test_actual_labeled_rows_cannot_be_fitted(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->engine()->train($this->records(2024, 'actual'));
    }
}
