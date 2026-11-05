<?php

namespace Tests\Unit;

use App\Services\MigrationNetForecast;
use PHPUnit\Framework\TestCase;

class MigrationNetForecastTest extends TestCase
{
    private function evaluation(array $actual, array $predicted, array $baseline, float $future): array
    {
        return ['ready' => true, 'required_months' => 18, 'available_months' => 18,
            'source_end' => '2026-09', 'horizon' => 3, 'prediction' => $future,
            'backtest' => array_map(fn ($a, $p, $b, $i) => ['month' => '2026-0'.($i + 7), 'label' => 'Month '.$i,
                'actual' => $a, 'predicted' => $p, 'baseline' => $b], $actual, $predicted, $baseline, [0, 1, 2])];
    }

    public function test_net_uses_signed_differences_and_measures_its_own_error_against_the_same_months(): void
    {
        $in = $this->evaluation([5, 2, 8], [4, 4, 6], [3, 3, 3], 2.5);
        $out = $this->evaluation([4, 5, 2], [3, 4, 5], [1, 4, 3], 6.5);
        $net = (new MigrationNetForecast())->combine($in, $out);
        $this->assertTrue($net['ready']);
        $this->assertSame(-4.0, $net['prediction']);
        $this->assertSame([1, -3, 6], array_column($net['backtest'], 'actual'));
        $this->assertSame([1, 0, 1], array_column($net['backtest'], 'predicted'));
        $this->assertSame([0, 3, 5], array_column($net['backtest'], 'absolute_error'));
        $this->assertSame(2.667, $net['mae']);
        $this->assertSame(3.0, $net['baseline_mae']);
        $this->assertSame('better', $net['baseline_comparison']);
    }

    public function test_one_unready_series_or_mismatched_targets_cannot_produce_a_net_estimate(): void
    {
        $in = $this->evaluation([1, 1, 1], [1, 1, 1], [1, 1, 1], 1);
        foreach (['unready', 'source', 'horizon', 'targets'] as $problem) {
            $out = $in;
            if ($problem === 'unready') $out = ['ready' => false, 'required_months' => 18, 'available_months' => 0];
            if ($problem === 'source') $out['source_end'] = '2026-08';
            if ($problem === 'horizon') $out['horizon'] = 2;
            if ($problem === 'targets') $out['backtest'][0]['month'] = '2025-07';
            $net = (new MigrationNetForecast())->combine($in, $out);
            $this->assertFalse($net['ready'], $problem);
            $this->assertArrayNotHasKey('prediction', $net);
        }
    }

    public function test_a_net_forecast_can_perform_worse_than_its_baseline_and_must_report_that(): void
    {
        $in = $this->evaluation([1, 1, 1], [4, 4, 4], [1, 1, 1], 4);
        $out = $this->evaluation([0, 0, 0], [0, 0, 0], [0, 0, 0], 0);
        $net = (new MigrationNetForecast())->combine($in, $out);
        $this->assertSame(3.0, $net['mae']);
        $this->assertSame(0.0, $net['baseline_mae']);
        $this->assertSame('worse', $net['baseline_comparison']);
        $this->assertFalse($net['beats_baseline']);
    }
}
