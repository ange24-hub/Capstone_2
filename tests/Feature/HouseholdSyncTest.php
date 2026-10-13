<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdSyncTest extends TestCase
{
    use RefreshDatabase;

    private function secretary(): User
    {
        return User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => Barangay::where('name', 'Banday')->value('id')]);
    }

    private function record(): array
    {
        return ['offline_uuid' => 'test-offline-001', 'household_number' => 'OFFLINE-001', 'household_name' => 'Test Family',
            'latitude' => 10.1234567, 'longitude' => 124.1234567, 'gps_accuracy' => 8.5,
            'location_captured_at' => '2026-10-07T00:00:00Z'];
    }

    public function test_sync_saves_to_assigned_barangay_and_retries_without_duplicates(): void
    {
        $secretary = $this->secretary();
        $this->actingAs($secretary)->getJson(route('households.offline-sync.session'))->assertOk()->assertJsonPath('barangay_id', $secretary->barangay_id)->assertJsonStructure(['csrf_token']);
        $first = $this->postJson(route('households.offline-sync'), $this->record())->assertOk()->assertJsonPath('success', true);
        $this->postJson(route('households.offline-sync'), $this->record())->assertOk()->assertJsonPath('household_id', $first->json('household_id'));
        $this->assertDatabaseHas('households', ['offline_uuid' => 'test-offline-001', 'barangay_id' => $secretary->barangay_id, 'household_name' => 'Test Family']);
        $this->assertSame(1, Household::where('offline_uuid', 'test-offline-001')->count());
    }

    public function test_sync_requires_secretary_login_and_assigned_barangay(): void
    {
        $this->postJson(route('households.offline-sync'), $this->record())->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson(route('households.offline-sync'), $this->record())->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => null]))->postJson(route('households.offline-sync'), $this->record())->assertForbidden();
    }

    public function test_foreign_barangay_uuid_and_invalid_coordinates_cannot_be_synced(): void
    {
        $secretary = $this->secretary();
        $foreignId = Barangay::where('name', 'Bogo')->value('id');
        Household::create(array_merge($this->record(), ['barangay_id' => $foreignId]));
        $this->actingAs($secretary)->postJson(route('households.offline-sync'), $this->record())->assertForbidden();
        $this->postJson(route('households.offline-sync'), array_merge($this->record(), ['barangay_id' => $foreignId]))->assertForbidden();
        $this->postJson(route('households.offline-sync'), array_merge($this->record(), ['latitude' => 91]))->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $this->assertSame($foreignId, Household::where('offline_uuid', 'test-offline-001')->firstOrFail()->barangay_id);
    }

    public function test_conflicting_household_number_does_not_overwrite_existing_registry_record(): void
    {
        $secretary = $this->secretary();
        $existing = Household::create(['barangay_id' => $secretary->barangay_id, 'household_number' => 'OFFLINE-001', 'household_name' => 'Original Family']);
        $this->actingAs($secretary)->postJson(route('households.offline-sync'), $this->record())->assertStatus(409);
        $this->assertSame('Original Family', $existing->fresh()->household_name);
        $this->assertNull($existing->fresh()->offline_uuid);
    }

    public function test_consolidated_name_lookup_only_returns_assigned_barangay_residents(): void
    {
        $secretary = $this->secretary();
        $household = Household::create(['barangay_id' => $secretary->barangay_id, 'household_number' => '135']);
        Inhabitant::create(['barangay_id' => $secretary->barangay_id, 'household_id' => $household->id, 'first_name' => 'Eiries', 'last_name' => 'Maquilang', 'sex' => 'Female', 'status' => 'active']);
        Inhabitant::create(['barangay_id' => $secretary->barangay_id, 'household_id' => $household->id, 'first_name' => 'Inactive', 'last_name' => 'Hidden', 'sex' => 'Male', 'status' => 'inactive']);
        $foreignId = Barangay::where('name', 'Bogo')->value('id');
        $foreign = Household::create(['barangay_id' => $foreignId, 'household_number' => '135', 'household_name' => 'Foreign Family']);
        Inhabitant::create(['barangay_id' => $foreignId, 'household_id' => $foreign->id, 'first_name' => 'Foreign', 'last_name' => 'Person', 'sex' => 'Male', 'status' => 'active']);
        $this->getJson(route('households.offline-sync.registry'))->assertUnauthorized();
        $this->actingAs($secretary)->getJson(route('households.offline-sync.registry'))->assertOk()
            ->assertJsonPath('barangay_id', $secretary->barangay_id)
            ->assertJsonFragment(['name' => 'Eiries Maquilang', 'household_number' => '135', 'household_id' => $household->id])
            ->assertDontSee('Foreign Family')->assertDontSee('Foreign Person')->assertDontSee('Inactive Hidden');
    }

    public function test_selected_consolidated_household_receives_gps_without_renaming_or_duplicate_records(): void
    {
        $secretary = $this->secretary();
        $household = Household::create(['barangay_id' => $secretary->barangay_id, 'household_number' => '135', 'household_name' => 'Original Family']);
        $record = array_merge($this->record(), ['household_id' => $household->id, 'household_number' => '135']);
        $this->actingAs($secretary)->postJson(route('households.offline-sync'), $record)->assertOk()->assertJsonPath('household_id', $household->id);
        $this->assertSame('Original Family', $household->fresh()->household_name);
        $this->assertSame('10.1234567', $household->fresh()->latitude);
        $this->postJson(route('households.offline-sync'), array_merge($record, ['offline_uuid' => 'second-capture', 'latitude' => 11]))->assertOk();
        $this->postJson(route('households.offline-sync'), $record)->assertOk();
        $this->assertSame('11.0000000', $household->fresh()->latitude);
        $this->assertSame(1, Household::where('barangay_id', $secretary->barangay_id)->count());
        $this->assertDatabaseCount('household_offline_syncs', 2);
    }

    public function test_selected_household_must_match_barangay_and_number(): void
    {
        $secretary = $this->secretary();
        $household = Household::create(['barangay_id' => $secretary->barangay_id, 'household_number' => '135']);
        $this->actingAs($secretary)->postJson(route('households.offline-sync'), array_merge($this->record(), ['household_id' => $household->id]))->assertUnprocessable();
        $household->update(['barangay_id' => Barangay::where('name', 'Bogo')->value('id')]);
        $this->postJson(route('households.offline-sync'), array_merge($this->record(), ['household_id' => $household->id, 'household_number' => '135']))->assertForbidden();
        $this->assertNull($household->fresh()->latitude);
        $this->assertDatabaseCount('household_offline_syncs', 0);
    }
}
