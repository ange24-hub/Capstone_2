<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\DeceasedInhabitant;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MagataRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_household_numbers_do_not_inflate_the_household_count(): void
    {
        $barangay = Barangay::where('name', 'Mag-ata')->firstOrFail();
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        Household::create(['barangay_id' => $barangay->id, 'household_number' => '1']);
        $unassigned = Household::create(['barangay_id' => $barangay->id, 'household_number' => 'Not recorded']);
        Inhabitant::create([
            'barangay_id' => $barangay->id, 'household_id' => $unassigned->id,
            'last_name' => 'Example', 'first_name' => 'AwayResident', 'sex' => '',
            'remarks' => 'AT MANILA', 'status' => Inhabitant::STATUS_ACTIVE,
        ]);
        $this->actingAs($secretary)->get(route('barangay.registry.active'))
            ->assertOk()->assertViewHas('registryHouseholdCount', 1)->assertSee('AwayResident');
        $this->get(route('barangay.registry.moved-out'))->assertOk()
            ->assertViewHas('residents', fn ($residents) => $residents->total() === 0);
    }

    public function test_magata_has_workbook_pages_and_can_save_monthly_families(): void
    {
        $barangay = Barangay::where('name', 'Mag-ata')->firstOrFail();
        $barangay->update(['secretary_name' => 'Magata Secretary', 'punong_barangay_name' => 'Magata Chair']);
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => '1']);
        Inhabitant::create([
            'barangay_id' => $barangay->id, 'household_id' => $household->id,
            'last_name' => 'Example', 'first_name' => 'MagataResident',
            'sex' => '', 'status' => Inhabitant::STATUS_ACTIVE,
        ]);
        DeceasedInhabitant::create([
            'barangay_id' => $barangay->id, 'household_number' => '2',
            'last_name' => 'Example', 'first_name' => 'HistoricalResident',
        ]);

        $this->actingAs($secretary)->get(route('dashboard.barangay'))
            ->assertOk()
            ->assertSee(route('barangay.registry.new-inhabitants'))
            ->assertSee(route('barangay.registry.deceased'));
        $this->get(route('barangay.registry.active'))->assertOk()
            ->assertSee('MAG-ATA')->assertSee('MagataResident')->assertSee('Not provided');
        $this->get(route('barangay.registry.deceased'))->assertOk()
            ->assertSee('HistoricalResident');
        $this->get(route('barangay.registry.new-inhabitants'))->assertOk()
            ->assertSee('MAG-ATA')->assertSee('Save Monthly Report')
            ->assertSee('Magata Secretary')->assertSee('Magata Chair')
            ->assertDontSee('NENA E. GONZAGA')->assertDontSee('MARY GRACE M. POLISTICO')->assertDontSee('MARCOS L. MAQUILANG');

        $this->post(route('registry.new-inhabitant-monthly-reports.store'), [
            'reporting_month' => '2026-09',
            'families' => [[
                'household_number' => '3',
                'members' => [['last_name' => 'Example', 'first_name' => 'NewResident', 'sex' => 'Male']],
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('new_inhabitants', [
            'barangay_id' => $barangay->id, 'household_number' => '3', 'first_name' => 'NewResident',
        ]);

        $otherSecretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', 'Cabascan')->firstOrFail()->id,
        ]);
        $this->actingAs($otherSecretary)->get(route('barangay.registry.active'))
            ->assertOk()->assertDontSee('MagataResident');
        $this->get(route('barangay.registry.deceased'))->assertOk()->assertDontSee('HistoricalResident');
        $this->get(route('barangay.registry.new-inhabitants'))->assertOk()->assertDontSee('NewResident');
    }
}
