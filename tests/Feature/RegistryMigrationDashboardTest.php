<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\DeceasedInhabitant;
use App\Models\Inhabitant;
use App\Models\Household;
use App\Models\MigrationRecord;
use App\Models\NewInhabitant;
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
                'registry_sequence' => '1',
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'relationship_to_head' => 'Head',
                'sex' => 'Female',
                'birth_date' => '1990-01-15',
                'recorded_age' => 36,
                'status' => Inhabitant::STATUS_ACTIVE,
                'remarks' => 'PWD',
                'ethnicity' => 'Cebuano',
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
            'registry_sequence' => '1',
            'recorded_age' => 36,
            'remarks' => 'PWD',
            'ethnicity' => 'Cebuano',
        ]);
        $this->assertDatabaseHas('migration_records', [
            'type' => MigrationRecord::TYPE_IN,
            'origin' => 'Quezon City',
            'destination' => 'San Isidro',
        ]);

        $this->actingAs($secretary)
            ->get(route('registry.index', ['source' => 'CANLUPAO.xlsx']))
            ->assertOk()
            ->assertSee('CONSOLIDATED HOUSEHOLD RECORD OF BARANGAY INHABITANTS (RBI)')
            ->assertSee('REMARKS')
            ->assertSee('ETHNICITY')
            ->assertSee('Cebuano')
            ->assertSee('Save row');

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

    public function test_secretary_can_view_and_edit_deceased_workbook_rows(): void
    {
        $barangay = Barangay::where('name', 'Canlupao')->firstOrFail();
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $record = DeceasedInhabitant::create([
            'barangay_id' => $barangay->id,
            'household_number' => '5',
            'last_name' => 'Moralde',
            'first_name' => 'Mark Gil',
            'sex' => 'Male',
            'death_date' => '2024-11-10',
        ]);

        $this->actingAs($secretary)
            ->get(route('registry.index', ['source' => 'CANLUPAO.xlsx', 'sheet' => 'deceased']))
            ->assertOk()
            ->assertSee('NAME OF DECEASED PERSONS')
            ->assertSee('Mark Gil')
            ->assertSee('Save row');

        $this->actingAs($secretary)
            ->put(route('registry.deceased.update', $record), [
                'household_number' => '5',
                'last_name' => 'Moralde',
                'first_name' => 'Mark Gil',
                'sex' => 'Male',
                'remarks' => 'Updated record',
                'death_date' => '2024-11-11',
            ])
            ->assertRedirect(route('registry.index', ['source' => 'CANLUPAO.xlsx', 'sheet' => 'deceased']));

        $this->assertDatabaseHas('deceased_inhabitants', [
            'id' => $record->id,
            'remarks' => 'Updated record',
            'death_date' => '2024-11-11 00:00:00',
        ]);
    }

    public function test_secretary_can_view_and_edit_new_inhabitant_workbook_rows(): void
    {
        $barangay = Barangay::where('name', 'Canlupao')->firstOrFail();
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $record = NewInhabitant::create([
            'barangay_id' => $barangay->id,
            'household_number' => '1',
            'last_name' => 'Schwartz',
            'first_name' => 'Andrew',
            'sex' => 'Male',
            'month_submitted' => 'SEPTEMBER 10, 2024',
        ]);

        $this->actingAs($secretary)
            ->get(route('registry.index', ['source' => 'CANLUPAO.xlsx', 'sheet' => 'new-inhabitants']))
            ->assertOk()
            ->assertSee('NEW INHABITANT')
            ->assertSee('Andrew')
            ->assertSee('MONTH')
            ->assertSee('Save row');

        $this->actingAs($secretary)
            ->put(route('registry.new-inhabitants.update', $record), [
                'household_number' => '1',
                'last_name' => 'Schwartz',
                'first_name' => 'Andrew',
                'sex' => 'Male',
                'month_submitted' => 'OCTOBER 2024',
            ])
            ->assertRedirect(route('registry.index', ['source' => 'CANLUPAO.xlsx', 'sheet' => 'new-inhabitants']));

        $this->assertDatabaseHas('new_inhabitants', [
            'id' => $record->id,
            'month_submitted' => 'OCTOBER 2024',
        ]);
    }

    public function test_biasong_workbook_uses_its_own_layout_and_excludes_sheet_three(): void
    {
        $barangay = Barangay::where('name', 'Biasong')->firstOrFail();
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => '1']);
        Inhabitant::create([
            'barangay_id' => $barangay->id,
            'household_id' => $household->id,
            'registry_sequence' => '1',
            'family_number' => '1',
            'individual_number' => '1',
            'last_name' => 'Layo',
            'first_name' => 'Julieto',
            'sex' => 'Male',
            'status' => Inhabitant::STATUS_ACTIVE,
        ]);

        $this->actingAs($secretary)
            ->get(route('registry.index', ['source' => 'BIASONG.xlsx']))
            ->assertOk()
            ->assertSee('No. of')
            ->assertSee('Families')
            ->assertSee('Individuals')
            ->assertSee('BIASONG')
            ->assertSee('Julieto')
            ->assertDontSee('Sheet3');

        $this->actingAs($secretary)
            ->get(route('registry.index', ['source' => 'BIASONG.xlsx', 'sheet' => 'new-inhabitants']))
            ->assertOk()
            ->assertSee('HOUSEHOLD RECORD OF BARANGAY INHABITANTS (RBI)')
            ->assertSee('COMPLETE')
            ->assertSee('Active household head')
            ->assertSee('Layo')
            ->assertSee('Save Monthly Report')
            ->assertSee('NENA E. GONZAGA')
            ->assertDontSee('Sheet3');

        $this->actingAs($secretary)
            ->post(route('registry.new-inhabitants.store'), [
                'household_number' => '66',
                'last_name' => 'Sample',
                'first_name' => 'Resident',
                'sex' => 'Female',
                'complete_address' => 'Purok Ipil',
                'occupation' => 'Teacher',
                'remarks' => 'New record',
            ])
            ->assertRedirect(route('registry.index', ['source' => 'BIASONG.xlsx', 'sheet' => 'new-inhabitants']));

        $this->assertDatabaseHas('new_inhabitants', [
            'barangay_id' => $barangay->id,
            'household_number' => '66',
            'last_name' => 'Sample',
            'complete_address' => 'Purok Ipil',
            'occupation' => 'Teacher',
        ]);

        $this->actingAs($secretary)
            ->post(route('registry.new-inhabitant-families.store'), [
                'reporting_month' => '2026-09',
                'household_number' => '67',
                'members' => [
                    ['last_name' => 'Family', 'first_name' => 'Head', 'sex' => 'Male', 'relationship_to_head' => 'HEAD'],
                    ['last_name' => 'Family', 'first_name' => 'Member', 'sex' => 'Female', 'relationship_to_head' => 'DAUGHTER'],
                ],
            ])
            ->assertRedirect(route('registry.index', ['source' => 'BIASONG.xlsx', 'sheet' => 'new-inhabitants', 'reporting_month' => '2026-09']));

        $this->assertDatabaseCount('new_inhabitants', 3);
        $this->assertDatabaseHas('new_inhabitants', [
            'barangay_id' => $barangay->id,
            'reporting_month' => '2026-09-01 00:00:00',
            'household_number' => '67',
            'first_name' => 'Member',
        ]);

        $promotePayload = ['household_number' => '67', 'reporting_month' => '2026-09'];
        $this->actingAs($secretary)
            ->post(route('registry.new-inhabitant-families.add-to-active'), $promotePayload)
            ->assertRedirect(route('registry.index', ['source' => 'BIASONG.xlsx', 'sheet' => 'new-inhabitants', 'reporting_month' => '2026-09']));

        $this->assertDatabaseHas('households', ['barangay_id' => $barangay->id, 'household_number' => '67']);
        $this->assertDatabaseHas('inhabitants', ['barangay_id' => $barangay->id, 'first_name' => 'Head', 'status' => Inhabitant::STATUS_ACTIVE]);
        $this->assertSame(2, NewInhabitant::where('household_number', '67')->whereNotNull('active_inhabitant_id')->count());
        $activeCount = Inhabitant::where('barangay_id', $barangay->id)->count();

        $this->actingAs($secretary)->post(route('registry.new-inhabitant-families.add-to-active'), $promotePayload);
        $this->assertSame($activeCount, Inhabitant::where('barangay_id', $barangay->id)->count());

        $this->actingAs($secretary)
            ->delete(route('registry.new-inhabitant-families.remove-from-active'), $promotePayload)
            ->assertRedirect(route('registry.index', ['source' => 'BIASONG.xlsx', 'sheet' => 'new-inhabitants', 'reporting_month' => '2026-09']));

        $this->assertSame(1, Inhabitant::where('barangay_id', $barangay->id)->count());
        $this->assertSame(0, NewInhabitant::where('household_number', '67')->whereNotNull('active_inhabitant_id')->count());
        $this->assertDatabaseMissing('households', ['barangay_id' => $barangay->id, 'household_number' => '67']);

        $this->actingAs($secretary)
            ->post(route('registry.new-inhabitant-families.store'), [
                'reporting_month' => '2026-09',
                'existing_household_id' => $household->id,
                'members' => [
                    ['last_name' => 'Layo', 'first_name' => 'New Child', 'sex' => 'Female', 'relationship_to_head' => 'DAUGHTER'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('new_inhabitants', [
            'barangay_id' => $barangay->id,
            'household_number' => '1',
            'first_name' => 'New Child',
        ]);

        $this->actingAs($secretary)
            ->post(route('registry.new-inhabitant-monthly-reports.store'), [
                'reporting_month' => '2026-10',
                'families' => [
                    ['household_number' => '70', 'members' => [['last_name' => 'One', 'first_name' => 'Family Head', 'sex' => 'Male']]],
                    ['household_number' => '71', 'members' => [['last_name' => 'Two', 'first_name' => 'Family Head', 'sex' => 'Female']]],
                ],
            ])
            ->assertRedirect(route('registry.index', ['source' => 'BIASONG.xlsx', 'sheet' => 'new-inhabitants', 'reporting_month' => '2026-10']));

        $this->assertSame(2, NewInhabitant::whereDate('reporting_month', '2026-10-01')->distinct('household_number')->count('household_number'));

        $this->actingAs($secretary)
            ->get(route('registry.new-inhabitant-monthly-reports.pdf', '2026-10'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($secretary)
            ->post(route('registry.new-inhabitant-monthly-reports.submit', '2026-10'))
            ->assertRedirect();

        $submitted = BarangayRbiUpdate::where('barangay_user_id', $secretary->id)
            ->whereDate('reporting_month', '2026-10-01')->firstOrFail();
        $this->assertSame(BarangayRbiUpdate::STATUS_SUBMITTED, $submitted->status);
        $this->assertCount(2, $submitted->rbiFamilies);
        $this->assertSame(2, NewInhabitant::whereDate('reporting_month', '2026-10-01')->whereNotNull('submitted_rbi_update_id')->count());

        $memberToDelete = NewInhabitant::whereDate('reporting_month', '2026-10-01')->firstOrFail();
        $this->actingAs($secretary)->delete(route('registry.new-inhabitants.destroy', $memberToDelete))->assertRedirect();
        $this->assertSame(BarangayRbiUpdate::STATUS_DRAFT, $submitted->fresh()->status);
        $this->assertSame(0, NewInhabitant::whereDate('reporting_month', '2026-10-01')->whereNotNull('submitted_rbi_update_id')->count());
    }

    public function test_cabascan_uses_workbook_registry_and_family_monthly_workflow(): void
    {
        $barangay = Barangay::where('name', 'Cabascan')->firstOrFail();
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => '1']);
        Inhabitant::create([
            'barangay_id' => $barangay->id,
            'household_id' => $household->id,
            'registry_sequence' => '1',
            'last_name' => 'Gumanit',
            'first_name' => 'Luciana',
            'sex' => 'Female',
            'status' => Inhabitant::STATUS_ACTIVE,
        ]);

        $this->actingAs($secretary)
            ->get(route('dashboard.barangay'))
            ->assertOk()
            ->assertSee('Open CABASCAN.xlsx Data')
            ->assertSee('New Inhabitants');

        $this->actingAs($secretary)
            ->get(route('registry.index', ['source' => 'CABASCAN.xlsx']))
            ->assertOk()
            ->assertSee('CABASCAN')
            ->assertSee('Luciana');

        $this->actingAs($secretary)
            ->get(route('registry.index', ['source' => 'CABASCAN.xlsx', 'sheet' => 'new-inhabitants']))
            ->assertOk()
            ->assertSee('HOUSEHOLD RECORD OF BARANGAY INHABITANTS (RBI)')
            ->assertSee('Save Monthly Report')
            ->assertSee('CABASCAN');
    }
}
