<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistryMigrationDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for isolated registry feature tests.');
        }

        parent::setUp();
    }

    public function test_staff_can_create_registry_record_with_house_coordinates_and_migration_event(): void
    {
        $barangay = Barangay::where('name', 'San Isidro')->firstOrFail();
        $secretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => $barangay->id,
        ]);

        $this->actingAs($secretary)
            ->post(route('registry.store'), [
                'barangay_id' => $barangay->id,
                'household_number' => 'HH-001',
                'purok' => 'Purok 2',
                'address' => 'Riverside Road',
                'latitude' => '14.1234567',
                'longitude' => '121.1234567',
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'relationship_to_head' => 'Head',
                'sex' => 'Female',
                'birth_date' => '1990-01-15',
                'status' => Inhabitant::STATUS_ACTIVE,
                'migration_type' => MigrationRecord::TYPE_IN,
                'movement_date' => '2026-05-01',
                'origin' => 'Quezon City',
                'destination' => 'San Isidro',
            ])
            ->assertRedirect(route('registry.index'));

        $this->assertDatabaseHas('barangays', ['name' => 'San Isidro']);
        $this->assertDatabaseHas('households', [
            'household_number' => 'HH-001',
            'address' => 'Riverside Road',
        ]);
        $this->assertDatabaseHas('inhabitants', [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);
        $this->assertDatabaseHas('migration_records', [
            'type' => MigrationRecord::TYPE_IN,
            'origin' => 'Quezon City',
            'destination' => 'San Isidro',
        ]);

        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);

        $this->actingAs($municipal)
            ->get(route('migration.dashboard'))
            ->assertOk()
            ->assertSee('San Isidro')
            ->assertSee('In-migration')
            ->assertSee('Maria Santos');

        $this->actingAs($municipal)
            ->get(route('spatial.index'))
            ->assertOk()
            ->assertSee('Household Population Map')
            ->assertSee('HH-001')
            ->assertSee('14.1234567');
    }

    public function test_residents_cannot_access_staff_registry(): void
    {
        $resident = User::factory()->create(['role' => User::ROLE_RESIDENT]);

        $this->actingAs($resident)
            ->get(route('registry.index'))
            ->assertForbidden();
    }
}
