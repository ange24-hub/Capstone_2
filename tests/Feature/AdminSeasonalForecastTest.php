<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\MigrationReportingPeriod;
use App\Models\RbiArrivalPeriod;
use App\Models\RegistryActivity;
use App\Models\User;
use App\Services\MigrationForecastHistory;
use App\Services\MigrationForecastReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\SubmittedArrivalFixture;
use Tests\TestCase;

class AdminSeasonalForecastTest extends TestCase
{
    use RefreshDatabase, SubmittedArrivalFixture;

    public function test_municipal_entry_point_now_renders_statistical_forecasts_without_issuing_on_get(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($admin)->get(route('municipal.prediction-forecast'))->assertOk()
            ->assertViewIs('barangay.dashboard.prediction')->assertSee('Weighted Moving Average')
            ->assertViewMissing('predictedOutMigration')->assertDontSee('SVR');
        $this->assertDatabaseCount('forecast_logs', 0);
    }

    private function history(array $in, array $out, string $start = '2025-04'): array
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $owner = User::factory()->create(['name' => 'Seasonal Secretary Fixture', 'role' => 'barangay', 'barangay_id' => $area->id]);
        $home = Household::create(['barangay_id' => $area->id, 'household_number' => 'SEASONAL-INTEGRATION']);
        $person = Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $home->id, 'first_name' => 'Private', 'last_name' => 'Fixture', 'sex' => 'Female']);
        $this->submittedArrivals($area, $owner, $start, $in, false);
        $last = BarangayRbiUpdate::orderByDesc('reporting_month')->firstOrFail();
        $last->update(['rows' => array_merge($last->rows, [
            ['first_name' => 'Newborn', 'last_name' => 'Excluded', 'birth_date' => $last->reporting_month->format('Y-m-d'), 'entry_type' => 'birth'],
            ['first_name' => 'Late', 'last_name' => 'Excluded', 'birth_date' => '1990-01-01', 'entry_type' => 'late_registration'],
        ])]);
        $this->reviewArrivals($area, $owner);
        foreach ($out as $i => $count) {
            $month = Carbon::createFromFormat('!Y-m', $start)->addMonths($i);
            for ($j = 0; $j < $count; $j++) {
                MigrationRecord::create(['barangay_id' => $area->id, 'inhabitant_id' => $person->id, 'type' => 'out', 'movement_date' => $month->copy()->addDays($j)->toDateString()]);
            }
            $source = app(MigrationForecastHistory::class)->build($area->id, $month, 1)['rows'][0];
            MigrationReportingPeriod::create(['barangay_id' => $area->id, 'month' => $month->format('Y-m'), 'out_count' => $count,
                'source_fingerprint' => $source['fingerprint'], 'verified_by' => $owner->id, 'verified_at' => now()]);
        }

        return [$area, $owner, $person];
    }




}
