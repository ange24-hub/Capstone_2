<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\User;
use App\Services\MigrationForecastHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RbiFirstSaveForecastSourceTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private function arrival(string $first, string $type = 'in_migration'): array
    {
        return ['household_head' => 'Example Household', 'first_name' => $first, 'last_name' => 'Example',
            'birth_date' => '1990-01-01', 'sex' => 'Female', 'entry_type' => $type,
            'previous_residence' => $type === 'in_migration' ? 'Cebu City' : '',
            'arrival_month' => $type === 'in_migration' ? '2026-03' : ''];
    }

    public function test_first_save_month_is_used_once_and_new_rows_get_their_own_save_month(): void
    {
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-04-10 10:00:00'));
        $area = Barangay::where('name', 'Cawayan')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->actingAs($staff)->post(route('barangay.rbi-updates.store'), [
            'form_section' => 'residents', 'reporting_month' => '2026-03',
            'rows' => [$this->arrival('First')],
        ])->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::sole();
        $first = $report->rows[0];
        $this->assertNotEmpty($first['source_row_id']);
        $this->assertSame('2026-04', Carbon::parse($first['first_saved_at'])->timezone('Asia/Manila')->format('Y-m'));
        $source = app(MigrationForecastHistory::class)->build($area->id, Carbon::parse('2026-04-01'), 1, 'in');
        $this->assertSame('classified_rbi_first_save_dates', $source['source']);
        $this->assertSame(1, $source['rows'][0]['count']);
        $this->assertSame(1, $source['rows'][0]['source_reports'][0]['rows_first_saved_this_month']);

        $this->travelTo(Carbon::parse('2026-05-05 10:00:00'));
        $this->post(route('barangay.rbi-updates.submit', $report), [
            'attested_by' => 'Example Captain',
            'prepared_signature_data' => self::SIGNATURE, 'attested_signature_data' => self::SIGNATURE,
        ])->assertSessionHasNoErrors();
        $submittedAt = $report->fresh()->submitted_at;
        $this->assertSame('2026-05', $submittedAt->format('Y-m'));
        $this->assertSame([1, 0], array_column(app(MigrationForecastHistory::class)->build($area->id, Carbon::parse('2026-05-01'), 2, 'in')['rows'], 'count'));

        $this->travelTo(Carbon::parse('2026-05-06 10:00:00'));
        $this->put(route('barangay.rbi-updates.update', $report), [
            'form_section' => 'residents', 'reporting_month' => '2026-03',
            'rows' => [array_replace($this->arrival('First'), ['source_row_id' => $first['source_row_id']]),
                $this->arrival('Second'), $this->arrival('Born', 'birth'), $this->arrival('Late', 'late_registration')],
        ])->assertSessionHasNoErrors();
        $updated = $report->fresh();
        $this->assertSame($first['source_row_id'], $updated->rows[0]['source_row_id']);
        $this->assertSame($first['first_saved_at'], $updated->rows[0]['first_saved_at']);
        $this->assertSame('2026-05', Carbon::parse($updated->rows[1]['first_saved_at'])->timezone('Asia/Manila')->format('Y-m'));
        $this->assertTrue($submittedAt->equalTo($updated->submitted_at));
        $this->assertSame([1, 1], array_column(app(MigrationForecastHistory::class)->build($area->id, Carbon::parse('2026-05-01'), 2, 'in')['rows'], 'count'));
        $months = app(MigrationForecastHistory::class)->build($area->id, Carbon::parse('2026-05-01'), 2, 'in')['rows'];
        $this->assertSame(1, $months[0]['source_reports'][0]['arrivals_this_month']);
        $this->assertSame(3, $months[1]['source_reports'][0]['rows_first_saved_this_month']);
        $this->assertSame(1, $months[1]['source_reports'][0]['arrivals_this_month']);
        $this->assertSame(3, $months[1]['source_reports'][0]['save_batches'][0]['entries']);
        $this->assertSame(1, $months[1]['source_reports'][0]['save_batches'][0]['arrivals']);
        $this->assertNotNull($months[0]['source_reports'][0]['submitted_at']);
        $this->travelTo(Carbon::parse('2026-07-05 10:00:00'));
        $admin = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($admin)->get(route('prediction.migration', ['barangay_id' => $area->id]))
            ->assertOk()->assertViewHas('forecasts', fn ($f) => $f['in']['method'] === 'population_baseline');
        $this->assertDatabaseCount('migration_logs', 0); // RBI first-save dates are not physical movement dates.
    }

    public function test_a_month_between_saved_reports_is_zero_without_recounting_residents(): void
    {
        $this->travelTo(Carbon::parse('2026-04-10 10:00:00'));
        $area = Barangay::where('name', 'Cawayan')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->actingAs($staff)->post(route('barangay.rbi-updates.store'), [
            'form_section' => 'residents', 'reporting_month' => '2026-03', 'rows' => [$this->arrival('April')],
        ])->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));
        $this->post(route('barangay.rbi-updates.store'), [
            'form_section' => 'residents', 'reporting_month' => '2026-06',
            'rows' => [array_replace($this->arrival('June'), ['arrival_month' => '2026-06'])],
        ])->assertSessionHasNoErrors();
        $source = app(MigrationForecastHistory::class)->build($area->id, Carbon::parse('2026-05-01'), 2, 'in');
        $this->assertSame([1, 0], array_column($source['rows'], 'count'));
        $this->assertTrue($source['rows'][1]['verified']);
        $this->assertSame('No saved report — zero entries', $source['rows'][1]['status']);
    }

    public function test_classifying_a_saved_row_later_keeps_its_original_save_month(): void
    {
        $this->travelTo(Carbon::parse('2026-04-10 10:00:00'));
        $area = Barangay::where('name', 'Cawayan')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->actingAs($staff)->post(route('barangay.rbi-updates.store'), [
            'form_section' => 'residents', 'reporting_month' => '2026-03',
            'rows' => [array_replace($this->arrival('Later'), ['entry_type' => '', 'arrival_month' => '', 'previous_residence' => ''])],
        ])->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::sole();
        $original = $report->rows[0];
        $this->assertFalse(app(MigrationForecastHistory::class)->build($area->id, Carbon::parse('2026-04-01'), 1, 'in')['rows'][0]['verified']);

        $this->travelTo(Carbon::parse('2026-05-08 10:00:00'));
        $this->put(route('barangay.rbi-updates.update', $report), [
            'form_section' => 'residents', 'reporting_month' => '2026-03',
            'rows' => [array_replace($this->arrival('Later'), ['source_row_id' => $original['source_row_id']])],
        ])->assertSessionHasNoErrors();
        $this->assertSame($original['first_saved_at'], $report->fresh()->rows[0]['first_saved_at']);
        $this->assertSame([1, 0], array_column(app(MigrationForecastHistory::class)->build($area->id, Carbon::parse('2026-05-01'), 2, 'in')['rows'], 'count'));
    }
}
