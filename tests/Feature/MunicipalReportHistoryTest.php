<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\NewInhabitant;
use App\Models\RegistryActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipalReportHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_history_inventory_preserves_records_and_leads_to_source_review(): void
    {
        $area = Barangay::where('name', 'Cabascan')->firstOrFail();
        $owner = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        $record = NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => '1', 'reporting_month' => '2021-05-01', 'first_name' => 'Needs', 'last_name' => 'Review', 'birth_date' => '1990-01-01']);
        BarangayRbiUpdate::create(['barangay_user_id' => $owner->id, 'barangay_name' => $area->name,
            'reporting_month' => '2021-05-01', 'status' => 'draft', 'rows' => [
                ['history_record_id' => $record->id, 'first_name' => 'Needs', 'last_name' => 'Review', 'birth_date' => '1990-01-01'],
            ]]);
        $before = [BarangayRbiUpdate::all()->toArray(), NewInhabitant::all()->toArray(), RegistryActivity::count()];
        $admin = User::factory()->create(['role' => 'municipal_lgu']);
        $this->actingAs($admin)->get(route('municipal.rbi-report-history'))->assertOk()
            ->assertSee('RBI report history')->assertSee(route('municipal.prediction-forecast'))
            ->assertSee(route('municipal.prediction-history', $area))->assertDontSee(route('rbi.arrivals.index', ['barangay_id' => $area->id]))
            ->assertDontSee('Show backtest')->assertDontSee('Training dataset')->assertDontSee('AGE-BASED ESTIMATES')
            ->assertViewHas('history', fn ($h) => $h['area_count'] === 29 && ! $h['age_estimates'] && $h['datasets'][$area->id]['entries']['2021-05'][0]['type'] === null);
        $this->get(route('municipal.prediction-history', [$area, 'month' => '2021-05']))->assertOk()
            ->assertSee('Needs Review')->assertSee(route('municipal.rbi-report-history'));
        $this->assertSame($before, [BarangayRbiUpdate::all()->toArray(), NewInhabitant::all()->toArray(), RegistryActivity::count()]);
        foreach ([$owner, User::factory()->create(['role' => 'resident'])] as $user) {
            $this->actingAs($user)->get(route('municipal.rbi-report-history'))->assertForbidden();
        }
    }

    public function test_operational_pages_do_not_advertise_demo_or_research_screens(): void
    {
        $admin = User::factory()->create(['role' => 'municipal_lgu']);
        $this->actingAs($admin);
        foreach (['migration.dashboard', 'municipal.prediction-forecast', 'municipal.rbi-report-history'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('Demo prediction analysis')
                ->assertDontSee('Historical prediction experiments')->assertDontSee('Rolling backtesting')
                ->assertDontSee(route('migration.demo'))->assertDontSee(route('migration.history-experiment'))
                ->assertDontSee('href="'.route('municipal.prediction-analytics').'"', false);
        }
        $area = Barangay::firstOrFail();
        $this->get(route('rbi.arrivals.index', ['barangay_id' => $area->id]))->assertOk()
            ->assertSee(route('municipal.prediction-forecast', ['barangay_id' => $area->id]))
            ->assertDontSee('historical experiment')->assertDontSee(route('migration.history-experiment'));
    }
}
