<?php

namespace Tests\Unit;

use App\Services\MigrationForecastEngine;
use App\Services\TwelveMonthHoldoutForecast;
use PHPUnit\Framework\TestCase;

class TwelveMonthHoldoutForecastTest extends TestCase
{
    private function rows(): array
    {
        $first = new \DateTimeImmutable('2020-06-01');

        return array_map(fn ($count, $index) => ['month' => $first->modify('+'.$index.' months')->format('Y-m'),
            'label' => $first->modify('+'.$index.' months')->format('M Y'), 'count' => $count, 'complete' => true],
            [0, 1, 0, 1, 0, 2, 4, 3, 0, 0, 1, 7, 1], range(0, 12));
    }

    public function test_june_2021_actual_never_changes_fit_or_model_selection(): void
    {
        $engine = new TwelveMonthHoldoutForecast(new MigrationForecastEngine);
        $rows = $this->rows();
        $original = $engine->evaluate($rows);
        $this->assertTrue($original['ready']);
        $this->assertSame('2020-06', $original['source_start']);
        $this->assertSame('2021-05', $original['source_end']);
        $this->assertSame('2021-06', $original['target_month']);
        $this->assertSame(12, $original['training_months']);
        $this->assertSame(1, $original['holdout_actual']);
        $rows[12]['count'] = 100;
        $changed = $engine->evaluate($rows);
        foreach (['prediction', 'method_key', 'validation_mae', 'comparison', 'backtest'] as $field) {
            $this->assertSame($original[$field], $changed[$field]);
        }
        $this->assertNotSame($original['holdout_error'], $changed['holdout_error']);
        $this->assertCount(7, $original['comparison']);
    }

    public function test_missing_training_blocks_fitting_and_unknown_target_has_no_error(): void
    {
        $engine = new TwelveMonthHoldoutForecast(new MigrationForecastEngine);
        $rows = $this->rows();
        $rows[12]['complete'] = false;
        $rows[12]['count'] = null;
        $result = $engine->evaluate($rows);
        $this->assertTrue($result['ready']);
        $this->assertNull($result['holdout_actual']);
        $this->assertNull($result['holdout_error']);
        $rows[0]['complete'] = false;
        $rows[0]['count'] = null;
        $this->assertFalse($engine->evaluate($rows)['ready']);
        $this->assertFalse($engine->evaluate(array_slice($this->rows(), 1))['ready']);
        $rows = $this->rows();
        $rows[12]['month'] = '2021-07';
        $this->assertFalse($engine->evaluate($rows)['ready']);
    }

    public function test_six_month_mean_forecast_keeps_all_later_actuals_out_of_training(): void
    {
        $engine = new TwelveMonthHoldoutForecast(new MigrationForecastEngine);
        $rows = array_slice($this->rows(), 0, 12);
        foreach ([0, 0, 0, 0, 0, 0, 4, 2, 0, 0, 1, 7] as $index => $count) {
            $rows[$index]['count'] = $count;
        }
        $result = $engine->evaluate($rows, 'mean_rate', 6, 6);
        $this->assertTrue($result['ready']);
        $this->assertSame('2020-06', $result['source_start']);
        $this->assertSame('2020-11', $result['source_end']);
        $this->assertSame('2020-12', $result['target_month']);
        $this->assertSame('2021-05', $result['forecast_end']);
        $this->assertEquals(0, $result['prediction']);
        $this->assertEquals(0, $result['forecast_total']);
        $this->assertSame([4, 2, 0, 0, 1, 7], array_column($result['forecasts'], 'actual'));
        $this->assertSame(14, $result['actual_total']);
        $this->assertEqualsWithDelta(14 / 6, $result['observed_mae'], 1e-12);
        $this->assertCount(6, $result['forecasts']);
        $rows[6]['count'] = 100;
        $rows[11]['count'] = 100;
        $changed = $engine->evaluate($rows, 'mean_rate', 6, 6);
        $this->assertSame(array_column($result['forecasts'], 'predicted'), array_column($changed['forecasts'], 'predicted'));
        $this->assertNotSame($result['observed_mae'], $changed['observed_mae']);
        // A later observed arrival must not update the frozen mean for the next month.
        foreach (range(0, 5) as $index) {
            $rows[$index]['count'] = $index + 1;
        }
        $this->assertEquals(array_fill(0, 6, 3.5), array_column($engine->evaluate($rows, 'mean_rate', 6, 6)['forecasts'], 'predicted'));
    }

