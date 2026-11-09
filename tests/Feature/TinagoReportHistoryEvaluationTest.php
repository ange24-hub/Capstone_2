<?php

namespace Tests\Feature;

use App\Models\{Barangay, BarangayRbiUpdate, ForecastLog, MigrationLog, User};
use App\Services\{MunicipalRbiReportHistory, TinagoReportHistoryEvaluation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TinagoReportHistoryEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private function rows(): array
    {
        $values = [0, 1, 7, 0, 3, 0, 1, 3, 0, 2, 4, 7, 0, 2];
        return array_map(fn ($count, $index) => [
            'month' => Carbon::createFromFormat('!Y-m', '2020-06')->addMonths($index)->format('Y-m'),
            'label' => Carbon::createFromFormat('!Y-m', '2020-06')->addMonths($index)->format('M Y'),
            'count' => $count, 'estimated_labels' => $count, 'ambiguous' => false,
        ], $values, array_keys($values));
    }

    public function test_all_holdout_predictions_are_frozen_before_any_test_outcome_is_used(): void
    {
        $service = app(TinagoReportHistoryEvaluation::class);
        $rows = $this->rows();
        $first = $service->evaluate($rows);
        $this->assertTrue($first['ready']);
        $this->assertEqualsWithDelta(2.1428571429, $first['test_results'][0]['predicted'], 1e-9);
        $this->assertEqualsWithDelta(2.2789115646, $first['test_results'][1]['predicted'], 1e-9);
        $this->assertEqualsWithDelta(2.3517978620, $first['test_results'][2]['predicted'], 1e-9);
        $rows[11]['count'] = 100;
        $rows[12]['count'] = 200;
        $changed = $service->evaluate(array_reverse($rows));
        $this->assertSame(array_column($first['test_results'], 'predicted'), array_column($changed['test_results'], 'predicted'));
        $this->assertNotSame($first['metrics']['mae'], $changed['metrics']['mae']);
        $this->assertSame(3, $first['metrics']['evaluated_months']);
        $this->assertSame(2, $first['metrics']['mape_months']);
        $this->assertSame(1, $first['metrics']['zero_actual_months']);
        $this->assertEqualsWithDelta(2.4959507613, $first['metrics']['mae'], 1e-9);
        $this->assertEqualsWithDelta(43.4888241011, $first['metrics']['mape'], 1e-8);
        $this->assertEqualsWithDelta(3.0, $first['baseline_metrics']['mae'], 1e-9);
        $this->assertFalse($first['verified_movement_accuracy']);
    }

    public function test_missing_unknown_duplicate_or_ambiguous_periods_cannot_be_scored_as_zero(): void
    {
        $service = app(TinagoReportHistoryEvaluation::class);
        $missing = $this->rows();
        unset($missing[5]);
        $unknown = $this->rows();
        $unknown[12]['count'] = null;
        $ambiguous = $this->rows();
        $ambiguous[4]['ambiguous'] = true;
        $duplicate = $this->rows();
        $duplicate[] = $duplicate[0];
        foreach ([$missing, $unknown, $ambiguous, $duplicate] as $rows) {
            $result = $service->evaluate($rows);
            $this->assertFalse($result['ready']);
            $this->assertNull($result['metrics']);
            $this->assertSame([], $result['test_results']);
        }
    }

    public function test_tinago_uses_report_history_labels_without_changing_records_or_live_accuracy(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 12:00', 'Asia/Manila'));
        $area = Barangay::where('name', 'Tinago')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        foreach ($this->rows() as $row) {
            $entries = [['first_name' => 'Born-'.$row['month'], 'last_name' => 'Fixture', 'birth_date' => $row['month'].'-02']];
            for ($index = 0; $index < $row['count']; $index++) {
                $entries[] = ['first_name' => 'Entry-'.$row['month'].'-'.$index, 'last_name' => 'Fixture', 'birth_date' => '1990-01-01'];
            }
            // An explicitly classified adult birth must override the age suggestion.
            $entries[] = ['first_name' => 'Confirmed-'.$row['month'], 'last_name' => 'Fixture', 'birth_date' => '1990-01-01', 'entry_type' => 'birth'];
            BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => $area->name,
                'reporting_month' => $row['month'].'-01', 'status' => 'draft', 'rows' => $entries]);
        }
        $other = Barangay::where('name', 'Carnaga')->firstOrFail();
        $otherStaff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $other->id]);
        BarangayRbiUpdate::create(['barangay_user_id' => $otherStaff->id, 'barangay_name' => $other->name, 'reporting_month' => '2021-05-01',
            'status' => 'draft', 'rows' => [['first_name' => 'OutsideScope', 'last_name' => 'Fixture', 'birth_date' => '1990-01-01']]]);
        $before = BarangayRbiUpdate::orderBy('id')->get()->map->getAttributes()->all();
        $response = $this->actingAs($staff)->get(route('prediction.migration'))->assertOk()
            ->assertSee('Tinago in-migrant history test')->assertSee('historical label error')
            ->assertSee('2.50')->assertSee('43.49%')->assertDontSee('OutsideScope');
        $evaluation = $response->viewData('reportHistoryEvaluation');
        $this->assertSame([7, 0, 2], array_column($evaluation['test_results'], 'actual'));
        $this->assertSame(30, $evaluation['estimated_labels']);
        $this->assertNull($response->viewData('accuracies')['in']['mape']);
        $this->assertSame($before, BarangayRbiUpdate::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(0, ForecastLog::count());
        $this->assertSame(0, MigrationLog::count());
        $this->assertSame(1, app(MunicipalRbiReportHistory::class)->build(true, $area->id)['area_count']);
        BarangayRbiUpdate::create(['barangay_user_id' => $otherStaff->id, 'barangay_name' => $area->name,
            'reporting_month' => '2020-08-01', 'status' => 'draft', 'rows' => []]);
        $this->assertFalse(app(TinagoReportHistoryEvaluation::class)->forBarangay($area)['ready']);
    }

    public function test_other_barangays_do_not_borrow_tinago_results(): void
    {
        $source = $this->mock(MunicipalRbiReportHistory::class)->shouldNotReceive('build')->getMock();
        $service = new TinagoReportHistoryEvaluation($source);
        $this->assertNull($service->forBarangay(Barangay::where('name', 'Carnaga')->firstOrFail()));
    }
}
