<?php

namespace Tests\Unit;

use App\Services\ValidatedMigrationForecast;
use PHPUnit\Framework\TestCase;

class ValidatedMigrationForecastTest extends TestCase
{
    private function rows(array $values): array
    {
        $date = new \DateTimeImmutable('2023-01-01');
        return array_map(fn ($count, $i) => ['month' => $date->modify('+'.$i.' months')->format('Y-m'),
            'label' => $date->modify('+'.$i.' months')->format('M Y'), 'count' => $count, 'verified' => true], $values, array_keys($values));
    }

    public function test_short_or_unverified_history_cannot_gain_a_forecast_or_fabricated_error(): void
    {
        $engine = new ValidatedMigrationForecast;
        $this->assertFalse($engine->evaluate([])['ready']);
        $short = $engine->evaluate($this->rows(range(1, 12)), 3);
        $this->assertTrue($short['ready']);
        $this->assertFalse($short['selection_ready']);
        $this->assertSame('seasonal_naive_12', $short['method_key']);
        $this->assertSame(3.0, $short['prediction']);
        $this->assertNull($short['mae']);
        $this->assertNull($short['rmse']);
        foreach (['gap', 'unverified', 'negative', 'unknown'] as $case) {
            $rows = $this->rows(range(1, 30));
            if ($case === 'gap') $rows[5]['month'] = '2023-12';
            if ($case === 'unverified') $rows[5]['verified'] = false;
            if ($case === 'negative') $rows[5]['count'] = -1;
            if ($case === 'unknown') $rows[5]['count'] = null;
            $this->assertFalse($engine->evaluate($rows)['ready'], $case);
        }
    }

    public function test_trend_challenger_can_improve_both_errors_on_an_independent_synthetic_fixture(): void
    {
        $result = (new ValidatedMigrationForecast)->evaluate($this->rows(range(1, 30)), 3);
        $this->assertTrue($result['selection_ready']);
        $this->assertStringStartsWith('holt_', $result['method_key']);
        $this->assertEqualsWithDelta(33, $result['prediction'], 1e-8);
        $this->assertEqualsWithDelta(0, $result['mae'], 1e-8);
        $this->assertEqualsWithDelta(0, $result['rmse'], 1e-8);
        $this->assertGreaterThan($result['mae'], $result['seasonal_mae']);
        $this->assertGreaterThan($result['rmse'], $result['seasonal_rmse']);
        $this->assertSame(6, $result['validation_months']);
        $this->assertLessThanOrEqual($result['backtest'][0]['training_through'], $result['selection_through']);
    }

    public function test_held_out_actuals_never_select_models_or_enter_their_own_origin(): void
    {
        $engine = new ValidatedMigrationForecast;
        foreach ([1, 3, 9] as $horizon) {
            $rows = $this->rows(range(1, 45));
            $before = $engine->evaluate($rows, $horizon);
            $rows[42]['count'] = 1000;
            $rows[43]['count'] = 0;
            $rows[44]['count'] = 2000;
            $after = $engine->evaluate($rows, $horizon);
            $this->assertSame($before['method_key'], $after['method_key']);
            $this->assertSame($before['selection_scores'], $after['selection_scores']);
            $this->assertSame($before['backtest'][0]['predicted'], $after['backtest'][0]['predicted']);
            if ($horizon >= 3) $this->assertSame(array_column($before['backtest'], 'predicted'), array_column($after['backtest'], 'predicted'));
            $errors = array_map(fn ($row) => $row['actual'] - $row['predicted'], $after['backtest']);
            $this->assertEqualsWithDelta(array_sum(array_map('abs', $errors)) / 3, $after['mae'], 1e-9);
            $this->assertEqualsWithDelta(sqrt(array_sum(array_map(fn ($error) => $error ** 2, $errors)) / 3), $after['rmse'], 1e-9);
        }
    }

    public function test_seasonal_and_zero_series_retain_the_baseline_in_ties(): void
    {
        $engine = new ValidatedMigrationForecast;
        foreach ([array_fill(0, 36, 0), array_merge(range(1, 12), range(1, 12), range(1, 12))] as $counts) {
            $result = $engine->evaluate($this->rows($counts), 3);
            $this->assertTrue($result['selection_ready']);
            $this->assertSame('seasonal_naive_12', $result['method_key']);
            $this->assertSame(0.0, $result['mae']);
            $this->assertSame(0.0, $result['rmse']);
        }
    }

    public function test_test_failures_are_reported_without_switching_to_the_test_winner(): void
    {
        $rows = $this->rows(range(1, 30));
        foreach ([27, 28, 29] as $i) $rows[$i]['count'] = $rows[$i - 12]['count'];
        $result = (new ValidatedMigrationForecast)->evaluate($rows, 3);
        $this->assertStringStartsWith('holt_', $result['method_key']);
        $this->assertSame(0.0, $result['seasonal_mae']);
        $this->assertEqualsWithDelta(12.0, $result['mae'], 1e-9);
        $this->assertEqualsWithDelta(12.0, $result['rmse'], 1e-9);
    }

    public function test_horizon_guard_requires_enough_prefix_and_counts_remain_nonnegative(): void
    {
        $engine = new ValidatedMigrationForecast;
        $this->assertFalse($engine->evaluate($this->rows(range(1, 24)), 3)['selection_ready']);
        $this->assertTrue($engine->evaluate($this->rows(range(1, 25)), 3)['selection_ready']);
        foreach (array_keys(ValidatedMigrationForecast::METHODS) as $method) {
            foreach ([1, 4, 9, 12] as $horizon) {
                $value = $engine->predict(range(30, 1), $horizon, $method);
                $this->assertGreaterThanOrEqual(0, $value);
                $this->assertTrue(is_finite($value));
            }
        }
    }
}
