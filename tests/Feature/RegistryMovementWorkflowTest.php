<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use App\Services\RegistryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistryMovementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setupPeople(): array
    {
        $area = Barangay::where('name', 'Cawayan')->firstOrFail();
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        $house = Household::create(['barangay_id' => $area->id, 'household_number' => 'WF-1']);
        $people = collect(['FirstMover', 'SecondMover'])->map(fn ($name) => Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $house->id, 'first_name' => $name, 'last_name' => 'Workflow', 'sex' => 'Female', 'status' => 'active', 'residence_status' => 'living_here']));

        return [$area, $staff, $people];
    }

    private function payload($people, string $action, string $scope = 'person'): array
    {
        return ['action' => $action, 'scope' => $scope, 'movement_date' => '2026-09-03', 'destination' => 'Cebu', 'confirmed' => 1,
            'versions' => $people->mapWithKeys(fn ($p) => [$p->id => app(RegistryMovement::class)->version($p->fresh())])->all()];
    }

    public function test_move_out_stays_consolidated_and_transfer_archives_without_duplicate_departure(): void
    {
        [$area,$staff,$people] = $this->setupPeople();
        $resident = $people->first();
        $this->actingAs($staff)->put(route('registry.movement.update', $resident), $this->payload(collect([$resident]), 'moved_out'))->assertSessionHasNoErrors();
        $this->assertSame('active', $resident->fresh()->status);
        $this->assertSame('living_elsewhere', $resident->fresh()->residence_status);
        $this->get(route('barangay.registry.active'))->assertSee('FirstMover');
        $this->get(route('barangay.residence.index'))->assertDontSee('FirstMover')->assertSee('SecondMover');
        $this->get(route('barangay.registry.moved-out'))->assertSee('FirstMover')->assertSee('Moved out — still registered');
        $this->put(route('registry.movement.update', $resident), $this->payload(collect([$resident]), 'transferred'))->assertSessionHasNoErrors();
        $this->assertSame('migrated_out', $resident->fresh()->status);
        $this->assertDatabaseCount('migration_records', 1);
        $this->get(route('barangay.registry.active'))->assertDontSee('FirstMover')->assertSee('SecondMover');
        $this->get(route('barangay.registry.moved-out'))->assertSee('FirstMover')->assertSee('Transferred');
    }

    public function test_household_update_is_atomic_scoped_and_stale_safe(): void
    {
        [$area,$staff,$people] = $this->setupPeople();
        $resident = $people->first();
        $payload = $this->payload($people, 'moved_out', 'household');
        $people->last()->update(['occupation' => 'Changed after form opened']);
        $this->actingAs($staff)->put(route('registry.movement.update', $resident), $payload)->assertSessionHasErrors('versions');
        $this->assertDatabaseCount('migration_records', 0);
        $this->put(route('registry.movement.update', $resident), $this->payload($people, 'moved_out', 'household'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('migration_records', 2);
        foreach ($people as $person) {
            $this->assertSame('living_elsewhere', $person->fresh()->residence_status);
        }
        $other = User::factory()->create(['role' => 'barangay', 'barangay_id' => Barangay::where('name', 'Looc')->firstOrFail()->id]);
        $this->actingAs($other)->put(route('registry.movement.update', $resident), $this->payload($people, 'transferred', 'household'))->assertForbidden();
    }

    public function test_date_is_required_and_other_pages_do_not_write_departures(): void
    {
        [$area,$staff,$people] = $this->setupPeople();
        $resident = $people->first();
        $payload = $this->payload(collect([$resident]), 'moved_out');
        unset($payload['movement_date']);
        $this->actingAs($staff)->put(route('registry.movement.update', $resident), $payload)->assertSessionHasErrors('movement_date');
        $this->post(route('barangay.registry.moved-out.store'), ['inhabitant_id' => $resident->id])->assertRedirect(route('barangay.registry.active'));
        $this->put(route('barangay.residence.update', $resident), ['residence_status' => 'transferred'])->assertRedirect(route('registry.movement', $resident));
        $this->assertDatabaseCount('migration_records', 0);
        $this->assertSame('living_here', $resident->fresh()->residence_status);
        $this->get(route('barangay.residence.index'))->assertDontSee('data-residence-update', false);
        $this->get(route('barangay.registry.moved-out'))->assertDontSee('Record a departure');
    }
}
