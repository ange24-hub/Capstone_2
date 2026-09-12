<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovedOutRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_removes_only_the_selected_resident_and_linked_movements(): void
    {
        $barangay = Barangay::where('name', 'Looc')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $house = Household::create(['barangay_id' => $barangay->id, 'household_number' => '1']);
        $resident = Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $house->id,
            'first_name' => 'Incorrect', 'last_name' => 'Example', 'sex' => 'Female', 'status' => Inhabitant::STATUS_MIGRATED_OUT]);
        $keep = Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $house->id,
            'first_name' => 'Retained', 'last_name' => 'Example', 'sex' => 'Male', 'status' => Inhabitant::STATUS_ACTIVE]);
        $movement = $resident->migrationRecords()->create(['barangay_id' => $barangay->id,
            'type' => MigrationRecord::TYPE_OUT, 'movement_date' => '2026-09-01']);
        $other = User::factory()->create(['role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', 'Higosoan')->firstOrFail()->id]);
        $this->actingAs($other)->delete(route('registry.destroy', $resident), ['return_to' => 'moved-out'])->assertForbidden();
        $this->assertDatabaseHas('inhabitants', ['id' => $resident->id]);
        $this->actingAs($staff)->get(route('barangay.registry.moved-out'))->assertOk()
            ->assertSee('Delete resident')->assertSee('This cannot be undone.')->assertSee('window.confirm', false);
        $this->delete(route('registry.destroy', $resident), ['return_to' => 'moved-out'])
            ->assertRedirect(route('barangay.registry.moved-out'))->assertSessionHas('status', 'Resident and linked migration records deleted.');
        $this->assertDatabaseMissing('inhabitants', ['id' => $resident->id]);
        $this->assertDatabaseMissing('migration_records', ['id' => $movement->id]);
        $this->assertDatabaseHas('inhabitants', ['id' => $keep->id]);
        $this->assertDatabaseHas('households', ['id' => $house->id]);
        $this->get(route('barangay.registry.moved-out'))->assertOk()->assertSee('No moved-out residents found.');
    }

    public function test_imported_transfer_destination_is_shown_without_inventing_a_date(): void
    {
        $barangay = Barangay::where('name', 'Looc')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => 'Not recorded']);
        $resident = Inhabitant::create([
            'barangay_id' => $barangay->id, 'household_id' => $household->id,
            'first_name' => 'ImportedResident', 'last_name' => 'Example', 'sex' => 'Female',
            'status' => Inhabitant::STATUS_MIGRATED_OUT,
            'remarks' => 'TRANSFERRED TO SAN ISIDRO [Source: LOOC.xlsx CONSOLIDATED RBI row 40]',
        ]);
        $this->actingAs($staff)->get(route('barangay.registry.moved-out'))->assertOk()
            ->assertSee('<td>Not recorded</td><td>SAN ISIDRO</td>', false);
        $this->assertDatabaseCount('migration_records', 0);

        $resident->migrationRecords()->create([
            'barangay_id' => $barangay->id, 'type' => MigrationRecord::TYPE_OUT,
            'movement_date' => '2026-09-01', 'destination' => 'Confirmed destination',
        ]);
        $this->get(route('barangay.registry.moved-out'))->assertOk()
            ->assertSee('<td>Confirmed destination</td>', false)
            ->assertDontSee('<td>SAN ISIDRO</td>', false);

        $resident->remarks = 'AT MANILA';
        $this->assertNull($resident->sourceTransferDestination());
        $resident->remarks = 'TRANSFER MASLOG [Source: MAG-ATA.xlsx]';
        $this->assertSame('MASLOG', $resident->sourceTransferDestination());
        $resident->remarks = 'TRANSFERRED TO CEBU [Source: example]';
        $resident->status = Inhabitant::STATUS_ACTIVE;
        $this->assertNull($resident->sourceTransferDestination());
    }

    public function test_departures_are_available_to_any_barangay_and_leave_active_household(): void
    {
        $barangay = Barangay::where('name', 'San Isidro')->firstOrFail();
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => '1']);
        $resident = Inhabitant::create([
            'barangay_id' => $barangay->id, 'household_id' => $household->id,
            'first_name' => 'DepartingResident', 'last_name' => 'Example', 'sex' => 'Female', 'status' => Inhabitant::STATUS_ACTIVE,
        ]);
        $this->actingAs($secretary)->get(route('barangay.registry.active'))->assertOk()
            ->assertSee(route('barangay.registry.moved-out'));
        $payload = ['inhabitant_id' => $resident->id, 'movement_date' => '2026-09-01', 'destination' => 'Manila', 'reason' => 'Work'];
        $this->post(route('barangay.registry.moved-out.store'), $payload)->assertSessionHasNoErrors()
            ->assertRedirect(route('barangay.registry.moved-out'));
        $this->assertSame(Inhabitant::STATUS_MIGRATED_OUT, $resident->fresh()->status);
        $this->assertDatabaseHas('migration_records', ['inhabitant_id' => $resident->id, 'type' => MigrationRecord::TYPE_OUT, 'destination' => 'Manila', 'recorded_by' => $secretary->id]);
        $this->get(route('barangay.registry.moved-out'))->assertOk()->assertSee('DepartingResident')->assertSee('Manila')->assertSee('Work');
        $this->get(route('barangay.registry.active'))->assertOk()->assertDontSee('DepartingResident');
        $this->post(route('barangay.registry.moved-out.store'), $payload)->assertSessionHasErrors('inhabitant_id');
        $this->assertDatabaseCount('migration_records', 1);

        $other = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => Barangay::where('name', 'Higosoan')->firstOrFail()->id]);
        $this->actingAs($other)->get(route('barangay.registry.moved-out'))->assertOk()->assertDontSee('DepartingResident');
        $this->post(route('barangay.registry.moved-out.store'), $payload)->assertNotFound();
        $this->assertDatabaseCount('migration_records', 1);
        $this->assertDatabaseCount('inhabitants', 1);
    }

    public function test_departure_requires_a_date_and_a_barangay_staff_account(): void
    {
        $barangay = Barangay::where('name', 'Higosoan')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => 'Not recorded']);
        Inhabitant::create([
            'barangay_id' => $barangay->id, 'household_id' => $household->id,
            'first_name' => 'ImportedDeparture', 'last_name' => 'Example', 'sex' => 'Female',
            'status' => Inhabitant::STATUS_MIGRATED_OUT, 'remarks' => 'MOVE TO MANILA',
        ]);
        $this->actingAs($staff)->get(route('barangay.registry.moved-out'))->assertOk()
            ->assertSee('ImportedDeparture')->assertSee('Not recorded')->assertSee('MOVE TO MANILA');
        $this->actingAs($staff)->post(route('barangay.registry.moved-out.store'), ['inhabitant_id' => 1])
            ->assertSessionHasErrors('movement_date');
        $residentUser = User::factory()->create(['role' => User::ROLE_RESIDENT, 'barangay_id' => $barangay->id]);
        $this->actingAs($residentUser)->get(route('barangay.registry.moved-out'))->assertForbidden();
        $this->post(route('barangay.registry.moved-out.store'), [])->assertForbidden();
        $this->assertDatabaseCount('migration_records', 0);
    }
}
