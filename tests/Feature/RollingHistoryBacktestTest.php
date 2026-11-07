<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\RbiArrivalPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SubmittedArrivalFixture;
use Tests\TestCase;

class RollingHistoryBacktestTest extends TestCase
{
    use RefreshDatabase, SubmittedArrivalFixture;

    public function test_cabascan_rolling_page_and_csv_stop_at_may_without_writing_reports(): void
    {
        Storage::fake('local');
        $area = Barangay::where('name', 'Cabascan')->firstOrFail();
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        $this->submittedArrivals($area, $staff, '2020-06', [0, 0, 0, 0, 0, 0, 4, 2, 0, 0, 1, 7, 1], false);
        $before = BarangayRbiUpdate::all()->toArray();
        $admin = User::factory()->create(['role' => 'municipal_lgu']);
        $this->actingAs($admin)->get(route('migration.history-experiment', ['mode' => 'rolling']))->assertOk()
            ->assertSee('Rolling backtesting · Actual vs predicted')->assertSee('December 2020–May 2021')
            ->assertSee('rolling-test-title')->assertSee('2.246')->assertSee('2.268')
            ->assertViewHas('history', fn ($h) => count($h['rows']) === 12 && $h['rows'][0]['month'] === '2020-06' && $h['rows'][11]['month'] === '2021-05')
            ->assertViewHas('evaluation', fn ($e) => $e['test_months'] === 6 && $e['actual_total'] === 14 && $e['backtest'][5]['training_through'] === '2021-04');
        $this->artisan('migration:history-experiment', ['--barangay' => $area->id, '--mode' => 'rolling', '--method' => 'poisson_jeffreys', '--end-month' => '2020-12', '--training-months' => 6, '--horizon' => 6])->assertSuccessful();
        $files = Storage::disk('local')->allFiles('private/forecast-history');
        $jsonPath = collect($files)->first(fn ($file) => str_ends_with($file, '/result.json'));
        $csvPath = collect($files)->first(fn ($file) => str_ends_with($file, '/test-results.csv'));
        $result = json_decode(Storage::disk('local')->get($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('rolling', $result['mode']);
        $this->assertSame('historical_expanding_one_step_backtest_v1', $result['evaluation']['protocol']);
        $this->assertSame('2021-05', $result['evaluation']['test_end']);
        $this->assertSame([6, 7, 8, 9, 10, 11], array_column($result['evaluation']['backtest'], 'training_months'));
        $csv = Storage::disk('local')->get($csvPath);
        $this->assertStringContainsString('mean_rate_baseline', $csv);
        $csvRows = array_map('str_getcsv', explode("\n", trim($csv)));
        $this->assertCount(7, $csvRows);
        $this->assertSame('2020-12', $csvRows[1][0]);
        $this->assertSame('2020-11', $csvRows[1][5]);
        $this->assertSame('2021-05', $csvRows[6][0]);
        $this->assertSame('2021-04', $csvRows[6][5]);
        $this->assertSame($before, BarangayRbiUpdate::all()->toArray());
        $this->assertSame(0, RbiArrivalPeriod::count());
        $elsewhere = Barangay::where('id', '!=', $area->id)->firstOrFail();
        $this->actingAs($staff)->get(route('migration.history-experiment', ['mode' => 'rolling', 'barangay_id' => $elsewhere->id]))->assertForbidden();
        $this->actingAs($admin)->getJson(route('migration.history-experiment', ['mode' => 'rolling', 'method' => 'selected']))->assertUnprocessable();
    }

    public function test_unclassified_test_month_blocks_rolling_instead_of_entering_later_training_as_zero(): void
    {
        Storage::fake('local');
        $area = Barangay::where('name', 'Cabascan')->firstOrFail();
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        $this->submittedArrivals($area, $staff, '2020-06', [0, 0, 0, 0, 0, 0, 4, 2, 0, 0, 1, 7], false);
        $report = BarangayRbiUpdate::where('barangay_name', $area->name)->whereDate('reporting_month', '2021-01-01')->firstOrFail();
        $entries = $report->rows;
        $entries[0]['entry_type'] = '';
        $report->update(['rows' => $entries]);
        $this->actingAs(User::factory()->create(['role' => 'municipal_lgu']))->get(route('migration.history-experiment', ['mode' => 'rolling']))->assertOk()
            ->assertSee('Complete the historical source window first')->assertViewHas('evaluation', fn ($e) => ! $e['ready']);
        $this->artisan('migration:history-experiment', ['--barangay' => $area->id, '--mode' => 'rolling', '--method' => 'poisson_jeffreys', '--end-month' => '2020-12', '--training-months' => 6, '--horizon' => 6])->assertFailed();
        $this->assertSame([], Storage::disk('local')->allFiles('private/forecast-history'));
    }
}
