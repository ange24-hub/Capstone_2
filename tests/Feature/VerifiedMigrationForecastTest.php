<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, MigrationRecord, MigrationReportingPeriod, User};
use App\Services\MigrationForecastHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    private function verifiedHistory(Barangay $area, User $user, int $months = 14): void
    {
        $person = $this->person($area);
        for ($i = 0; $i < $months; $i++) {
            $month = now()->startOfMonth()->subMonths($months - $i);
            $this->event($person, $month->toDateString());
            $source = $this->source($area, $month->format('Y-m'));
            MigrationReportingPeriod::create(['barangay_id' => $area->id, 'month' => $month->format('Y-m'),
                'out_count' => 1, 'source_fingerprint' => $source['fingerprint'], 'verified_by' => $user->id, 'verified_at' => now()]);
        }
    }

    public function test_unverified_history_cannot_produce_estimates_or_call_the_legacy_model(): void
    {
        $area = Barangay::firstOrFail();
        $this->event($this->person($area), '2026-10-01');
        $this->actingAs($this->staff($area))->get(route('migration.dashboard', ['section' => 'forecast']))
            ->assertOk()->assertViewHas('predictedOutMigration', null)->assertSee('Insufficient verified history')
            ->assertSee('Confirm complete month')->assertDontSee('synthetically assigned training dates');
        Http::assertNothingSent();
    }

    public function test_verified_history_produces_next_calendar_month_forecast_and_holdout_metrics(): void
    {
        $area = Barangay::firstOrFail();
        $staff = $this->staff($area);
        $this->verifiedHistory($area, $staff);
        $this->actingAs($staff)->get(route('migration.dashboard', ['section' => 'forecast']))
            ->assertOk()->assertViewHas('predictionMonthLabel', 'December 2026')->assertViewHas('sourceMonthLabel', 'October 2026')
            ->assertViewHas('predictedOutMigration', 1.0)->assertViewHas('forecastEvaluation', fn ($r) => $r['mae'] === 0.0 && count($r['backtest']) === 3)
            ->assertSee('Actual versus predicted departures')->assertSee('Selected model MAE')->assertSee('Holt linear-trend')
            ->assertDontSee('Private Resident');
        Http::assertNothingSent();
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

    public function test_source_changes_invalidate_verification_and_a_missing_month_breaks_the_training_series(): void
    {
        $area = Barangay::firstOrFail();
        $staff = $this->staff($area);
        $this->verifiedHistory($area, $staff);
        $person = $this->person($area);
        $this->event($person, '2026-10-15');
        $source = $this->source($area, '2026-10');
        $this->assertFalse($source['verified']);
        $this->assertSame('Records changed — recheck', $source['status']);
        $history = app(MigrationForecastHistory::class)->build($area->id, Carbon::parse('2026-10-01'));
        $this->assertCount(0, $history['consecutive']);
        $this->actingAs($staff)->get(route('migration.dashboard', ['section' => 'forecast']))
            ->assertOk()->assertViewHas('predictedOutMigration', null);
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

    public function test_municipal_forecast_requires_all_configured_barangays_and_secretary_cannot_switch_scope(): void
    {
        $area = Barangay::firstOrFail();
        $staff = $this->staff($area);
        $this->verifiedHistory($area, $staff);
        $this->actingAs($this->staff($area, User::ROLE_MUNICIPAL_LGU))->get(route('migration.dashboard', ['section' => 'forecast']))
            ->assertOk()->assertViewHas('predictedOutMigration', null)->assertSee('every configured barangay');
        foreach (Barangay::whereKeyNot($area->id)->get() as $other) {
            for ($i = 1; $i <= 14; $i++) MigrationReportingPeriod::create(['barangay_id' => $other->id, 'month' => now()->startOfMonth()->subMonths($i)->format('Y-m'),
                'out_count' => 0, 'source_fingerprint' => hash('sha256', '[]'), 'verified_by' => $staff->id, 'verified_at' => now()]);
        }
        $this->get(route('migration.dashboard', ['section' => 'forecast']))->assertOk()->assertViewHas('predictedOutMigration', 1.0);
        $this->actingAs($staff)->get(route('migration.dashboard', ['section' => 'forecast', 'barangay_id' => Barangay::whereKeyNot($area->id)->value('id')]))
            ->assertOk()->assertViewHas('selectedBarangayId', $area->id)->assertViewHas('predictedOutMigration', 1.0);
    }

    public function test_release_installer_only_adds_the_reporting_table_and_is_idempotent(): void
    {
        Schema::drop('migration_reporting_periods');
        DB::table('migrations')->where('migration', '2026_11_05_000000_create_migration_reporting_periods_table')->delete();
        $before = User::count();
        $this->artisan('migration:prepare-forecast-storage')->assertSuccessful();
        $this->assertTrue(Schema::hasTable('migration_reporting_periods'));
        $this->artisan('migration:prepare-forecast-storage')->assertSuccessful();
        $this->assertSame($before, User::count());
        $this->assertDatabaseCount('migration_reporting_periods', 0);
    }

    public function test_assistant_uses_the_same_verified_forecast_and_respects_role_scope_and_supported_targets(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $staff = $this->staff($area);
        $this->verifiedHistory($area, $staff);
        $this->actingAs($staff)->postJson(route('assistant.chat'), ['message' => 'Predict out-migration next month', 'language' => 'en'])
            ->assertOk()->assertJsonPath('mode', 'database_forecast')->assertJsonPath('facts.predicted_departures', 1)
            ->assertJsonPath('facts.forecast_month', 'December 2026')->assertJsonPath('facts.mae', 0)
            ->assertJsonPath('actions.0.url', route('migration.dashboard', ['section' => 'forecast', 'barangay_id' => $area->id]));
        $this->postJson(route('assistant.chat'), ['message' => 'Pila ang predicted migration sunod bulan?', 'language' => 'auto'])
            ->assertOk()->assertJsonPath('language', 'ceb')->assertJsonPath('facts.predicted_departures', 1)->assertSee('Banabana');
        foreach (['Predict in migration next month', 'Predict migration in 2027'] as $question) {
            $this->postJson(route('assistant.chat'), ['message' => $question, 'language' => 'en'])->assertOk()
                ->assertJsonPath('needs_clarification', true)->assertJsonMissingPath('facts');
        }
        $this->postJson(route('assistant.chat'), ['message' => 'Predict migration in Canlupao'])
            ->assertOk()->assertJsonPath('blocked_reason', 'assigned_barangay_only')->assertJsonMissingPath('facts');
        $this->actingAs($this->staff($area, User::ROLE_RESIDENT))->postJson(route('assistant.chat'), ['message' => 'Predict migration next month'])
            ->assertOk()->assertJsonMissingPath('facts');
        Http::assertNothingSent();
    }

    public function test_historical_year_uses_only_verified_history_through_that_december(): void
    {
        $area = Barangay::firstOrFail();
        $staff = $this->staff($area);
        for ($month = 1; $month <= 12; $month++) MigrationReportingPeriod::create(['barangay_id' => $area->id,
            'month' => sprintf('2025-%02d', $month), 'out_count' => 0, 'source_fingerprint' => hash('sha256', '[]'),
            'verified_by' => $staff->id, 'verified_at' => now()]);
        $this->event($this->person($area), '2026-01-01');
        $this->actingAs($staff)->get(route('migration.dashboard', ['section' => 'forecast', 'year' => 2025]))
            ->assertOk()->assertViewHas('predictionMonthLabel', 'January 2026')->assertViewHas('predictedOutMigration', 0.0)
            ->assertViewHas('forecastHorizon', 1)->assertViewHas('forecastEvaluation', fn ($r) => $r['source_end'] === '2025-12' && $r['mae'] === 0.0);
        Http::assertNothingSent();
    }
}
