<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, User, BarangayRbiUpdate};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbiResidentEditTest extends TestCase
{
    use RefreshDatabase;

    private function resident(string $barangayName): Inhabitant
    {
        $area = Barangay::where('name', $barangayName)->firstOrFail();
        $house = Household::create(['barangay_id' => $area->id, 'household_number' => '10', 'purok' => 'Existing Purok']);
        return Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $house->id, 'first_name' => 'Original', 'last_name' => 'Resident',
            'sex' => 'Female', 'status' => Inhabitant::STATUS_ACTIVE, 'family_number' => '10.1', 'civil_status' => 'Existing custom value', 'occupation' => 'Teacher',
            'remarks' => 'Old remark [Source: LOOC.xlsx]', 'residence_status' => Inhabitant::RESIDENCE_HERE]);
    }

    public function test_edit_action_opens_rbi_form_and_save_updates_same_consolidated_resident(): void
    {
        foreach (['Looc', 'Biasong'] as $name) {
            $resident = $this->resident($name);
            $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $resident->barangay_id]);
            $url = route('barangay.rbi-updates.index', ['edit_resident' => $resident->id]);
            $this->actingAs($staff)->get(route('barangay.registry.active'))->assertOk()->assertSee($url)->assertSee('Action')->assertSee('Click Edit');
            $count = Inhabitant::count();
            $household = $resident->household->toArray();
            $this->get($url)->assertOk()->assertSee('Edit registered resident')->assertSee('Existing custom value')->assertSee('Save RBI Changes');
            $this->assertSame($count, Inhabitant::count());
            $response = $this->put(route('barangay.rbi-residents.update', $resident), [
                'record_version' => (string) $resident->updated_at, 'first_name' => 'Corrected', 'last_name' => 'Resident', 'sex' => 'Female',
                'civil_status' => 'Married', 'religion' => 'Roman Catholic', 'education_level' => 'College graduate', 'remarks' => 'Updated remark',
                'barangay_id' => 999999, 'household_id' => 999999, 'status' => Inhabitant::STATUS_MIGRATED_OUT,
            ])->assertSessionHasNoErrors()->assertRedirect(route('barangay.registry.active', ['search' => 'Resident']));
            $resident->refresh();
            $this->assertSame('Corrected', $resident->first_name);
            $this->assertSame('Married', $resident->civil_status);
            $this->assertSame('10.1', $resident->family_number);
            $this->assertSame('Teacher', $resident->occupation);
            $this->assertSame(Inhabitant::STATUS_ACTIVE, $resident->status);
            $this->assertSame(Inhabitant::RESIDENCE_HERE, $resident->residence_status);
            $this->assertStringContainsString('[Source: LOOC.xlsx]', $resident->remarks);
            $this->assertSame($household, $resident->household()->first()->toArray());
            $this->assertSame($count, Inhabitant::count());
            $this->assertSame(0, BarangayRbiUpdate::count());
            $this->get($response->headers->get('Location'))->assertOk()->assertSee('Corrected')->assertSee('College graduate');
        }
    }

    public function test_cross_barangay_stale_and_invalid_edits_do_not_change_records(): void
    {
        $resident = $this->resident('Looc');
        $other = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => Barangay::where('name', 'Canlupao')->value('id')]);
        $url = route('barangay.rbi-updates.index', ['edit_resident' => $resident->id]);
        $save = route('barangay.rbi-residents.update', $resident);
        $data = ['first_name' => 'Changed', 'last_name' => 'Resident', 'sex' => 'Female', 'record_version' => (string) $resident->updated_at];
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->put($save, $data)->assertForbidden();
        $owner = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $resident->barangay_id]);
        $this->actingAs($owner)->put($save, array_replace($data, ['birth_date' => 'not a date']))->assertSessionHasErrors('birth_date');
        $this->put($save, array_replace($data, ['record_version' => 'old version']))->assertSessionHasErrors('record_version');
        $this->assertSame('Original', $resident->fresh()->first_name);
        $resident->update(['status' => Inhabitant::STATUS_MIGRATED_OUT]);
        $this->get($url)->assertStatus(409);
        $this->put($save, $data)->assertStatus(409);
        $regular = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        $this->actingAs($regular)->get($url)->assertForbidden();
        $this->put($save, $data)->assertForbidden();
    }
}
