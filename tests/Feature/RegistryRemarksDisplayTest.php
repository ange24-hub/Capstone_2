<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\DeceasedInhabitant;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistryRemarksDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_edits_keep_internal_source_references(): void
    {
        $barangay = Barangay::where('name', 'San Roque')->firstOrFail();
        $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => '1']);
        $resident = Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $household->id,
            'last_name' => 'Example', 'first_name' => 'Person', 'sex' => 'Male', 'status' => 'active',
            'remarks' => 'Cebu [Source: SAN ROQUE.xlsx CONSOLIDATED RBI row 243]']);
        $resident->update(['remarks' => 'Manila']);
        $this->assertSame('Manila', \App\Support\RegistryRemarks::display($resident->fresh()->remarks));
        $this->assertStringContainsString('[Source: SAN ROQUE.xlsx CONSOLIDATED RBI row 243]', $resident->fresh()->remarks);
        $resident->update(['remarks' => null]);
        $this->assertSame('', \App\Support\RegistryRemarks::display($resident->fresh()->remarks));
    }

    public function test_residence_pages_household_totals_monthly_reports_and_barangay_isolation(): void
    {
        $barangay = Barangay::where('name', 'San Roque')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        Household::create(['barangay_id' => $barangay->id, 'household_number' => '1']);
        $unknown = Household::create(['barangay_id' => $barangay->id, 'household_number' => 'Not recorded']);
        foreach (['HerePerson' => 'living_here', 'OverseasPerson' => 'living_elsewhere', 'CebuPerson' => 'unconfirmed'] as $name => $residence) {
            Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $unknown->id,
                'last_name' => 'Example', 'first_name' => $name, 'sex' => 'Female',
                'remarks' => ($name === 'OverseasPerson' ? 'Cebu ' : '').'[Source: SAN ROQUE.xlsx CONSOLIDATED RBI row 243]', 'status' => 'active', 'residence_status' => $residence]);
        }
        DeceasedInhabitant::create(['barangay_id' => $barangay->id, 'household_number' => '1', 'last_name' => 'Example', 'first_name' => 'HistoricalPerson']);
        $this->actingAs($staff)->get(route('barangay.registry.active'))->assertOk()
            ->assertViewHas('registryHouseholdCount', 1)->assertSee('HerePerson')->assertSee('OverseasPerson')->assertSee('CebuPerson')->assertDontSee('[Source:')->assertSee('value="Cebu"', false);
        foreach (['living_here' => 'HerePerson', 'living_elsewhere' => 'OverseasPerson', 'unconfirmed' => 'CebuPerson'] as $scope => $name) {
            $this->get(route('barangay.residence.index', ['scope' => $scope]))->assertOk()
                ->assertDontSee('[Source:')->assertViewHas('residents', fn ($rows) => $rows->total() === 1 && $rows->first()->first_name === $name);
        }
        $this->get(route('barangay.registry.moved-out'))->assertOk()->assertDontSee('[Source:')->assertViewHas('residents', fn ($rows) => $rows->total() === 0);
        $this->get(route('barangay.registry.deceased'))->assertOk()->assertSee('HistoricalPerson');
        $this->get(route('barangay.registry.new-inhabitants'))->assertOk()->assertSee('Save Monthly Report');
        $this->post(route('registry.new-inhabitant-monthly-reports.store'), [
            'reporting_month' => '2026-09', 'families' => [['household_number' => '2',
                'members' => [['last_name' => 'Example', 'first_name' => 'NewPerson', 'sex' => 'Male']]]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('new_inhabitants', ['barangay_id' => $barangay->id, 'first_name' => 'NewPerson']);
        $otherStaff = User::factory()->create(['role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', 'San Antonio')->firstOrFail()->id]);
        $this->actingAs($otherStaff)->get(route('barangay.registry.active'))->assertOk()->assertDontSee('HerePerson')->assertDontSee('OverseasPerson');
        $this->get(route('barangay.registry.deceased'))->assertOk()->assertDontSee('HistoricalPerson');
        $this->get(route('barangay.registry.new-inhabitants'))->assertOk()->assertDontSee('NewPerson');
    }
}
