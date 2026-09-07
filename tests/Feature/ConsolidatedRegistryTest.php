<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_household_counts_empty_households_and_excludes_archived_records(): void
    {
        $barangay = Barangay::where('name', 'Canlupao')->firstOrFail();
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        foreach ([Inhabitant::STATUS_ACTIVE => 'ConsolidatedResident', Inhabitant::STATUS_INACTIVE => 'ArchivedResident', Inhabitant::STATUS_MIGRATED_OUT => 'DepartedResident'] as $status => $name) {
            $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => $status]);
            Inhabitant::create([
                'barangay_id' => $barangay->id, 'household_id' => $household->id,
                'first_name' => $name, 'last_name' => 'Example', 'sex' => 'Female', 'status' => $status,
            ]);
        }

        Household::create(['barangay_id' => $barangay->id, 'household_number' => '205']);
        $this->actingAs($secretary);
        foreach ([route('barangay.registry.active'), route('registry.index', ['source' => 'CANLUPAO.xlsx'])] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('CONSOLIDATED HOUSEHOLD RECORD')
                ->assertSee('ConsolidatedResident')->assertDontSee('ArchivedResident')->assertDontSee('DepartedResident')
                ->assertViewHas('registryHouseholdCount', 2)
                ->assertViewHas('inhabitants', fn ($records) => $records->total() === 1);
        }
        $this->assertDatabaseCount('inhabitants', 3);
        $this->get(route('registry.index'))->assertOk()->assertSee('ArchivedResident')->assertSee('DepartedResident');
    }

    public function test_resident_numbers_continue_on_the_next_page(): void
    {
        $barangay = Barangay::where('name', 'Canlupao')->firstOrFail();
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => '1']);
        for ($number = 1; $number <= 26; $number++) {
            Inhabitant::create([
                'barangay_id' => $barangay->id, 'household_id' => $household->id,
                'first_name' => 'Resident'.$number, 'last_name' => 'Example',
                'sex' => 'Female', 'status' => Inhabitant::STATUS_ACTIVE,
            ]);
        }
        $this->actingAs($secretary)->get(route('barangay.registry.active', ['page' => 2]))
            ->assertOk()->assertSee('Resident No.')->assertSee('Residents: 26')
            ->assertSee('<td class="resident-row-number">26</td>', false);
    }
}
