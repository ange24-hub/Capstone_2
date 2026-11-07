<?php

namespace Tests\Unit;

use App\Services\SeasonalNaiveForecast;
use PHPUnit\Framework\TestCase;

class SeasonalNaiveForecastTest extends TestCase
{
    private function rows(array $counts): array
    {
        $start = new \DateTimeImmutable('2024-01-01');

        return array_map(fn ($count, $i) => ['month' => $start->modify('+'.$i.' months')->format('Y-m'),
            'label' => $start->modify('+'.$i.' months')->format('M Y'), 'count' => $count, 'verified' => true], $counts, array_keys($counts));
    }

    public function test_twelve_verified_months_forecast_the_matching_calendar_month_without_fabricated_error(): void
    {
        $engine = new SeasonalNaiveForecast;
        foreach ([1, 4, 12] as $horizon) {
            $result = $engine->evaluate($this->rows(range(1, 12)), $horizon);
            $this->assertTrue($result['ready']);
            $this->assertSame((float) $horizon, $result['prediction']);
            $this->assertSame(12, $result['training_months']);
            $this->assertFalse($result['backtest_ready']);
            $this->assertNull($result['mae']);
            $this->assertNull($result['baseline_mae']);
            $this->assertSame([], $result['backtest']);
            $this->assertSame('not_evaluated', $result['baseline_comparison']);
        }
        $this->assertFalse($engine->evaluate($this->rows(range(1, 11)))['ready']);
    }

    public function test_backtests_use_only_earlier_verified_records_at_the_displayed_horizon(): void
    {
        $engine = new SeasonalNaiveForecast;
        $rows = $this->rows(array_merge(range(1, 12), range(1, 12)));
        foreach ([1, 3, 4, 12] as $horizon) {
            $result = $engine->evaluate($rows, $horizon);
            if ($horizon === 12) {
                $this->assertFalse($result['backtest_ready']);

                continue;
            }
            $this->assertSame(0.0, $result['mae']);
            $this->assertCount(3, $result['backtest']);
            foreach ($result['backtest'] as $row) {
                $target = new \DateTimeImmutable($row['month'].'-01');
                $this->assertSame($target->modify('-'.$horizon.' months')->format('Y-m'), $row['training_through']);
                $this->assertSame($target->modify('-12 months')->format('Y-m'), $row['reference_month']);
                $this->assertSame((float) $row['actual'], $row['predicted']);
            }
        }
        $before = $engine->evaluate($rows, 3);
        $rows[21]['count'] = 900;
        $rows[22]['count'] = 800;
        $rows[23]['count'] = 700;
        $after = $engine->evaluate($rows, 3);
        $this->assertSame(array_column($before['backtest'], 'predicted'), array_column($after['backtest'], 'predicted'));
        $this->assertSame($before['method_key'], $after['method_key']);
        $this->assertNotSame($before['mae'], $after['mae']);
    }

    public function test_zero_forecasts_are_valid_and_zero_error_requires_separate_test_actuals(): void
    {
        $engine = new SeasonalNaiveForecast;
        $short = $engine->evaluate($this->rows(array_fill(0, 12, 0)));
        $this->assertSame(0.0, $short['prediction']);
        $this->assertNull($short['mae']);
        $long = $engine->evaluate($this->rows(array_fill(0, 15, 0)));
        $this->assertSame(0.0, $long['mae']);
        $this->assertSame(0.0, $long['baseline_mae']);
        $this->assertTrue($long['backtest_ready']);
        $this->assertFalse($engine->evaluate($this->rows(array_fill(0, 17, 0)), 4)['backtest_ready']);
    }

    public function test_unknown_unverified_gapped_or_invalid_sources_are_not_fitted(): void
    {
        $engine = new SeasonalNaiveForecast;
        foreach (['unverified', 'gap', 'negative', 'unknown'] as $case) {
            $rows = $this->rows(array_fill(0, 18, 2));
            if ($case === 'unverified') {
                $rows[7]['verified'] = false;
            } elseif ($case === 'gap') {
                $rows[7]['month'] = '2024-09';
            } elseif ($case === 'negative') {
                $rows[7]['count'] = -1;
            } else {
                $rows[7]['count'] = null;
            }
            $this->assertFalse($engine->evaluate($rows)['ready'], $case);
        }
    }
}
