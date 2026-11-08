<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\NewInhabitant;
use App\Models\User;
use App\Services\MigrationForecastHistory;
use App\Services\RbiArrivalReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RbiArrivalPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function source(): array
    {
        $this->travelTo(Carbon::parse('2026-11-07 12:00:00'));
        $area = Barangay::where('name', 'Cawayan')->firstOrFail();
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        $reports = [];
        foreach (['2026-02', '2026-03', '2026-04'] as $i => $month) {
            $reports[] = BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => $area->name, 'reporting_month' => $month.'-01', 'status' => 'submitted', 'submitted_at' => now(),
                'rows' => [['first_name' => 'Arrival'.$i, 'last_name' => 'Example', 'birth_date' => '1990-01-01', 'entry_type' => 'in_migration', 'previous_residence' => 'Cebu', 'arrival_date' => $month.'-01'], ['first_name' => 'Born'.$i, 'last_name' => 'Example', 'birth_date' => $month.'-02', 'entry_type' => 'birth']]]);
        }

        return [$area, $staff, $reports];
    }

    private function payload($area): array
    {
        $p = app(RbiArrivalReview::class)->periods($area)[0];

        return ['start_month' => $p['start'], 'fingerprint' => $p['fingerprint'], 'counts' => ['2026-02' => 1, '2026-03' => 1, '2026-04' => 0], 'confirmed' => 1];
    }

    public function test_period_starts_with_first_submitted_month_and_supplies_only_reviewed_arrivals(): void
    {
        [$area,$staff,$reports] = $this->source();
        $service = app(RbiArrivalReview::class);
        $period = $service->periods($area)[0];
        $this->assertSame('2026-02', $period['start']);
        $this->assertSame('2026-04', $period['end']);
        $this->assertSame([1, 1, 1], array_column($period['months'], 'eligible'));
        $before = app(MigrationForecastHistory::class)->build($area->id, Carbon::parse('2026-04-01'), 3, 'in');
        $this->assertCount(0, $before['consecutive']);
        $this->actingAs($staff)->post(route('rbi.arrivals.store'), $this->payload($area))->assertSessionHasNoErrors();
        $after = $service->history($area->id, Carbon::parse('2026-04-01'), 3);
        $this->assertSame([1, 1, 0], array_column($after['rows'], 'count'));
        $this->assertCount(3, $after['consecutive']);
        $this->assertSame('classified_rbi_first_save_dates', app(MigrationForecastHistory::class)->build($area->id, Carbon::parse('2026-04-01'), 3, 'in')['source']);
        $this->assertDatabaseCount('migration_records', 0);
        $this->get(route('rbi.arrivals.index'))->assertOk()->assertSee('2 confirmed arrivals');
        $reports[0]->update(['rows' => [['first_name' => 'Changed', 'last_name' => 'Source']]]);
        $changed = $service->history($area->id, Carbon::parse('2026-04-01'), 3);
        $this->assertCount(0, $changed['consecutive']);
    }

    public function test_missing_draft_or_stale_sources_and_excess_counts_cannot_be_confirmed(): void
    {
        [$area,$staff,$reports] = $this->source();
        $payload = $this->payload($area);
        $this->actingAs($staff)->post(route('rbi.arrivals.store'), array_replace($payload, ['counts' => ['2026-02' => 2, '2026-03' => 1, '2026-04' => 0]]))->assertSessionHasErrors('counts');
        $reports[1]->update(['status' => 'draft']);
        $this->post(route('rbi.arrivals.store'), $payload)->assertSessionHasErrors('period');
        $this->assertDatabaseCount('rbi_arrival_periods', 0);
        $other = User::factory()->create(['role' => 'barangay', 'barangay_id' => Barangay::where('name', 'Looc')->firstOrFail()->id]);
        $this->actingAs($other)->post(route('rbi.arrivals.store'), $payload + ['barangay_id' => $area->id])->assertForbidden();
        $resident = User::factory()->create(['role' => 'resident']);
        $this->actingAs($resident)->get(route('rbi.arrivals.index'))->assertForbidden();
    }

    public function test_same_person_in_two_months_is_counted_once_in_three_month_period(): void
    {
        [$area,$staff,$reports] = $this->source();
        $rows = $reports[0]->rows;
        $rows[0]['arrival_date'] = '2026-03-01';
        $reports[1]->update(['rows' => $rows]);
        $period = app(RbiArrivalReview::class)->periods($area)[0];
        $this->assertSame([1, 0, 1], array_column($period['months'], 'eligible'));
        $this->assertSame([1, 2, 1], array_column($period['months'], 'excluded'));
    }

    public function test_missing_month_between_reports_is_zero_and_later_draft_invalidates_review(): void
    {
        [$area, $staff, $reports] = $this->source();
        $this->actingAs($staff)->post(route('rbi.arrivals.store'), $this->payload($area))->assertSessionHasNoErrors();
        $reports[1]->delete();
        $service = app(RbiArrivalReview::class);
        $period = $service->periods($area)[0];
        $this->assertTrue($period['complete']);
        $this->assertSame([false, true, false], array_column($period['months'], 'inferred_zero'));
        $page = $this->get(route('rbi.arrivals.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="counts\[2026-03\]"[^>]*value="0"[^>]*readonly/', $page);
        $payload = ['start_month' => $period['start'], 'fingerprint' => $period['fingerprint'], 'counts' => ['2026-02' => 1, '2026-03' => 0, '2026-04' => 1], 'confirmed' => 1];
        $this->actingAs($staff)->post(route('rbi.arrivals.store'), $payload)->assertSessionHasNoErrors();
        $history = $service->history($area->id, Carbon::parse('2026-04-01'), 3);
        $this->assertSame([1, 0, 1], array_column($history['consecutive'], 'count'));
        $this->get(route('rbi.arrivals.index'))->assertOk()->assertSee('No report — zero new entries');
        $reports[1]->replicate()->fill(['status' => 'draft', 'submitted_at' => null])->save();
        $changed = $service->periods($area)[0];
        $this->assertFalse($changed['complete']);
        $this->assertFalse($changed['verified']);
        $this->assertFalse($changed['months'][1]['inferred_zero']);
        $this->post(route('rbi.arrivals.store'), $payload)->assertSessionHasErrors('period');
    }

    public function test_cawayan_history_gaps_are_zero_but_saved_entries_and_trailing_months_are_not(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07'));
        $area = Barangay::where('name', 'Cawayan')->firstOrFail();
        foreach (['2021-03', '2021-05', '2021-06', '2021-09'] as $i => $month) {
            NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => (string) ($i + 1), 'reporting_month' => $month.'-01', 'first_name' => 'History'.$i, 'last_name' => 'Example']);
        }
        NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => '5', 'first_name' => 'Undated', 'last_name' => 'Example']);
        NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => '9', 'reporting_month' => '2026-12-01', 'first_name' => 'Future', 'last_name' => 'Example']);
        $service = app(RbiArrivalReview::class);
        $periods = $service->periods($area);
        $this->assertSame(['2021-03', '2021-06', '2021-09'], array_column($periods, 'start'));
        $this->assertSame([false, true, false], array_column($periods[0]['months'], 'inferred_zero'));
        $this->assertSame([false, true, true], array_column($periods[1]['months'], 'inferred_zero'));
        $this->assertSame([false, false, false], array_column($periods[2]['months'], 'inferred_zero'));
        $this->assertFalse($periods[0]['months'][0]['available']);
        $this->assertFalse($periods[0]['complete']);
        $this->assertSame(1, $periods[0]['months'][0]['history_entries']);
        $this->assertSame('No later report yet', $periods[2]['months'][1]['source_status']);
        // Saving an entry in a formerly empty month removes the inferred zero.
        NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => '6', 'reporting_month' => '2021-04-01', 'first_name' => 'New', 'last_name' => 'History']);
        $changed = $service->periods($area)[0];
        $this->assertFalse($changed['months'][1]['inferred_zero']);
        $this->assertNotSame($periods[0]['fingerprint'], $changed['fingerprint']);
    }

    public function test_new_history_entries_cannot_be_omitted_from_a_previously_submitted_month(): void
    {
        [$area, $staff, $reports] = $this->source();
        $service = app(RbiArrivalReview::class);
        $this->actingAs($staff)->post(route('rbi.arrivals.store'), $this->payload($area))->assertSessionHasNoErrors();
        $record = NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => '9', 'reporting_month' => '2026-02-01', 'first_name' => 'Additional', 'last_name' => 'History']);
        $changed = $service->periods($area)[0];
        $this->assertFalse($changed['verified']);
        $this->assertFalse($changed['complete']);
        $this->assertSame(1, $changed['months'][0]['unclassified']);
        $rows = $reports[0]->rows;
        $rows[] = ['history_record_id' => $record->id, 'first_name' => 'Additional', 'last_name' => 'History', 'entry_type' => 'late_registration'];
        $reports[0]->update(['rows' => $rows]);
        $ready = $service->periods($area)[0];
        $this->assertTrue($ready['complete']);
        $this->assertFalse($ready['verified']);
        $this->assertSame(1, $ready['months'][0]['eligible']);
        $this->assertSame(0, $ready['months'][0]['unclassified']);
        $this->post(route('rbi.arrivals.store'), $this->payload($area))->assertSessionHasNoErrors();
        $this->assertTrue($service->periods($area)[0]['verified']);
    }
}
