<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\MigrationReportingPeriod;
use App\Models\User;
use App\Services\MigrationForecastHistory;
use App\Services\MigrationForecastReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VerifiedMigrationForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-05 10:00:00'));
        Http::preventStrayRequests();
    }

    private function staff(?Barangay $area = null, string $role = User::ROLE_BARANGAY): User
    {
        return User::factory()->create(['role' => $role, 'barangay_id' => ($area ?? Barangay::firstOrFail())->id]);
    }

    private function person(Barangay $area): Inhabitant
    {
        $household = Household::firstOrCreate(['barangay_id' => $area->id, 'household_number' => 'FORECAST-TEST']);

        return Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $household->id,
            'first_name' => 'Private', 'last_name' => 'Resident', 'sex' => 'Female']);
    }

    private function source(Barangay $area, string $month): array
    {
        return app(MigrationForecastHistory::class)->build($area->id, Carbon::createFromFormat('!Y-m', $month), 1)['rows'][0];
    }

    private function event(Inhabitant $person, string $date, ?string $reason = null): MigrationRecord
    {
        return MigrationRecord::create(['barangay_id' => $person->barangay_id, 'inhabitant_id' => $person->id,
            'type' => 'out', 'movement_date' => $date, 'reason' => $reason]);
    }

    private function verifiedHistory(Barangay $area, User $user, int $months = 18): void
    {
        $person = $this->person($area);
        for ($i = 0; $i < $months; $i++) {
            $month = now()->startOfQuarter()->subMonths($months - $i);
            $this->event($person, $month->toDateString());
            $source = $this->source($area, $month->format('Y-m'));
            MigrationReportingPeriod::create(['barangay_id' => $area->id, 'month' => $month->format('Y-m'),
                'out_count' => 1, 'source_fingerprint' => $source['fingerprint'], 'verified_by' => $user->id, 'verified_at' => now()]);
        }
    }





    public function test_confirmed_zero_month_can_be_reopened_and_has_an_audit_trail(): void
    {
        $area = Barangay::firstOrFail();
        $staff = $this->staff($area);
        $source = $this->source($area, '2026-10');
        $data = ['barangay_id' => $area->id, 'month' => '2026-10', 'year' => 2026,
            'source_fingerprint' => $source['fingerprint'], 'action' => 'verify', 'confirm_complete' => '1', 'notes' => 'Checked transfer log'];
        $this->actingAs($staff)->post(route('migration.reporting-periods.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($this->source($area, '2026-10')['verified']);
        $this->assertDatabaseHas('migration_reporting_periods', ['barangay_id' => $area->id, 'month' => '2026-10', 'out_count' => 0, 'verified_by' => $staff->id]);
        unset($data['confirm_complete']);
        $data['action'] = 'reopen';
        $this->post(route('migration.reporting-periods.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($this->source($area, '2026-10')['verified']);
        $this->assertDatabaseCount('registry_activities', 2);
        $this->assertDatabaseCount('migration_records', 0);
    }



    public function test_scopes_attestation_future_months_and_stale_browser_forms_are_enforced(): void
    {
        $area = Barangay::firstOrFail();
        $other = Barangay::whereKeyNot($area->id)->firstOrFail();
        $data = ['barangay_id' => $area->id, 'month' => '2026-10', 'year' => 2026,
            'source_fingerprint' => $this->source($area, '2026-10')['fingerprint'], 'action' => 'verify', 'confirm_complete' => 1];
        $this->actingAs($this->staff($other))->postJson(route('migration.reporting-periods.store'), $data)->assertForbidden();
        $this->actingAs($this->staff($area, User::ROLE_RESIDENT))->postJson(route('migration.reporting-periods.store'), $data)->assertForbidden();
        $this->actingAs($this->staff($area))->postJson(route('migration.reporting-periods.store'), array_merge($data, ['confirm_complete' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors('confirm_complete');
        $this->postJson(route('migration.reporting-periods.store'), array_merge($data, ['month' => '2026-11']))
            ->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->event($this->person($area), '2026-10-03');
        $this->postJson(route('migration.reporting-periods.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('source_fingerprint');
        $this->assertDatabaseCount('migration_reporting_periods', 0);
    }

    public function test_duplicate_real_events_require_review_and_demo_events_never_enter_training(): void
    {
        $area = Barangay::firstOrFail();
        $person = $this->person($area);
        $this->event($person, '2026-10-02');
        $this->event($person, '2026-10-02');
        $this->event($person, '2026-10-03', MigrationForecastHistory::DEMO_MARKER);
        $source = $this->source($area, '2026-10');
        $this->assertSame(2, $source['count']);
        $this->assertSame(1, $source['duplicates']);
        $this->assertSame(1, $source['demo_count']);
        $this->actingAs($this->staff($area))->postJson(route('migration.reporting-periods.store'), [
            'barangay_id' => $area->id, 'month' => '2026-10', 'year' => 2026, 'source_fingerprint' => $source['fingerprint'],
            'action' => 'verify', 'confirm_complete' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->assertDatabaseCount('migration_reporting_periods', 0);
    }



    public function test_release_installer_only_adds_the_reporting_table_and_is_idempotent(): void
    {
        Schema::drop('migration_reporting_periods');
        DB::table('migrations')->whereIn('migration', ['2026_11_05_000000_create_migration_reporting_periods_table',
            '2026_11_05_010000_add_arrival_verification_to_migration_reporting_periods'])->delete();
        $before = User::count();
        $this->artisan('migration:prepare-forecast-storage')->assertSuccessful();
        $this->assertTrue(Schema::hasTable('migration_reporting_periods'));
        $this->assertTrue(Schema::hasColumn('migration_reporting_periods', 'in_verified_at'));
        $this->artisan('migration:prepare-forecast-storage')->assertSuccessful();
        $this->assertSame($before, User::count());
        $this->assertDatabaseCount('migration_reporting_periods', 0);
    }





    public function test_quarter_keeps_source_and_method_fixed_while_next_month_target_advances(): void
    {
        $area = Barangay::firstOrFail();
        $this->verifiedHistory($area, $this->staff($area));
        $method = null;
        foreach ([10 => 'November 2026', 11 => 'December 2026', 12 => 'January 2027'] as $month => $target) {
            $this->travelTo(Carbon::create(2026, $month, 5, 10));
            $report = app(MigrationForecastReport::class)->build($area->id);
            $this->assertSame('September 2026', $report['sourceMonthLabel']);
            $this->assertSame('October 1, 2026', $report['forecastRefreshLabel']);
            $this->assertSame('January 1, 2027', $report['forecastNextRefreshLabel']);
            $this->assertSame($target, $report['predictionMonthLabel']);
            $this->assertSame($month - 8, $report['forecastHorizon']);
            $this->assertSame(12, $report['forecastEvaluation']['required_months']);
            $this->assertSame(0, $report['forecastEvaluation']['validation_months']);
            $this->assertSame('seasonal_naive_12', $report['forecastEvaluation']['method_key']);
            $this->assertSame(1.0, $report['predictedOutMigration']);
            $method ??= $report['predictionMethod'];
            $this->assertSame($method, $report['predictionMethod']);
        }
        $this->event($this->person($area), '2026-10-15');
        $unchanged = app(MigrationForecastReport::class)->build($area->id);
        $this->assertSame(1.0, $unchanged['predictedOutMigration']);
        $this->assertSame('2026-09', $unchanged['forecastEvaluation']['source_end']);

        // New-quarter records must be checked before they can enter a forecast.
        $this->travelTo(Carbon::parse('2027-01-01 00:00:00'));
        $newQuarter = app(MigrationForecastReport::class)->build($area->id);
        $this->assertSame('December 2026', $newQuarter['sourceMonthLabel']);
        $this->assertSame('February 2027', $newQuarter['predictionMonthLabel']);
        $this->assertSame('April 1, 2027', $newQuarter['forecastNextRefreshLabel']);
        $this->assertNull($newQuarter['predictedOutMigration']);
        foreach (['2026-10', '2026-11', '2026-12'] as $month) {
            $source = $this->source($area, $month);
            MigrationReportingPeriod::create(['barangay_id' => $area->id, 'month' => $month,
                'out_count' => $source['count'], 'source_fingerprint' => $source['fingerprint'],
                'verified_by' => User::firstOrFail()->id, 'verified_at' => now()]);
        }
        $refreshed = app(MigrationForecastReport::class)->build($area->id);
        $this->assertNotNull($refreshed['predictedOutMigration']);
        $this->assertSame('2026-12', $refreshed['forecastEvaluation']['source_end']);
        $this->assertSame(2, $refreshed['forecastHorizon']);
    }

    public function test_quarterly_readiness_does_not_change_mid_quarter_and_audit_matches_dashboard(): void
    {
        $area = Barangay::firstOrFail();
        $this->verifiedHistory($area, $this->staff($area), 11);
        foreach ([10, 11, 12] as $month) {
            $this->travelTo(Carbon::create(2026, $month, 5, 10));
            $report = app(MigrationForecastReport::class)->build($area->id);
            $this->assertNull($report['predictedOutMigration']);
            $this->assertSame(12, $report['forecastEvaluation']['required_months']);
        }
        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, Artisan::call('migration:forecast-audit', ['--barangay' => $area->id]));
        $audit = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('September 2026', $audit['source_through']);
        $this->assertSame('January 1, 2027', $audit['next_refresh']);
        $this->assertSame(12, $audit['evaluation']['required_months']);
    }
}