    public function test_mean_uses_twelve_training_counts_and_unknown_targets_have_no_invented_error(): void
    {
        $engine = new TwelveMonthHoldoutForecast(new MigrationForecastEngine);
        $rows = $this->rows();
        foreach ([0, 0, 0, 0, 0, 0, 4, 2, 0, 0, 1, 7, 1] as $index => $count) {
            $rows[$index]['count'] = $count;
        }
        $result = $engine->evaluate($rows, 'mean_rate');
        $this->assertEqualsWithDelta(14 / 12, $result['prediction'], 1e-12);
        $this->assertEqualsWithDelta(1 / 6, $result['holdout_error'], 1e-12);
        $this->assertSame('user_selected_baseline', $result['method_choice']);
        $rows[12]['complete'] = false;
        $rows[12]['count'] = null;
        $result = $engine->evaluate($rows, 'mean_rate');
        $this->assertEqualsWithDelta(14 / 12, $result['prediction'], 1e-12);
        $this->assertNull($result['actual_total']);
        $this->assertNull($result['observed_mae']);
        $this->assertNull($result['forecasts'][0]['actual']);
        $rows[0]['complete'] = false;
        $this->assertFalse($engine->evaluate($rows, 'mean_rate')['ready']);
    }

    public function test_bayesian_poisson_has_a_fixed_prior_and_no_target_leakage(): void
    {
        $engine = new TwelveMonthHoldoutForecast(new MigrationForecastEngine);
        $rows = array_slice($this->rows(), 0, 12);
        foreach ([0, 0, 0, 0, 0, 0, 4, 2, 0, 0, 1, 7] as $index => $count) {
            $rows[$index]['count'] = $count;
        }
        $result = $engine->evaluate($rows, 'poisson_jeffreys', 6, 6);
        $this->assertTrue($result['ready']);
        $this->assertSame('historical_poisson_jeffreys_fixed_origin_v1', $result['protocol']);
        $this->assertSame(0, $result['training_total']);
        $this->assertSame(['name' => 'Jeffreys', 'shape' => 0.5, 'rate' => 0, 'tuned' => false], $result['prior']);
        $this->assertSame(['shape' => 0.5, 'rate' => 6], $result['posterior']);
        $this->assertEqualsWithDelta(1 / 12, $result['prediction'], 1e-12);
        $this->assertEqualsWithDelta(0.5, $result['forecast_total'], 1e-12);
        $this->assertEqualsWithDelta(83 / 36, $result['observed_mae'], 1e-12);
        $this->assertEqualsWithDelta(14 / 6, $result['mean_rate_mae'], 1e-12);
        $this->assertEqualsWithDelta(-1 / 36, $result['mae_change_from_mean'], 1e-12);
        $this->assertSame(14, $result['actual_total']);
        $this->assertCount(2, $result['rate_comparison']);
        $this->assertFalse($result['rate_comparison'][0]['selected']);
        $this->assertTrue($result['rate_comparison'][1]['selected']);
        foreach (range(6, 11) as $index) {
            $rows[$index]['count'] = 100;
        }
        $changed = $engine->evaluate($rows, 'poisson_jeffreys', 6, 6);
        foreach (['prediction', 'forecast_total', 'prior', 'posterior'] as $key) {
            $this->assertSame($result[$key], $changed[$key]);
        }
        $this->assertSame(array_column($result['forecasts'], 'predicted'), array_column($changed['forecasts'], 'predicted'));
        foreach (range(6, 11) as $index) {
            $rows[$index]['count'] = null;
            $rows[$index]['complete'] = false;
        }
        $unknown = $engine->evaluate($rows, 'poisson_jeffreys', 6, 6);
        $this->assertTrue($unknown['ready']);
        $this->assertNull($unknown['observed_mae']);
        $this->assertNull($unknown['actual_total']);
        $this->assertNull($unknown['rate_comparison'][0]['mae']);
    }

    public function test_worse_bayesian_result_is_retained_without_choosing_using_the_actual(): void
    {
        $engine = new TwelveMonthHoldoutForecast(new MigrationForecastEngine);
        $rows = $this->rows();
        foreach ([0, 0, 0, 0, 0, 0, 4, 2, 0, 0, 1, 7, 1] as $index => $count) {
            $rows[$index]['count'] = $count;
        }
        $result = $engine->evaluate($rows, 'poisson_jeffreys');
        $this->assertSame('poisson_jeffreys', $result['method_key']);
        $this->assertEqualsWithDelta(14.5 / 12, $result['prediction'], 1e-12);
        $this->assertGreaterThan(0, $result['mae_change_from_mean']);
        $this->assertTrue($result['rate_comparison'][1]['selected']);
        $this->assertGreaterThan($result['rate_comparison'][0]['mae'], $result['rate_comparison'][1]['mae']);
    }
}
