<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, MigrationLog, MigrationRecord, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ForecastMovementDateTest extends TestCase
{
    use RefreshDatabase;

    public static function calendarBoundaries(): array
    {
        return [
            'before Philippine midnight'=>['2026-11-10 15:59:59','2026-11-10','2026-11-11'],
            'at Philippine midnight'=>['2026-11-10 16:00:00','2026-11-11','2026-11-12'],
            'semester boundary'=>['2026-06-30 17:00:00','2026-07-01','2026-07-02'],
            'year boundary'=>['2026-12-31 17:00:00','2027-01-01','2027-01-02'],
        ];
    }

    #[DataProvider('calendarBoundaries')]
    public function test_form_and_validation_use_the_same_philippine_day_without_changing_recorded_dates(string $instant, string $today, string $future): void
    {
        $this->travelTo(Carbon::parse($instant, 'UTC'));
        $this->assertSame('UTC', config('app.timezone'));
        $area = Barangay::where('name','Hugpa')->firstOrFail();
        $staff = User::factory()->create(['role'=>User::ROLE_BARANGAY, 'barangay_id'=>$area->id]);
        $household = Household::create(['barangay_id'=>$area->id, 'household_number'=>'TIMEZONE-1']);
        $person = Inhabitant::create(['barangay_id'=>$area->id, 'household_id'=>$household->id,
            'first_name'=>'Date', 'last_name'=>'Fixture', 'status'=>'active', 'sex'=>'Female']);
        $this->actingAs($staff)->get(route('prediction.migration'))->assertOk()->assertSee('max="'.$today.'"', false);
        $payload = ['resident_id'=>$person->id, 'migration_type'=>'in', 'confirm_date'=>'1'];
        $this->post(route('prediction.movements.store'), $payload + ['transaction_date'=>$today])
            ->assertSessionHasNoErrors()->assertRedirect(route('prediction.migration', ['barangay_id'=>$area->id]));
        $this->assertSame($today, MigrationRecord::sole()->movement_date->toDateString());
        $this->assertSame($today, MigrationLog::sole()->transaction_date->toDateString());
        $this->assertSame(MigrationRecord::sole()->id, MigrationLog::sole()->source_record_id);
        $this->post(route('prediction.movements.store'), $payload + ['transaction_date'=>$future])
            ->assertSessionHasErrors('transaction_date');
        $this->assertDatabaseCount('migration_records', 1);
        $this->assertDatabaseCount('forecast_logs', 0);
    }

    public function test_default_population_report_period_matches_its_philippine_observation_date(): void
    {
        $area = Barangay::where('name','Hugpa')->firstOrFail();
        $staff = User::factory()->create(['role'=>User::ROLE_BARANGAY, 'barangay_id'=>$area->id]);
        $admin = User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU, 'barangay_id'=>null]);
        foreach ([['2026-06-30 17:00:00',2026,2], ['2026-12-31 17:00:00',2027,1]] as [$instant,$year,$semester]) {
            $this->travelTo(Carbon::parse($instant, 'UTC'));
            $response = $this->actingAs($staff)->get(route('reports.population'))->assertOk();
            $this->assertSame($year, $response->viewData('report')['monitoring']['year']);
            $this->assertSame($semester, $response->viewData('report')['monitoring']['semester']);
            $this->actingAs($admin)->get(route('reports.population'))->assertRedirect(route('reports.population.submissions.index',
                ['report_year'=>$year,'semester'=>$semester]));
        }
        $this->assertDatabaseCount('population_report_submissions', 0);
    }
}
