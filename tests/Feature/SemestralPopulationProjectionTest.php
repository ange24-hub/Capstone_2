<?php

namespace Tests\Feature;

use App\Models\{Barangay, PopulationReportSubmission, User};
use App\Services\{PopulationReportSubmissionSnapshot, SemestralPopulationProjection};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SemestralPopulationProjectionTest extends TestCase
{
    use RefreshDatabase;

    private function report(int $area, int $year, int $semester, int $population, ?int $ageSum = null): void
    {
        $snapshot = ['kind'=>'historical_population_photo', 'population'=>$population, 'age_total_sum'=>$ageSum ?? $population];
        $hash = app(PopulationReportSubmissionSnapshot::class)->hash($snapshot);
        PopulationReportSubmission::create(['barangay_id'=>$area, 'barangay_name'=>'Test area',
            'submitted_by_name'=>'Historical paper report', 'report_year'=>$year, 'semester'=>$semester,
            'total_records'=>$population, 'snapshot'=>$snapshot, 'snapshot_hash'=>$hash, 'content_hash'=>$hash,
            'submission_key'=>(string) Str::uuid(), 'submitted_at'=>now()]);
    }

    public function test_future_projection_uses_three_completed_semesters_with_explicit_unobserved_bridges(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8));
        $area = Barangay::where('name','Carnaga')->firstOrFail();
        $this->report($area->id, 2023, 1, 417);
        $this->report($area->id, 2024, 1, 459, 429);
        $this->report($area->id, 2024, 2, 456);
        $this->report($area->id, 2025, 1, 458, 425);
        $this->report($area->id, 2025, 2, 466, 447);
        // Future/current records must not leak into the calculation.
        $this->report($area->id, 2026, 2, 900);
        $this->report($area->id, 2027, 1, 999);
        $result = app(SemestralPopulationProjection::class)->forBarangay($area->id);
        $this->assertSame('2027 · 1st semester', $result['target']);
        $this->assertEqualsWithDelta(462.8055556, $result['estimate'], .000001);
        $this->assertSame([456,458,466], array_column($result['inputs'], 'population'));
        $this->assertCount(3, $result['steps']);
        $this->assertSame(['2023 · 2nd semester'], $result['missing_periods']);
        $this->assertSame(3, $result['quality_issues']);
        $this->assertNull($result['mape']);
        $staff=User::factory()->create(['role'=>User::ROLE_BARANGAY,'barangay_id'=>$area->id]);
        $this->actingAs($staff)->get(route('prediction.dashboard'))->assertOk()
            ->assertSee('Semestral population projection')->assertSee('463')->assertSee('unobserved bridge estimate')
            ->assertSee('MAPE / accuracy: Not yet validated.');
        $other=Barangay::where('name','Looc')->firstOrFail();
        $this->get(route('prediction.dashboard',['barangay_id'=>$other->id]))->assertForbidden();
        $this->assertDatabaseCount('forecast_logs',0);
        $this->assertDatabaseCount('migration_logs',0);
        $this->assertDatabaseCount('population_report_submissions',7);
    }

    public function test_gap_conflict_or_old_sources_prevent_projection(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8));
        $area = Barangay::firstOrFail()->id;
        $this->report($area, 2024, 1, 100);
        $this->report($area, 2025, 1, 110);
        $this->report($area, 2025, 2, 120);
        $service=app(SemestralPopulationProjection::class);
        $this->assertNull($service->forBarangay($area)['estimate']);
        $this->report($area, 2024, 2, 105);
        $this->assertNotNull($service->forBarangay($area)['estimate']);
        $this->travelTo(now()->setDate(2030, 11, 8));
        $this->assertNull($service->forBarangay($area)['estimate']);
        $this->travelTo(now()->setDate(2026, 11, 8));
        $this->report($area, 2025, 2, 130);
        $result=$service->forBarangay($area);
        $this->assertNull($result['estimate']);
        $this->assertStringContainsString('Conflicting', $result['reason']);
    }
}
