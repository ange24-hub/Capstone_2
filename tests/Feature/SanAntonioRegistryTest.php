<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use App\Support\SanAntonioWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanAntonioRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_out_residents_appear_in_the_correct_registry_pages(): void
    {
        $barangay = Barangay::where('name', 'San Antonio')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => '1']);
        $plan = SanAntonioWorkbook::plan([
            'CONSOLIDATED RBI' => [3 => ['O' => 'SAN ANTONIO']], 'DECEASED' => [], 'NEW' => [],
            'OUT' => [
                3 => ['A' => '1', 'B' => 'Example', 'C' => 'ElsewherePerson'],
                4 => ['A' => '1', 'B' => 'Example', 'C' => 'TransferredPerson', 'P' => 'TRANSFERRED TO BOGO'],
            ],
        ]);
        foreach (['active', 'moved'] as $group) foreach ($plan[$group] as $row) {
            Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $household->id,
                'last_name' => $row['C'], 'first_name' => $row['D'], 'sex' => '', 'remarks' => $row['Q'],
                'status' => $group === 'active' ? Inhabitant::STATUS_ACTIVE : Inhabitant::STATUS_MIGRATED_OUT,
                'residence_status' => SanAntonioWorkbook::residence($row)[0]]);
        }
        $this->actingAs($staff)->get(route('barangay.residence.index', ['scope' => 'living_elsewhere']))
            ->assertOk()->assertSee('ElsewherePerson')->assertDontSee('TransferredPerson');
        $this->get(route('barangay.registry.moved-out'))->assertOk()
            ->assertSee('TransferredPerson')
            ->assertViewHas('residents', fn ($rows) => $rows->total() === 1 && $rows->first()->first_name === 'TransferredPerson');
        $this->get(route('barangay.registry.active'))->assertOk()
            ->assertSee('ElsewherePerson')->assertDontSee('TransferredPerson');
        $this->get(route('barangay.registry.new-inhabitants'))->assertOk()->assertSee('Save Monthly Report');
    }
}
