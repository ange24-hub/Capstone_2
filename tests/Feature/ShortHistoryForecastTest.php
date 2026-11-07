<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\MigrationRecord;
use App\Models\MigrationReportingPeriod;
use App\Models\NewInhabitant;
use App\Models\RbiArrivalPeriod;
use App\Models\User;
use App\Services\RbiShortHistorySource;
use App\Services\ShortHistoryForecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShortHistoryForecastTest extends TestCase
{
    use RefreshDatabase;

    private function source(): array
    {
        $this->travelTo(now()->setDate(2026, 11, 7));
        $area = Barangay::where('name', 'Cawayan')->firstOrFail();
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        $reports = [];
        foreach (['2021-03' => ['in_migration', 'in_migration', 'in_migration'], '2021-05' => ['in_migration', 'in_migration', 'late_registration'], '2021-06' => ['birth'], '2021-09' => ['in_migration', 'in_migration', 'birth']] as $month => $types) {
            $rows = [];
            foreach ($types as $index => $type) {
                $record = NewInhabitant::create(['barangay_id' => $area->id, 'reporting_month' => $month.'-01', 'household_number' => '1', 'first_name' => 'Person-'.$month.'-'.$index, 'last_name' => 'Example']);
                $rows[] = ['history_record_id' => $record->id, 'first_name' => $record->first_name, 'last_name' => $record->last_name, 'household_number' => '1', 'entry_type' => $type,
                    'previous_residence_unknown' => $type === 'in_migration' ? '1' : '', 'arrival_month' => $type === 'in_migration' ? $month : ''];
            }
            $reports[] = BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => $area->name, 'reporting_month' => $month.'-01', 'status' => 'draft', 'rows' => $rows]);
        }
        NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => '2', 'first_name' => 'Undated', 'last_name' => 'Example']);

        return [$area, $staff, $reports];
    }

    public function test_classified_drafts_and_bounded_gap_zeros_produce_historical_graphs_without_writes(): void
    {
        [$area, $staff] = $this->source();
        $before = [BarangayRbiUpdate::all()->toArray(), NewInhabitant::all()->toArray(), MigrationRecord::count(), MigrationReportingPeriod::count(), RbiArrivalPeriod::count()];
        $response = $this->actingAs(User::factory()->create(['role' => 'municipal_lgu']))->get(route('migration.history-experiment'))->assertOk()
            ->assertSee('PRELIMINARY')->assertSee('June 2021 test prediction')->assertSee('2.32')->assertSee('1.62')->assertSee('Five-model comparison')
            ->assertSee('history-series-title')->assertSee('history-test-title')->assertDontSee('Person-2021');
        $response->assertViewHas('history', fn ($h) => array_column($h['rows'], 'count') === [3, 0, 2, 0, 0, 0, 2] && $h['draft_months'] === 4 && $h['undated_entries'] === 1 && $h['unknown_origins'] === 7);
        $this->assertSame($before, [BarangayRbiUpdate::all()->toArray(), NewInhabitant::all()->toArray(), MigrationRecord::count(), MigrationReportingPeriod::count(), RbiArrivalPeriod::count()]);
        $this->get(route('migration.dashboard', ['barangay_id' => $area->id]))->assertOk()->assertDontSee(route('migration.history-experiment', ['barangay_id' => $area->id]));
    }

    public function test_unknown_classifications_new_entries_and_trailing_months_block_the_experiment(): void
    {
        [$area, $staff, $reports] = $this->source();
        $service = app(RbiShortHistorySource::class);
        $original = $service->build($area);
        $rows = $reports[0]->rows;
        $rows[0]['entry_type'] = '';
        $reports[0]->update(['rows' => $rows]);
        $changed = $service->build($area);
        $this->assertNotSame($original['fingerprint'], $changed['fingerprint']);
        $this->assertFalse(app(ShortHistoryForecast::class)->evaluate($changed['rows'])['ready']);
        $rows[0]['entry_type'] = 'in_migration';
        $reports[0]->update(['rows' => $rows]);
        $trailing = $service->build($area, '2021-10');
        $this->assertNull($trailing['rows'][6]['count']);
        $this->assertFalse($trailing['rows'][6]['gap_zero']);
        NewInhabitant::create(['barangay_id' => $area->id, 'reporting_month' => '2021-04-01', 'household_number' => '8', 'first_name' => 'Unreviewed', 'last_name' => 'New']);
        $new = $service->build($area);
        $this->assertNull($new['rows'][1]['count']);
        $this->assertFalse($new['rows'][1]['gap_zero']);
        $this->actingAs(User::factory()->create(['role' => 'municipal_lgu']))->get(route('migration.history-experiment'))->assertOk()->assertSee('Complete seven classified months')->assertDontSee('id="history-series-title"', false);
    }

    public function test_access_is_approved_and_scoped_and_future_windows_are_rejected(): void
    {
        [$area, $staff] = $this->source();
        $url = route('migration.history-experiment');
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'resident']))->get($url)->assertForbidden();
        $pending = User::factory()->create(['role' => 'municipal_lgu', 'approval_status' => 'pending']);
        $this->actingAs($pending)->get($url)->assertForbidden();
        $other = Barangay::where('name', 'Looc')->firstOrFail();
        $this->actingAs($staff)->get($url.'?barangay_id='.$other->id)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'municipal_lgu']))->getJson($url.'?end_month=2026-11')->assertUnprocessable();
        $municipal = User::factory()->create(['role' => 'municipal_lgu']);
        $this->actingAs($municipal)->get($url)->assertOk()->assertViewHas('barangay', fn ($b) => $b->id === $area->id);
        $this->get($url.'?barangay_id='.$other->id)->assertOk()->assertSee('Complete seven classified months')->assertDontSee('history-series-title');
    }

    public function test_runner_saves_only_private_aggregate_results_without_verifying_sources(): void
    {
        Storage::fake('local');
        [$area] = $this->source();
        $this->artisan('migration:history-experiment', ['--barangay' => $area->id])->assertSuccessful();
        $files = Storage::disk('local')->allFiles('private/forecast-history');
        $this->assertCount(2, $files);
        $json = collect($files)->first(fn ($file) => str_ends_with($file, '.json'));
        $saved = json_decode(Storage::disk('local')->get($json), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('CLASSIFIED_RBI_HISTORY_EXPERIMENT', $saved['data_source']);
        $this->assertEqualsWithDelta(1.350272, $saved['evaluation']['prediction'], 1e-9);
        $this->assertStringNotContainsString('Person-2021', Storage::disk('local')->get($json));
        $this->assertDatabaseCount('rbi_arrival_periods', 0);
        $this->assertSame(4, BarangayRbiUpdate::where('status', 'draft')->count());
    }

    public function test_demo_and_future_rows_do_not_extend_history_and_repeated_arrivals_need_review(): void
    {
        [$area, $staff, $reports] = $this->source();
        $rows = $reports[0]->rows;
        $rows[] = ['first_name' => 'Synthetic', 'last_name' => 'Demo', 'remarks' => \App\Services\MigrationForecastHistory::DEMO_MARKER];
        $reports[0]->update(['rows' => $rows]);
        BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => $area->name, 'reporting_month' => '2027-03-01', 'status' => 'draft', 'rows' => []]);
        $service = app(RbiShortHistorySource::class);
        $source = $service->build($area);
        $this->assertSame([3, 0, 2, 0, 0, 0, 2], array_column($source['rows'], 'count'));
        $this->assertSame('2021-09', $source['rows'][6]['month']);
        $this->assertTrue(app(ShortHistoryForecast::class)->evaluate($source['rows'])['ready']);
        $last = $reports[3]->rows;
        $last[0] = array_replace($rows[0], ['arrival_month' => '2021-09']);
        $reports[3]->update(['rows' => $last]);
        $changed = $service->build($area);
        $this->assertFalse($changed['rows'][6]['complete']);
        $this->assertStringContainsString('Repeated identity', $changed['rows'][6]['status']);
        $this->assertFalse(app(ShortHistoryForecast::class)->evaluate($changed['rows'])['ready']);
    }
}
