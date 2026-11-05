<?php

namespace Tests\Unit;

use App\Services\MigrationForecastEngine;
use PHPUnit\Framework\TestCase;

class MigrationForecastEngineTest extends TestCase
{
    private function rows(array $counts): array
    {
        $date = new \DateTimeImmutable('2024-01-01');
        return array_map(fn ($value, $index) => ['month' => $date->modify('+'.$index.' months')->format('Y-m'),
            'label' => $date->modify('+'.$index.' months')->format('M Y'), 'count' => $value, 'verified' => true], $counts, array_keys($counts));
    }

    public function test_verified_zero_months_are_valid_and_no_history_is_not_ready(): void
    {
        $engine = new MigrationForecastEngine();
        $this->assertFalse($engine->evaluate([])['ready']);
        $this->assertFalse($engine->evaluate($this->rows(array_fill(0, 13, 0)), 2)['ready']);
        $result = $engine->evaluate($this->rows(array_fill(0, 14, 0)), 2);
        $this->assertTrue($result['ready']);
        $this->assertSame(0.0, $result['prediction']);
        $this->assertSame(0.0, $result['mae']);
        $this->assertSame('moving_average_3', $result['method_key']);
        $this->assertSame(3, $result['validation_months']);
        $this->assertSame('equal', $result['baseline_comparison']);
    }

    public function test_linear_trend_is_fitted_and_evaluated_at_the_correct_horizon(): void
    {
        $result = (new MigrationForecastEngine())->evaluate($this->rows(range(1, 14)), 2);
        $this->assertTrue($result['ready']);
        $this->assertStringStartsWith('holt_', $result['method_key']);
        $this->assertEqualsWithDelta(16, $result['prediction'], 1e-9);
        $this->assertSame(0.0, $result['mae']);
        $this->assertGreaterThan(0, $result['baseline_mae']);
        $this->assertSame('better', $result['baseline_comparison']);
        foreach ($result['backtest'] as $row) $this->assertEqualsWithDelta($row['actual'], $row['predicted'], 1e-9);
    }

    public function test_held_out_actuals_cannot_change_model_selection_or_their_own_forecast(): void
    {
        $engine = new MigrationForecastEngine();
        $rows = $this->rows(range(1, 14));
        $before = $engine->evaluate($rows, 2);
        $rows[11]['count'] = 999;
        $rows[12]['count'] = 1;
        $rows[13]['count'] = 500;
        $after = $engine->evaluate($rows, 2);
        $this->assertSame($before['method_key'], $after['method_key']);
        $this->assertSame($before['validation_mae'], $after['validation_mae']);
        $this->assertSame($before['backtest'][0]['predicted'], $after['backtest'][0]['predicted']);
        $this->assertSame($before['backtest'][1]['predicted'], $after['backtest'][1]['predicted']);
        $manual = array_sum(array_map(fn ($row) => abs($row['actual'] - $row['predicted']), $after['backtest'])) / 3;
        $this->assertEqualsWithDelta($manual, $after['mae'], .00051);
        $this->assertGreaterThan(0, $after['mae']);
    }

    public function test_missing_unverified_or_invalid_months_cannot_enter_evaluation(): void
    {
        $engine = new MigrationForecastEngine();
        foreach (['unverified', 'gap', 'negative'] as $problem) {
            $rows = $this->rows(array_fill(0, 14, 3));
            if ($problem === 'unverified') $rows[6]['verified'] = false;
            if ($problem === 'gap') $rows[6]['month'] = '2027-01';
            if ($problem === 'negative') $rows[6]['count'] = -1;
            $this->assertFalse($engine->evaluate($rows, 2)['ready'], $problem);
        }
    }

    public function test_falling_trend_cannot_produce_negative_departure_counts(): void
    {
        $result = (new MigrationForecastEngine())->evaluate($this->rows(range(14, 1)), 2);
        $this->assertTrue($result['ready']);
        $this->assertSame(0.0, $result['prediction']);
    }

    public function test_quarterly_horizons_keep_the_selected_method_and_do_not_use_held_out_actuals(): void
    {
        $engine = new MigrationForecastEngine();
        $rows = $this->rows(range(1, 18));
        $selection = $engine->evaluate($rows, 4);
        $this->assertTrue($selection['ready']);
        $this->assertSame(18, $selection['required_months']);
        $this->assertSame(3, $selection['validation_months']);
        $this->assertEqualsWithDelta(22, $selection['prediction'], 1e-9);
        foreach ([2, 3, 4] as $horizon) {
            $result = $engine->evaluate($rows, $horizon, $selection['method_key']);
            $this->assertSame($selection['method_key'], $result['method_key']);
            $this->assertEqualsWithDelta(18 + $horizon, $result['prediction'], 1e-9);
            $this->assertSame(0.0, $result['mae']);
        }
        $rows[15]['count'] = 999;
        $rows[16]['count'] = 1;
        $rows[17]['count'] = 500;
        $changed = $engine->evaluate($rows, 4);
        $this->assertSame($selection['method_key'], $changed['method_key']);
        $this->assertSame($selection['validation_mae'], $changed['validation_mae']);
        $this->assertSame(array_column($selection['backtest'], 'predicted'), array_column($changed['backtest'], 'predicted'));
    }
}
