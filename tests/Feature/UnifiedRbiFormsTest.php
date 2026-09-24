<?php
namespace Tests\Feature;

use App\Models\{Barangay, NewInhabitant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedRbiFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_records_move_into_rbi_forms_without_copying_or_exposing_other_barangays(): void
    {
        $barangay = Barangay::where('name', 'San Roque')->firstOrFail();
        $other = Barangay::where('name', 'Hinagtikan')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $record = NewInhabitant::create(['barangay_id' => $barangay->id, 'first_name' => 'SavedPerson', 'last_name' => 'Example', 'household_number' => '12', 'sex' => 'Male', 'reporting_month' => '2021-05-01']);
        NewInhabitant::create(['barangay_id' => $other->id, 'household_number' => '1', 'first_name' => 'OtherOfficePerson', 'last_name' => 'Example']);
        NewInhabitant::create(['barangay_id' => $barangay->id, 'household_number' => '2', 'first_name' => 'UndatedPerson', 'last_name' => 'Example', 'month_submitted' => 'MAY']);
        $this->actingAs($staff)->get(route('barangay.rbi-updates.history'))->assertOk()
            ->assertSee('SavedPerson')->assertDontSee('UndatedPerson')->assertDontSee('OtherOfficePerson')
            ->assertSee(route('registry.new-inhabitants.edit', $record))->assertSee('Add to Active Household')
            ->assertDontSee('id="new-family-forms"', false);
        $this->get(route('barangay.rbi-updates.history', ['household_page' => 2]))->assertOk()
            ->assertSee('UndatedPerson')->assertDontSee('SavedPerson')->assertDontSee('OtherOfficePerson');
        $this->get(route('barangay.registry.new-inhabitants'))->assertRedirect(route('barangay.rbi-updates.history'));
        $this->get(route('registry.index', ['source' => 'SAN ROQUE.xlsx', 'sheet' => 'new-inhabitants']))->assertRedirect(route('barangay.rbi-updates.history'));
        $this->assertDatabaseCount('new_inhabitants', 3);
    }
}
