<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, MigrationRecord, MigrationReportingPeriod, User};
use App\Services\{MigrationForecastHistory, MigrationForecastReport};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Http, Schema};
use Tests\TestCase;

class NetMigrationForecastTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Support\SubmittedArrivalFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-05 10:00:00'));
        Http::preventStrayRequests();
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => Barangay::where('name', 'Looc')->value('id')]);
    }

    private function person(User $staff): Inhabitant
    {
        $home = Household::firstOrCreate(['barangay_id' => $staff->barangay_id, 'household_number' => 'NET-TEST']);
        return Inhabitant::create(['barangay_id' => $staff->barangay_id, 'household_id' => $home->id,
            'first_name' => 'Private', 'last_name' => 'Identity', 'sex' => 'Female']);
    }

    private function event(Inhabitant $person, string $type, string $date, ?string $reason = null): void
    {
        MigrationRecord::create(['barangay_id' => $person->barangay_id, 'inhabitant_id' => $person->id,
            'type' => $type, 'movement_date' => $date, 'reason' => $reason]);
    }

    private function source(User $staff, string $type, string $month = '2026-09'): array
    {
        return app(MigrationForecastHistory::class)->build($staff->barangay_id, Carbon::createFromFormat('!Y-m', $month), 1, $type)['rows'][0];
    }

    private function seedHistory(User $staff, int $in = 3, int $out = 1, bool $verifyArrivals = true): void
    {
        $person = $this->person($staff);
        for ($i = 18; $i >= 1; $i--) {
            $month = now()->startOfQuarter()->subMonths($i);
            foreach (['in' => $in, 'out' => $out] as $type => $count) {
                for ($j = 0; $j < $count; $j++) $this->event($person, $type, $month->copy()->addDays($j)->toDateString());
            }
            $arrival = $this->source($staff, 'in', $month->format('Y-m'));
            $departure = $this->source($staff, 'out', $month->format('Y-m'));
            MigrationReportingPeriod::create(['barangay_id' => $staff->barangay_id, 'month' => $month->format('Y-m'),
                'out_count' => $out, 'source_fingerprint' => $departure['fingerprint'], 'verified_at' => now(), 'verified_by' => $staff->id,
                'in_count' => $verifyArrivals ? $in : null, 'in_source_fingerprint' => $verifyArrivals ? $arrival['fingerprint'] : null,
                'in_verified_at' => $verifyArrivals ? now() : null, 'in_verified_by' => $verifyArrivals ? $staff->id : null]);
        }
        $this->submittedArrivals($staff->barangay,$staff,'2025-04',array_fill(0,18,$in),$verifyArrivals);
    }

    private function review(User $staff, string $action = 'verify'): array
    {
        return ['barangay_id' => $staff->barangay_id, 'year' => 2026, 'month' => '2026-09', 'movement_scope' => 'all',
            'source_fingerprint' => $this->source($staff, 'out')['fingerprint'],
            'in_source_fingerprint' => $this->source($staff, 'in')['fingerprint'], 'action' => $action, 'confirm_complete' => 1];
    }







    public function test_rbi_classification_changes_refresh_the_source_without_a_period_review(): void
    {
        $staff=$this->staff();$this->seedHistory($staff);
        $saved=\App\Models\BarangayRbiUpdate::where('barangay_name',$staff->barangay->name)->whereDate('reporting_month','2026-09-01')->firstOrFail();
        $rows=$saved->rows;$rows[]=['first_name'=>'ExtraArrival','last_name'=>'Example','birth_date'=>'1990-01-01','entry_type'=>'in_migration','previous_residence'=>'Cebu','arrival_date'=>'2026-09-20'];$saved->update(['rows'=>$rows]);
        $report=app(MigrationForecastReport::class)->build($staff->barangay_id);
        $this->assertSame(4, $this->source($staff, 'in')['count']);
        $this->assertSame(3.0, $report['predictedInMigration']);
        $rows[count($rows) - 1]['entry_type'] = '';
        $saved->update(['rows' => $rows]);
        $this->assertFalse($this->source($staff, 'in')['verified']);
        $this->assertNull(app(MigrationForecastReport::class)->build($staff->barangay_id)['predictedNetMigration']);
        $rows[count($rows) - 1]['entry_type'] = 'in_migration';
        $saved->update(['rows' => $rows]);
        $this->assertTrue($this->source($staff, 'in')['verified']);
        $this->assertSame(2.0, app(MigrationForecastReport::class)->build($staff->barangay_id)['predictedNetMigration']);
    }

    public function test_joint_confirmation_requires_both_fingerprints_and_is_atomic_for_stale_arrivals(): void
    {
        $staff=$this->staff();
        $this->actingAs($staff)->postJson(route('migration.reporting-periods.store'),$this->review($staff))->assertUnprocessable()->assertJsonValidationErrors('movement_scope');
        $this->assertDatabaseCount('migration_reporting_periods',0);
        $this->assertFalse($this->source($staff,'in')['verified']);
        $other=User::factory()->create(['role'=>'barangay','barangay_id'=>Barangay::whereKeyNot($staff->barangay_id)->value('id')]);
        $data=$this->review($staff);$data['movement_scope']='out';
        $this->actingAs($other)->postJson(route('migration.reporting-periods.store'),$data)->assertForbidden();
    }

    public function test_duplicate_arrivals_block_confirmation_and_synthetic_arrivals_are_excluded(): void
    {
        $staff=$this->staff();$person=$this->person($staff);
        $this->event($person,'in','2026-09-01');$this->event($person,'in','2026-09-01');
        $this->event($person,'in','2026-09-02',MigrationForecastHistory::DEMO_MARKER);
        $source=$this->source($staff,'in');
        $this->assertSame(0,$source['count']);$this->assertFalse($source['verified']);
        $this->assertSame('classified_rbi_first_save_dates',app(MigrationForecastHistory::class)->build($staff->barangay_id,Carbon::parse('2026-09-01'),1,'in')['source']);
    }





    public function test_upgrade_preserves_departure_checks_without_automatically_verifying_arrivals(): void
    {
        $staff = $this->staff();
        MigrationReportingPeriod::create(['barangay_id' => $staff->barangay_id, 'month' => '2026-09', 'out_count' => 0,
            'source_fingerprint' => hash('sha256', '[]'), 'verified_at' => now(), 'verified_by' => $staff->id, 'notes' => 'Existing log check']);
        $migration = require database_path('migrations/2026_11_05_010000_add_arrival_verification_to_migration_reporting_periods.php');
        $migration->down();
        DB::table('migrations')->where('migration', '2026_11_05_010000_add_arrival_verification_to_migration_reporting_periods')->delete();
        $this->assertTrue($this->source($staff, 'out')['verified']);
        $this->assertFalse($this->source($staff, 'in')['verified']);
        $this->artisan('migration:prepare-forecast-storage')->assertSuccessful();
        $this->artisan('migration:prepare-forecast-storage')->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('migration_reporting_periods', 'in_count'));
        $this->assertTrue($this->source($staff, 'out')['verified']);
        $this->assertFalse($this->source($staff, 'in')['verified']);
        $this->assertDatabaseHas('migration_reporting_periods', ['notes' => 'Existing log check', 'in_verified_at' => null, 'in_count' => null]);
        $this->assertDatabaseCount('migration_records', 0);
        $this->assertDatabaseCount('registry_activities', 0);
    }

    public function test_historical_joint_forecast_excludes_later_events_and_future_years_show_no_estimate(): void
    {
        $staff = $this->staff();
        for ($month = 1; $month <= 12; $month++) MigrationReportingPeriod::create(['barangay_id' => $staff->barangay_id,
            'month' => sprintf('2025-%02d', $month), 'out_count' => 0, 'in_count' => 0,
            'source_fingerprint' => hash('sha256', '[]'), 'in_source_fingerprint' => hash('sha256', '[]'),
            'verified_at' => now(), 'in_verified_at' => now(), 'verified_by' => $staff->id, 'in_verified_by' => $staff->id]);
        $this->event($this->person($staff), 'in', '2026-01-01');
        $this->submittedArrivals($staff->barangay,$staff,'2025-01',array_fill(0,12,0));
        $report = app(MigrationForecastReport::class)->build($staff->barangay_id, 2025);
        $this->assertSame('January 2026', $report['predictionMonthLabel']);
        $this->assertSame(0.0, $report['predictedInMigration']);
        $this->assertSame(0.0, $report['predictedOutMigration']);
        $this->assertSame(0.0, $report['predictedNetMigration']);
        $this->assertSame(1, $report['forecastNetEvaluation']['horizon']);
        $this->assertNull($report['forecastNextRefreshLabel']);
        $future = app(MigrationForecastReport::class)->build($staff->barangay_id, 2027);
        $this->assertNull($future['predictedInMigration']);
        $this->assertNull($future['predictedOutMigration']);
        $this->assertNull($future['predictedNetMigration']);
    }
}
