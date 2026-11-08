<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\MigrationRecord;
use App\Models\MigrationReportingPeriod;
use App\Services\MigrationForecastHistory;
use App\Services\MigrationForecastReport;
use App\Services\MigrationNetForecast;
use App\Services\ValidatedMigrationForecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class ValidatedMigrationForecastReportTest extends TestCase
{
    use RefreshDatabase;

    private function source(array $counts): array
    {
        $start = Carbon::parse('2024-04-01');
        $rows = array_map(fn ($count, $i) => ['month' => $start->copy()->addMonths($i)->format('Y-m'),
            'label' => $start->copy()->addMonths($i)->format('M Y'), 'count' => $count, 'verified' => true], $counts, array_keys($counts));
        return ['rows' => $rows, 'consecutive' => $rows, 'storage_ready' => true,
            'coverage' => [], 'excluded_demo_events' => 0];
    }

    public function test_both_targets_select_separately_and_six_future_estimates_use_their_selected_models(): void
    {
        $this->travelTo(Carbon::parse('2026-11-08 10:00:00'));
        $in = range(1, 30);
        $out = array_merge(range(1, 12), range(1, 12), range(1, 6));
        $history = Mockery::mock(MigrationForecastHistory::class);
        $history->shouldReceive('build')->times(3)->with(29, Mockery::on(fn ($date) => $date->format('Y-m') === '2026-09'))
            ->andReturn($this->source($out));
        $history->shouldReceive('build')->times(3)->with(29, Mockery::any(), 36, 'in')->andReturn($this->source($in));
        $service = new MigrationForecastReport($history, new ValidatedMigrationForecast, new MigrationNetForecast);
        $report = $service->build(29);
        $this->assertStringStartsWith('holt_', $report['forecastArrivalEvaluation']['method_key']);
        $this->assertSame('seasonal_naive_12', $report['forecastEvaluation']['method_key']);
        $this->assertEqualsWithDelta(33, $report['predictedInMigration'], 1e-8);
        $this->assertSame(9.0, $report['predictedOutMigration']);
        $this->assertEqualsWithDelta(24, $report['predictedNetMigration'], 1e-8);
        $this->assertCount(6, $report['forecastMonths']);
        foreach ($report['forecastMonths'] as $i => $row) {
            $this->assertEqualsWithDelta(33 + $i, $row['in'], 1e-8);
            $this->assertEqualsWithDelta($row['in'] - $row['out'], $row['net'], 1e-8);
        }
        $this->assertEqualsWithDelta(0, $report['forecastArrivalEvaluation']['rmse'], 1e-8);
        $this->assertEqualsWithDelta(0, $report['forecastNetEvaluation']['rmse'], 1e-8);
        $evaluationHtml = view('dashboards._migration-forecast-evaluation', [
            'evaluation' => $report['forecastArrivalEvaluation'], 'kind' => 'arrivals',
        ])->render();
        $this->assertStringContainsString('Model RMSE', $evaluationHtml);
        $this->assertStringContainsString('Selected on 6 earlier validation targets', $evaluationHtml);
        $this->assertStringContainsString('Seasonal fallback on the same final test months', $evaluationHtml);
        $futureHtml = view('dashboards._migration-seasonal-future', $report)->render();
        $this->assertStringContainsString('Holt linear trend', $futureHtml);
        $this->assertStringContainsString('Seasonal naive', $futureHtml);
        foreach (['2026-10-08', '2026-12-08'] as $day) {
            $this->travelTo(Carbon::parse($day.' 10:00:00'));
            $sameQuarter = $service->build(29);
            foreach (['forecastArrivalEvaluation', 'forecastEvaluation'] as $key) {
                $this->assertSame($report[$key]['method_key'], $sameQuarter[$key]['method_key']);
                $this->assertSame($report[$key]['selection_scores'], $sameQuarter[$key]['selection_scores']);
            }
        }
    }

    public function test_readiness_command_reports_all_scopes_without_writing_source_records(): void
    {
        $before = [BarangayRbiUpdate::count(), MigrationRecord::count(), MigrationReportingPeriod::count()];
        $this->assertSame(0, Artisan::call('migration:forecast-readiness', ['--all-barangays' => true]));
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(Barangay::count() + 1, $payload['scopes']);
        $this->assertFalse($payload['source_records_changed']);
        foreach ($payload['scopes'] as $scope) {
            foreach (['in', 'out'] as $type) {
                $this->assertFalse($scope[$type]['evaluation']['ready']);
                $this->assertNull($scope[$type]['evaluation']['rmse']);
                $this->assertNotEmpty($scope[$type]['blocked_recent_months']);
            }
        }
        $this->assertSame($before, [BarangayRbiUpdate::count(), MigrationRecord::count(), MigrationReportingPeriod::count()]);
    }
}
