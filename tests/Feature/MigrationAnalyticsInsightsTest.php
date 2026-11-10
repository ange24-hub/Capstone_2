<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use App\Services\MigrationAnalyticsInsights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MigrationAnalyticsInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 8)->setTime(12, 0));
        config(['assistant_ai.enabled' => false]);
        Http::preventStrayRequests();
    }

    private function months(): \Illuminate\Support\Collection
    {
        return collect(range(1, 12))->map(fn ($m) => ['month' => sprintf('2026-%02d', $m),
            'in' => 0, 'out' => match ($m) { 9 => 20, 10 => 27, 11 => 999, 12 => 888, default => 0 }]);
    }

    public function test_comparisons_use_closed_months_and_do_not_invent_zero_denominator_percentages(): void
    {
        $result = app(MigrationAnalyticsInsights::class)->build(null, 2026, $this->months());
        $facts = $result['observations'];
        $this->assertStringContainsString('35.0%', $facts['out_change']);
        $this->assertStringContainsString('2026-09 = 20; 2026-10 = 27', $facts['out_change']);
        $this->assertStringContainsString('Percentage change is unavailable', $facts['in_change']);
        $this->assertStringContainsString('47 recorded departure events', $facts['totals']);
        $this->assertStringNotContainsString('999', implode(' ', $facts));
        $this->assertFalse($result['ai_used']);
        Http::assertNothingSent();
    }

    private function batch(Barangay $area, string $number, int $count): Household
    {
        $house = Household::create(['barangay_id' => $area->id, 'household_number' => $number]);
        for ($i = 0; $i < $count; $i++) {
            Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $house->id,
                'first_name' => 'PrivateName'.$i, 'last_name' => 'DoNotSendToAI', 'sex' => 'Female', 'status' => 'active']);
        }

        return $house;
    }

    public function test_secretary_alerts_stay_in_assigned_barangay_and_do_not_modify_records(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        $this->batch($area, 'OWN-HOUSE', 5);
        $this->batch($area, 'BELOW-THRESHOLD', 4);
        $this->batch($other, 'OTHER-PRIVATE-HOUSE', 8);
        Inhabitant::where('barangay_id', $area->id)->first()->update(['status' => 'migrated_out']);
        $before = Inhabitant::orderBy('id')->get()->toArray();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $response = $this->actingAs($staff)->get(route('migration.dashboard', ['year' => 2026, 'barangay_id' => $other->id]))
            ->assertOk()->assertDontSee('OWN-HOUSE')->assertDontSee('OTHER-PRIVATE-HOUSE')
            ->assertDontSee('Automated statistical summary')->assertDontSee('PrivateName')
            ->assertDontSee('Trend interpretation &amp; review alerts', false)
            ->assertDontSee('migration-insights-title');
        $result = $response->viewData('analyticsInsights');
        $this->assertSame(1, $result['alert_count']);
        $this->assertSame(1, $result['undated_count']);
        $this->assertSame(5, $result['alerts'][0]['count']);
        $this->assertSame($before, Inhabitant::orderBy('id')->get()->toArray());
        $this->get(route('migration.dashboard', ['year' => 2025]))->assertOk()
            ->assertViewHas('analyticsInsights', fn ($r) => $r['alert_count'] === 0);
        $resident = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        $this->actingAs($resident)->get(route('migration.dashboard'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_no_ml_summary_never_calls_a_model_even_if_old_ai_config_is_enabled(): void
    {
        config(['assistant_ai.enabled'=>true]);
        $result = app(MigrationAnalyticsInsights::class)->build(null, 2026, $this->months());
        $this->assertFalse($result['ai_used']);
        $this->assertSame([], $result['focus']);
        $this->assertStringContainsString('35.0%', $result['observations']['out_change']);
        Http::assertNothingSent();
    }

    public function test_invalid_ai_output_and_outage_keep_database_summary_available(): void
    {
        config(['assistant_ai.enabled' => true, 'assistant_ai.url' => 'http://ollama:11434', 'assistant_ai.model' => 'qwen2.5:1.5b']);
        Http::fake([
            '*/api/tags' => Http::response(['models' => [['name' => 'qwen2.5:1.5b']]]),
            '*/api/chat' => Http::response(['message' => ['content' => '{"focus_ids":["invented_job_reason"]}']]),
        ]);
        $result = app(MigrationAnalyticsInsights::class)->build(null, 2026, $this->months());
        $this->assertFalse($result['ai_used']);
        $this->assertArrayNotHasKey('demo', $result['observations']);
        Cache::flush();
        Http::fake(['*' => Http::response([], 503)]);
        $fallback = app(MigrationAnalyticsInsights::class)->build(null, 2026, $this->months());
        $this->assertFalse($fallback['ai_used']);
        $this->assertSame($result['observations'], $fallback['observations']);
    }

    public function test_future_year_has_no_completed_month_comparison(): void
    {
        $months = $this->months()->map(fn ($r) => array_replace($r, ['month' => str_replace('2026', '2027', $r['month'])]));
        $result = app(MigrationAnalyticsInsights::class)->build(null, 2027, $months);
        $this->assertArrayNotHasKey('out_change', $result['observations']);
        $this->assertStringContainsString('No completed calendar months', $result['observations']['period']);
    }

    public function test_demo_records_are_excluded_and_population_matches_consolidated_without_deleting_data(): void
    {
        $area = Barangay::where('name', 'Hugpa')->firstOrFail();
        $real = $this->batch($area, 'REAL-HOUSE', 5);
        $marked = $this->batch($area, 'MARKED-HOUSE', 5);
        $demoHouse = $this->batch($area, 'DEMO-MIGRATION-V1', 5);
        $marker = \App\Services\MigrationForecastHistory::DEMO_MARKER;
        $marked->inhabitants()->update(['remarks' => $marker, 'status' => 'migrated_out']);
        $realPerson = $real->inhabitants()->first();
        $realPerson->update(['residence_status' => Inhabitant::RESIDENCE_ELSEWHERE]);
        foreach (['migrated_out', 'inactive'] as $status) {
            Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $real->id,
                'first_name' => 'Archived', 'last_name' => $status, 'sex' => 'Male', 'status' => $status]);
        }
        foreach ([[$realPerson, null], [$realPerson, ''], [$realPerson, $marker],
            [$marked->inhabitants()->first(), null], [$demoHouse->inhabitants()->first(), null]] as [$person, $reason]) {
            \App\Models\MigrationRecord::create(['barangay_id' => $area->id, 'inhabitant_id' => $person->id,
                'type' => 'out', 'movement_date' => '2026-10-01', 'reason' => $reason]);
        }
        $before = Inhabitant::orderBy('id')->get()->toArray();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $trends = $this->actingAs($staff)->get(route('migration.dashboard', ['year' => 2026]))->assertOk()
            ->assertDontSee('Demo data included')->assertDontSee('DEMO-MIGRATION-V1');
        $this->assertSame(2, $trends->viewData('totalOut'));
        $this->assertSame(2, $trends->viewData('monthlyTrend')->sum('out'));
        $this->assertSame(1, $trends->viewData('analyticsInsights')['alert_count']);
        $this->assertSame(1, $trends->viewData('analyticsInsights')['undated_count']);
        $this->get(route('migration.dashboard', ['year' => 2026, 'section' => 'events']))->assertOk()
            ->assertViewHas('records', fn ($r) => $r->count() === 2);
        $this->assertSame(2, app(\App\Services\MigrationSummary::class)->build($area->id, 2026)['out']);
        $population = $this->get(route('reports.population'))->assertOk()->viewData('report');
        $consolidated = $this->get(route('barangay.registry.active'))->assertOk()->viewData('inhabitants');
        $this->assertSame(5, $population['totalRecords']);
        $this->assertSame(5, $consolidated->total());
        $this->assertSame(5, array_sum($population['sex']));
        $this->assertSame(5, array_sum($population['ages']));
        $this->assertSame(5, app(\App\Services\BarangayDashboardPopulation::class)->counts($area)['residents']);
        $this->assertSame($before, Inhabitant::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('migration_records', 5);
    }
}
