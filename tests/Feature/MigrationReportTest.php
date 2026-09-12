<?php
namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_filters_by_movement_year_and_barangay_and_matches_dashboard(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        foreach ([[$area, 'in', '2026-01-01'], [$area, 'out', '2026-12-31'], [$area, 'out', '2026-12-01'],
            [$area, 'in', '2025-12-31'], [$other, 'in', '2026-01-02']] as [$barangay, $type, $date]) {
            $house = Household::firstOrCreate(['barangay_id' => $barangay->id, 'household_number' => 'TEST-1']);
            $resident = Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $house->id,
                'first_name' => 'Example', 'last_name' => 'Resident', 'sex' => 'Male', 'status' => Inhabitant::STATUS_ACTIVE]);
            MigrationRecord::create(['inhabitant_id' => $resident->id, 'barangay_id' => $barangay->id, 'type' => $type, 'movement_date' => $date]);
        }
        $staff = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $params = ['barangay_id' => $area->id, 'year' => 2026];
        $response = $this->actingAs($staff)->get(route('reports.migration', $params))->assertOk()
            ->assertSee('Download PDF')->assertSee('No recorded events');
        $report = $response->viewData('report');
        $this->assertCount(12, $report['months']);
        $this->assertSame([1, 2, -1, 3], [$report['in'], $report['out'], $report['net'], $report['total']]);
        $this->assertSame(1, $report['months'][0]['in']);
        $this->assertSame(2, $report['months'][11]['out']);
        $this->assertSame(0, $report['months'][1]['total']);
        $dashboard = $this->get(route('migration.dashboard', $params))->assertOk();
        $this->assertSame($dashboard->viewData('totalIn'), $report['in']);
        $this->assertSame($dashboard->viewData('totalOut'), $report['out']);
        $this->assertSame($dashboard->viewData('monthlyTrend')->toArray(), $report['months']->map(fn ($month) =>
            ['month' => $month['month'], 'in' => $month['in'], 'out' => $month['out']])->toArray());
        $all = $this->get(route('reports.migration', ['year' => 2026]))->assertOk()->viewData('report');
        $this->assertSame(4, $all['total']);
        $this->assertCount(Barangay::count(), $all['coverage']);
        $empty = $this->get(route('reports.migration', ['year' => 2024]))->assertOk()->viewData('report');
        $this->assertSame(0, $empty['total']);
        $this->assertCount(12, $empty['months']);
        $pdf = $this->get(route('reports.migration.pdf', $params))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('2026.pdf', $pdf->headers->get('content-disposition'));
    }

    public function test_secretary_scope_and_input_validation_apply_to_both_exports(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->actingAs($staff)->get(route('reports.migration'))->assertOk()->assertSee('Barangay Looc')->assertDontSee('Canlupao');
        foreach (['reports.migration', 'reports.migration.pdf'] as $route) {
            $this->get(route($route, ['barangay_id' => $other->id]))->assertForbidden();
            $this->getJson(route($route, ['year' => 'invalid']))->assertUnprocessable();
            $this->getJson(route($route, ['barangay_id' => 999999]))->assertUnprocessable();
        }
        $staff->update(['barangay_id' => null]);
        $this->get(route('reports.migration'))->assertForbidden();
    }

    public function test_guests_and_residents_cannot_access_reports(): void
    {
        foreach (['reports.migration', 'reports.migration.pdf'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $resident = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        foreach (['reports.migration', 'reports.migration.pdf'] as $route) {
            $this->actingAs($resident)->get(route($route))->assertForbidden();
        }
    }
}
