<?php

namespace Tests\Feature;

use App\Models\{Barangay, BarangayRbiUpdate, Household, Inhabitant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbiSubpagesTest extends TestCase
{
    use RefreshDatabase;

    private function secretary(): User
    {
        return User::factory()->create(['role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', 'Maslog')->firstOrFail()->id]);
    }

    public function test_subpages_show_only_their_own_form_and_scope_households(): void
    {
        $staff = $this->secretary();
        $other = Barangay::where('name', 'Looc')->firstOrFail();
        foreach ([[$staff->barangay_id, 'LocalHead'], [$other->id, 'PrivateOtherHead']] as [$area, $name]) {
            $house = Household::create(['barangay_id' => $area, 'household_number' => '10']);
            Inhabitant::create(['barangay_id' => $area, 'household_id' => $house->id,
                'first_name' => $name, 'last_name' => 'Example', 'sex' => 'Male', 'status' => 'active']);
        }
        $this->actingAs($staff)->get(route('barangay.rbi-updates.residents'))->assertOk()
            ->assertSee('id="family-forms"', false)->assertDontSee('id="rbi-deceased-rows-table"', false);
        $this->get(route('barangay.rbi-updates.deceased'))->assertOk()
            ->assertSee('id="rbi-deceased-rows-table"', false)->assertDontSee('id="family-forms"', false)
            ->assertDontSee('data-family-head required', false)->assertSee('LocalHead')->assertDontSee('PrivateOtherHead');
        $resident = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        $this->actingAs($resident)->get(route('barangay.rbi-updates.deceased'))->assertForbidden();
    }

    public function test_saving_either_subpage_preserves_the_other_sections_records(): void
    {
        $staff = $this->secretary();
        $this->actingAs($staff)->post(route('barangay.rbi-updates.store'), [
            'form_section' => 'residents', 'reporting_month' => '2026-09',
            'rows' => [['household_head' => 'Local Family', 'inhabitant_name' => 'Original Resident']],
        ])->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::firstOrFail();
        $originalRows = $report->rows;
        $this->put(route('barangay.rbi-updates.update', $report), [
            'form_section' => 'deceased', 'reporting_month' => '2026-09',
            'deceased_rows' => [['household_head' => 'Different Family', 'deceased_name' => 'Recorded Death', 'death_date' => '2026-09-01']],
        ])->assertSessionHasNoErrors();
        $report->refresh();
        $this->assertSame($originalRows, $report->rows);
        $deceased = $report->deceased_rows;
        $this->put(route('barangay.rbi-updates.update', $report), [
            'form_section' => 'residents', 'reporting_month' => '2026-09',
            'rows' => [['household_head' => 'Local Family', 'inhabitant_name' => 'Updated Resident']],
        ])->assertSessionHasNoErrors();
        $report->refresh();
        $this->assertSame($deceased, $report->deceased_rows);
        $this->assertSame('Updated Resident', $report->rows[0]['inhabitant_name']);
        $this->assertSame('Recorded Death', $report->deceasedRecords()->firstOrFail()->deceased_name);
        $this->get(route('barangay.rbi-updates.deceased', ['edit' => $report->id]))->assertOk()
            ->assertSee('Recorded Death')->assertSee(route('barangay.rbi-updates.residents', ['new' => 1]));
        $this->post(route('barangay.rbi-updates.store'), ['form_section' => 'deceased', 'reporting_month' => '2026-09'])
            ->assertRedirect(route('barangay.rbi-updates.deceased', ['edit' => $report->id]));
    }

    public function test_resident_form_is_blank_after_creating_and_updating_a_saved_report(): void
    {
        $staff = $this->secretary();
        $blankUrl = route('barangay.rbi-updates.residents', ['new' => 1]);
        $payload = ['form_section' => 'residents', 'reporting_month' => '2026-09',
            'prepared_by' => 'Saved Encoder',
            'rows' => [['household_head' => 'Saved Family', 'inhabitant_name' => 'Saved Resident']]];
        $this->actingAs($staff)->post(route('barangay.rbi-updates.store'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect($blankUrl);
        $report = BarangayRbiUpdate::firstOrFail();
        $assertBlank = function () use ($blankUrl, $report): void {
            $response = $this->get($blankUrl)->assertOk()->assertViewHas('draftRbiUpdate', null);
            $html = $response->getContent();
            preg_match('/<form[^>]*id="rbi-monthly-form"[^>]*>(.*?)<\/form>/s', $html, $matches);
            $this->assertNotEmpty($matches);
            $this->assertStringNotContainsString('value="Saved Family"', $matches[1]);
            $this->assertStringNotContainsString('value="Saved Resident"', $matches[1]);
            $this->assertStringNotContainsString('value="Saved Encoder"', $matches[1]);
            $this->assertStringContainsString('name="reporting_month" type="month" value=""', $matches[1]);
            $response->assertSee(route('barangay.rbi-updates.residents', ['edit' => $report->id]));
        };
        $assertBlank();
        $this->assertSame('Saved Resident', $report->fresh()->rows[0]['inhabitant_name']);
        $this->get(route('barangay.rbi-updates.residents', ['edit' => $report->id]))->assertOk()
            ->assertSee('value="Saved Family"', false);
        $this->put(route('barangay.rbi-updates.update', $report), $payload)
            ->assertSessionHasNoErrors()->assertRedirect($blankUrl);
        $assertBlank();
        $this->assertSame('Saved Resident', $report->fresh()->rows[0]['inhabitant_name']);
    }

    public function test_resident_navigation_starts_blank_and_only_explicit_edit_loads_saved_names(): void
    {
        $staff = $this->secretary();
        $this->actingAs($staff)->post(route('barangay.rbi-updates.store'), [
            'form_section' => 'residents', 'reporting_month' => now()->format('Y-m'),
            'prepared_by' => 'Saved Encoder', 'certified_by' => 'Saved Secretary', 'attested_by' => 'Saved Captain',
            'rows' => [['household_head' => 'Saved Family', 'inhabitant_name' => 'Saved Resident']],
        ])->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::firstOrFail();
        foreach (['barangay.rbi-updates.index', 'barangay.rbi-updates.residents'] as $routeName) {
            $response = $this->get(route($routeName))->assertOk()->assertViewHas('draftRbiUpdate', null);
            preg_match('/<form[^>]*id="rbi-monthly-form"[^>]*>(.*?)<\/form>/s', $response->getContent(), $matches);
            $this->assertNotEmpty($matches);
            foreach (['Saved Family', 'Saved Resident', 'Saved Encoder', 'Saved Secretary', 'Saved Captain'] as $name) {
                $this->assertStringNotContainsString('value="'.$name.'"', $matches[1]);
            }
            foreach (['prepared_by', 'certified_by', 'attested_by'] as $field) {
                $this->assertStringContainsString('name="'.$field.'" type="text" value=""', $matches[1]);
            }
            $this->assertStringContainsString('name="reporting_month" type="month" value=""', $matches[1]);
        }
        foreach (['residents', 'deceased'] as $section) {
            $response = $this->get(route('barangay.rbi-updates.'.$section, ['edit' => $report->id]))->assertOk();
            preg_match('/<nav class="rbi-subpages"[^>]*>(.*?)<\/nav>/s', $response->getContent(), $nav);
            $this->assertStringContainsString(route('barangay.rbi-updates.residents', ['new' => 1]), $nav[1]);
            $this->assertStringNotContainsString(route('barangay.rbi-updates.residents', ['edit' => $report->id]), $nav[1]);
        }
        $this->get(route('barangay.rbi-updates.residents', ['edit' => $report->id]))
            ->assertOk()->assertSee('value="Saved Resident"', false);
        $this->assertSame('Saved Resident', $report->fresh()->rows[0]['inhabitant_name']);
    }

    public function test_failed_resident_save_keeps_entered_values_for_correction(): void
    {
        $staff = $this->secretary();
        $url = route('barangay.rbi-updates.residents', ['new' => 1]);
        $this->actingAs($staff)->from($url)->post(route('barangay.rbi-updates.store'), [
            'form_section' => 'residents', 'reporting_month' => 'invalid',
            'rows' => [['household_head' => 'Unsaved Family', 'inhabitant_name' => 'Unsaved Resident']],
        ])->assertRedirect($url)->assertSessionHasErrors('reporting_month');
        $this->get($url)->assertOk()->assertSee('value="Unsaved Family"', false);
        $this->assertDatabaseCount('barangay_rbi_updates', 0);
    }

    public function test_deceased_only_report_can_be_created_and_another_office_cannot_update_it(): void
    {
        $staff = $this->secretary();
        $this->actingAs($staff)->post(route('barangay.rbi-updates.store'), [
            'form_section' => 'deceased', 'reporting_month' => '2026-10',
            'deceased_rows' => [['deceased_name' => 'Deceased Only', 'death_date' => '2026-10-01']],
        ])->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::firstOrFail();
        $this->assertSame([], $report->rows);
        $this->assertSame('Deceased Only', $report->deceased_rows[0]['deceased_name']);
        $this->get(route('barangay.rbi-updates.residents', ['edit' => $report->id]))->assertOk();
        $other = User::factory()->create(['role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', 'Looc')->firstOrFail()->id]);
        $this->actingAs($other)->put(route('barangay.rbi-updates.update', $report), [
            'form_section' => 'deceased', 'reporting_month' => '2026-10',
        ])->assertForbidden();
        $this->assertCount(1, $report->fresh()->deceased_rows);
    }
}
