<?php

namespace Tests\Unit;

use App\Services\ShortHistoryForecast;
use PHPUnit\Framework\TestCase;

class ShortHistoryForecastTest extends TestCase
{
    private function rows(array $counts = [3, 0, 2, 0, 0, 0, 2]): array
    {
        return array_map(fn ($count, $index) => ['month' => '2021-'.str_pad((string) ($index + 3), 2, '0', STR_PAD_LEFT), 'label' => 'Month '.$index, 'count' => $count, 'complete' => true], $counts, array_keys($counts));
    }

    public function test_fixed_smoothing_matches_known_forecasts_and_counts_zeros(): void
    {
        $result = (new ShortHistoryForecast)->evaluate($this->rows());
        $this->assertTrue($result['ready']);
        $this->assertSame('2021-10', $result['target_month']);
        foreach ([2.32, 1.856, 1.4848, 1.18784] as $index => $expected) {
            $this->assertEqualsWithDelta($expected, $result['backtest'][$index]['predicted'], 1e-9);
        }
        $this->assertSame([0, 0, 0, 2], array_column($result['backtest'], 'actual'));
        $this->assertEqualsWithDelta(1.350272, $result['prediction'], 1e-9);
        $this->assertEqualsWithDelta(1.61824, $result['mae'], 1e-9);
        $this->assertEqualsWithDelta(1.25, $result['baseline_mae'], 1e-9);
        $this->assertSame('2021-06', $result['backtest'][0]['month']);
        $this->assertSame('2021-05', $result['backtest'][0]['training_through']);
        $this->assertSame(3, $result['backtest'][0]['training_months']);
        $this->assertSame('smoothing_0.2', $result['method_key']);
        $this->assertCount(5, $result['comparison']);
    }

    public function test_a_test_targets_actual_cannot_enter_its_own_or_earlier_forecast(): void
    {
        $engine = new ShortHistoryForecast;
        $original = $engine->evaluate($this->rows());
        $changed = $engine->evaluate($this->rows([3, 0, 2, 40, 0, 0, 2]));
        $this->assertSame($original['backtest'][0]['predicted'], $changed['backtest'][0]['predicted']);
        $this->assertNotSame($original['backtest'][1]['predicted'], $changed['backtest'][1]['predicted']);
        $lastChanged = $engine->evaluate($this->rows([3, 0, 2, 0, 0, 0, 20]));
        $this->assertSame(array_column($original['backtest'], 'predicted'), array_column($lastChanged['backtest'], 'predicted'));
        $this->assertNotSame($original['prediction'], $lastChanged['prediction']);
        $this->assertSame(0.2, $lastChanged['alpha']);
        $this->assertSame($original['method_key'], $lastChanged['method_key']);
        $this->assertSame($original['validation_error'], $lastChanged['validation_error']);
        $this->assertSame($original['method_key'], $changed['method_key']);
    }

    public function test_incomplete_missing_or_invalid_months_do_not_get_forecasts(): void
    {
        $engine = new ShortHistoryForecast;
        $rows = $this->rows();
        $this->assertFalse($engine->evaluate(array_slice($rows, 1))['ready']);
        foreach ([['month' => '2021-10'], ['count' => null], ['complete' => false], ['count' => -1], ['count' => '0']] as $invalid) {
            $modified = $rows;
            $modified[2] = array_replace($modified[2], $invalid);
            $this->assertFalse($engine->evaluate($modified)['ready']);
        }
        $this->assertSame(0.0, $engine->evaluate($this->rows([0, 0, 0, 0, 0, 0, 0]))['mae']);
    }

    public function test_worse_than_baseline_results_are_preserved(): void
    {
        $result = (new ShortHistoryForecast)->evaluate($this->rows());
        $this->assertSame('worse', $result['baseline_comparison']);
        $this->assertGreaterThan($result['baseline_mae'], $result['mae']);
        $this->assertSame(0.2, $result['alpha']);
    }
}
