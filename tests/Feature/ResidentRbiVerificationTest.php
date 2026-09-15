<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentRbiVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function applicants(): array
    {
        $barangay = Barangay::where('name', 'Looc')->firstOrFail();
        return [
            User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]),
            User::factory()->create(['name' => 'Juan Santos', 'role' => User::ROLE_RESIDENT, 'barangay_id' => $barangay->id, 'approval_status' => User::APPROVAL_PENDING]),
        ];
    }

    private function record(User $resident, array $attributes = []): Inhabitant
    {
        $house = Household::create(['barangay_id' => $resident->barangay_id, 'household_number' => 'Test-'.Household::count()]);
        return Inhabitant::create(array_replace([
            'barangay_id' => $resident->barangay_id, 'household_id' => $house->id,
            'first_name' => 'Juan', 'last_name' => 'Santos', 'sex' => 'Male',
            'status' => Inhabitant::STATUS_ACTIVE, 'residence_status' => Inhabitant::RESIDENCE_HERE,
        ], $attributes));
    }

    public function test_dashboard_shows_automatic_note_and_allows_approval_without_manual_verification(): void
    {
        [$staff, $resident] = $this->applicants();
        $this->record($resident);
        $resident->update(['name' => ' SANTOS,   JUAN ']);
        $this->actingAs($staff)->get(route('barangay.resident-approvals.index'))
            ->assertOk()->assertSee('Resident record found')->assertSee('living in Barangay Looc');
        $url = route('barangay.residents.approve', $resident);
        $this->post($url)->assertSessionHasNoErrors();
        $this->assertSame(User::APPROVAL_APPROVED, $resident->fresh()->approval_status);
    }

    public function test_missing_and_other_barangay_records_cannot_clear_a_request(): void
    {
        [$staff, $resident] = $this->applicants();
        $other = User::factory()->create(['barangay_id' => Barangay::where('name', 'Luan')->value('id')]);
        $this->record($other);
        $this->actingAs($staff)->get(route('dashboard.barangay'))->assertOk()->assertSee('No RBI match found');
        $this->post(route('barangay.residents.approve', $resident), ['residency_confirmed' => '1'])
            ->assertSessionHasErrors('resident_verification');
        $this->assertSame(User::APPROVAL_PENDING, $resident->fresh()->approval_status);
    }

    public function test_ambiguous_similar_and_nonresident_records_block_direct_approval(): void
    {
        [$staff, $resident] = $this->applicants();
        $record = $this->record($resident);
        $this->actingAs($staff);
        foreach ([
            ['first_name' => 'Juann'],
            ['first_name' => 'Juan', 'residence_status' => Inhabitant::RESIDENCE_ELSEWHERE],
            ['residence_status' => Inhabitant::RESIDENCE_UNCONFIRMED],
            ['residence_status' => Inhabitant::RESIDENCE_HERE, 'status' => Inhabitant::STATUS_MIGRATED_OUT],
            ['status' => Inhabitant::STATUS_INACTIVE],
            ['status' => Inhabitant::STATUS_ACTIVE, 'resident_user_id' => $staff->id],
        ] as $changes) {
            $record->update($changes);
            $this->post(route('barangay.residents.approve', $resident), ['residency_confirmed' => '1'])
                ->assertSessionHasErrors('resident_verification');
            $this->assertSame(User::APPROVAL_PENDING, $resident->fresh()->approval_status);
        }
        $record->update(['resident_user_id' => null]);
        $this->record($resident);
        $this->get(route('dashboard.barangay'))->assertOk()->assertSee('Multiple matching records');
        $this->post(route('barangay.residents.approve', $resident), ['residency_confirmed' => '1'])
            ->assertSessionHasErrors('resident_verification');
    }

    public function test_approval_rechecks_registry_after_dashboard_was_opened(): void
    {
        [$staff, $resident] = $this->applicants();
        $record = $this->record($resident);
        $this->actingAs($staff)->get(route('dashboard.barangay'))->assertOk()->assertSee('Resident record found');
        $record->update(['residence_status' => Inhabitant::RESIDENCE_ELSEWHERE]);
        $this->post(route('barangay.residents.approve', $resident), ['residency_confirmed' => '1'])
            ->assertSessionHasErrors('resident_verification');
        $this->assertSame(User::APPROVAL_PENDING, $resident->fresh()->approval_status);
    }
}
