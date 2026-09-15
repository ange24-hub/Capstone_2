<?php

namespace Tests\Feature;

use App\Models\{Barangay, BarangayRbiUpdate, Inhabitant, MigrationRecord, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_months_scope_deduplication_and_no_record_mutation(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-13 12:00:00'));
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $house = \App\Models\Household::create(['barangay_id' => $area->id, 'household_number' => 'FORECAST-TEST']);
        $resident = Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $house->id,
            'first_name' => 'PRIVATE-FORECAST-RESIDENT', 'last_name' => 'Test', 'sex' => 'Male', 'status' => Inhabitant::STATUS_ACTIVE]);
        foreach ([[$area->id, '2024-09-01'], [$area->id, '2026-08-31'], [$area->id, '2024-08-31'], [$area->id, '2026-09-01'], [$area->id, '2026-10-01'], [$other->id, '2026-08-01']] as [$id, $date]) {
            MigrationRecord::create(['inhabitant_id' => $resident->id, 'barangay_id' => $id, 'type' => 'in', 'movement_date' => $date]);
        }
        foreach ([['2026-08-01', 'submitted', now()], ['2026-08-01', 'submitted', now()], ['2026-07-01', 'draft', now()], ['2026-09-01', 'submitted', now()], ['2026-06-01', 'submitted', now()->addDay()]] as [$month, $status, $submitted]) {
            BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => 'Looc', 'reporting_month' => $month, 'status' => $status, 'submitted_at' => $submitted]);
        }
        $before = [MigrationRecord::all()->toArray(), BarangayRbiUpdate::all()->toArray(), Inhabitant::all()->toArray()];
        $response = $this->actingAs($staff)->get(route('analysis.forecast-readiness'))->assertOk()
            ->assertSee('Forecast unavailable')->assertDontSee($resident->first_name);
        $report = $response->viewData('report');
        $this->assertSame(2, $report['eventCount']);
        $this->assertFalse($report['forecastAvailable']);
        $this->assertCount(24, $report['months']);
        $this->assertCount(1, $report['coverage']);
        $this->assertSame('2024-09', $report['months']->first()['period']);
        $this->assertSame('2026-08', $report['months']->last()['period']);
        $this->assertSame(2, $report['months']->last()['forms']);
        $this->assertSame(1, $report['coverage']->first()['reportMonths']);
        $this->assertSame($before, [MigrationRecord::all()->toArray(), BarangayRbiUpdate::all()->toArray(), Inhabitant::all()->toArray()]);
        $this->get(route('analysis.forecast-readiness', ['barangay_id' => $other->id]))->assertForbidden();
        $this->get(route('analysis.forecast-readiness', ['barangay_id' => 'invalid']))->assertSessionHasErrors('barangay_id');
        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $r = $this->actingAs($municipal)->get(route('analysis.forecast-readiness'))->assertOk()->viewData('report');
        $this->assertSame(3, $r['eventCount']);
        $r = $this->get(route('analysis.forecast-readiness', ['barangay_id' => $other->id]))->assertOk()->viewData('report');
        $this->assertSame(1, $r['eventCount']);
    }

    public function test_access_empty_history_and_year_boundary(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-01-01'));
        $this->get(route('analysis.forecast-readiness'))->assertRedirect(route('login'));
        $resident = User::factory()->create();
        $this->actingAs($resident)->get(route('analysis.forecast-readiness'))->assertForbidden();
        $pending = User::factory()->create(['role' => User::ROLE_BARANGAY, 'approval_status' => User::APPROVAL_PENDING]);
        $this->actingAs($pending)->get(route('analysis.forecast-readiness'))->assertRedirect(route('approval.pending'));
        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $r = $this->actingAs($municipal)->get(route('analysis.forecast-readiness'))->assertOk()->assertSee('No recorded history')->viewData('report');
        $this->assertSame(0, $r['eventCount']);
        $this->assertSame('2024-01', $r['months']->first()['period']);
        $this->assertSame('2025-12', $r['months']->last()['period']);
        $this->assertFalse($r['forecastAvailable']);
    }
}
