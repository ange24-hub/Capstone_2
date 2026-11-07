<?php

namespace Tests\Unit;

use App\Services\MigrationRateForecast;
use App\Services\RollingHistoryBacktest;
use PHPUnit\Framework\TestCase;

class RollingHistoryBacktestTest extends TestCase
{
    private function rows(): array
    {
        $start = new \DateTimeImmutable('2020-06-01');

        return array_map(fn ($count, $index) => ['month' => $start->modify('+'.$index.' months')->format('Y-m'),
            'label' => $start->modify('+'.$index.' months')->format('M Y'), 'count' => $count, 'complete' => true],
            [0, 0, 0, 0, 0, 0, 4, 2, 0, 0, 1, 7], range(0, 11));
    }

    public function test_expanding_fits_use_only_earlier_actuals_and_score_exactly_december_to_may(): void
    {
        $result = (new RollingHistoryBacktest(new MigrationRateForecast))->evaluate($this->rows());
        $predictions = [0.5 / 6, 4.5 / 7, 6.5 / 8, 6.5 / 9, 6.5 / 10, 7.5 / 11];
        $baselines = [0, 4 / 7, 6 / 8, 6 / 9, 6 / 10, 7 / 11];
        $actuals = [4, 2, 0, 0, 1, 7];
        $this->assertTrue($result['ready']);
        $this->assertSame('2020-12', $result['test_start']);
        $this->assertSame('2021-05', $result['test_end']);
        $this->assertSame(1, $result['horizon']);
        $this->assertSame(6, $result['test_months']);
        $this->assertSame(range(6, 11), array_column($result['backtest'], 'training_months'));
        $this->assertSame(['2020-11', '2020-12', '2021-01', '2021-02', '2021-03', '2021-04'], array_column($result['backtest'], 'training_through'));
        $this->assertSame($actuals, array_column($result['backtest'], 'actual'));
        foreach ($result['backtest'] as $index => $row) {
            $this->assertEqualsWithDelta($predictions[$index], $row['predicted'], 1e-12);
            $this->assertEqualsWithDelta($baselines[$index], $row['baseline'], 1e-12);
            $this->assertSame(0.5, $row['prior']['shape']);
            $this->assertFalse($row['prior']['tuned']);
        }
        $error = array_sum(array_map(fn ($actual, $predicted) => abs($actual - $predicted), $actuals, $predictions)) / 6;
        $this->assertEqualsWithDelta($error, $result['mae'], 1e-12);
        $this->assertSame(14, $result['actual_total']);
    }

    public function test_current_and_later_targets_cannot_change_their_own_or_earlier_fits(): void
    {
        $engine = new RollingHistoryBacktest(new MigrationRateForecast);
        $rows = $this->rows();
        $original = $engine->evaluate($rows);
        $rows[11]['count'] = 100;
        $changed = $engine->evaluate($rows);
        $this->assertSame(array_column($original['backtest'], 'predicted'), array_column($changed['backtest'], 'predicted'));
        $this->assertNotSame($original['mae'], $changed['mae']);
        $rows = $this->rows();
        $rows[6]['count'] = 40;
        $changed = $engine->evaluate($rows);
        $this->assertSame($original['backtest'][0]['predicted'], $changed['backtest'][0]['predicted']);
        $this->assertNotSame($original['backtest'][1]['predicted'], $changed['backtest'][1]['predicted']);
        $this->assertSame($original['prior'], $changed['prior']);
    }

    public function test_unknown_counts_or_gaps_block_the_backtest_instead_of_fabricating_zeros(): void
    {
        $engine = new RollingHistoryBacktest(new MigrationRateForecast);
        $rows = $this->rows();
        $rows[8]['count'] = null;
        $rows[8]['complete'] = false;
        $this->assertFalse($engine->evaluate($rows)['ready']);
        $rows = $this->rows();
        $rows[11]['month'] = '2021-06';
        $this->assertFalse($engine->evaluate($rows)['ready']);
        $this->assertFalse($engine->evaluate(array_slice($this->rows(), 1))['ready']);
    }

    public function test_mean_method_uses_the_same_expanding_prefixes(): void
    {
        $result = (new RollingHistoryBacktest(new MigrationRateForecast))->evaluate($this->rows(), 'mean_rate');
        $this->assertNull($result['prior']);
        $this->assertEquals($result['mae'], $result['baseline_mae']);
        $this->assertEqualsWithDelta(4 / 7, $result['backtest'][1]['predicted'], 1e-12);
        $this->assertEqualsWithDelta(7 / 11, $result['backtest'][5]['predicted'], 1e-12);
    }
}
