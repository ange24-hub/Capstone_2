<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use App\Services\RegistryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistryTransferConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $b = Barangay::where('name', 'San Roque')->firstOrFail();
        $u = User::factory()->create(['role' => 'barangay', 'barangay_id' => $b->id]);
        $h = Household::create(['barangay_id' => $b->id, 'household_number' => '1']);
        $r = Inhabitant::create(['barangay_id' => $b->id, 'household_id' => $h->id, 'first_name' => 'Person', 'last_name' => 'Example', 'sex' => 'Male', 'status' => 'active', 'residence_status' => 'living_here']);

        return [$b, $u, $r];
    }

    private function transfer($r): array
    {
        return ['action' => 'transferred', 'scope' => 'person', 'movement_date' => '2026-09-01', 'destination' => 'Cebu', 'versions' => [$r->id => app(RegistryMovement::class)->version($r->fresh())]];
    }

    public function test_transfer_requires_confirmation_and_is_logged_and_isolated(): void
    {
        [$b,$u,$r] = $this->fixture();
        $data = $this->transfer($r);
        $this->actingAs($u)->put(route('registry.movement.update', $r), $data)->assertSessionHasErrors('confirmed');
        $this->assertSame('active', $r->fresh()->status);
        $this->assertDatabaseCount('migration_records', 0);
        $this->put(route('registry.movement.update', $r), $data + ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame('migrated_out', $r->fresh()->status);
        $this->assertDatabaseHas('migration_records', ['inhabitant_id' => $r->id, 'type' => 'out', 'destination' => 'Cebu', 'recorded_by' => $u->id]);
        $this->assertDatabaseHas('registry_activities', ['inhabitant_id' => $r->id, 'user_id' => $u->id]);
        $this->get(route('barangay.registry.active'))->assertDontSee('Example');
        $this->get(route('barangay.registry.moved-out'))->assertSee('Example')->assertSee('Cebu');
        $other = User::factory()->create(['role' => 'barangay', 'barangay_id' => Barangay::where('name', 'San Isidro')->value('id')]);
        $this->actingAs($other)->put(route('registry.movement.update', $r), $data + ['confirmed' => 1])->assertForbidden();
    }

    public function test_remarks_do_not_silently_transfer_a_resident(): void
    {
        [$b,$u,$r] = $this->fixture();
        $data = ['barangay_id' => $b->id, 'household_number' => '1', 'first_name' => 'Person', 'last_name' => 'Example', 'sex' => 'Male', 'status' => 'active'];
        $this->actingAs($u)->put(route('registry.update', $r), $data + ['remarks' => 'TRANSFERED TO CEBU'])->assertSessionHasNoErrors();
        $this->assertSame('active', $r->fresh()->status);
        $this->assertDatabaseCount('migration_records', 0);
        $this->put(route('registry.update', $r), $data + ['residence_status' => 'transferred'])->assertSessionHasErrors('movement');
    }

    public function test_residence_list_redirects_edits_to_registry_and_transfer_uses_actual_date(): void
    {
        [$b,$u,$r] = $this->fixture();
        $this->actingAs($u)->get(route('barangay.residence.index'))->assertOk()->assertDontSee('data-residence-update', false)->assertSee(route('registry.movement', $r));
        $this->put(route('barangay.residence.update', $r), ['residence_status' => 'transferred'])->assertRedirect(route('registry.movement', $r));
        $this->assertDatabaseCount('migration_records', 0);
        $this->put(route('registry.movement.update', $r), $this->transfer($r) + ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame('2026-09-01', $r->migrationRecords()->firstOrFail()->movement_date->format('Y-m-d'));
    }
}
