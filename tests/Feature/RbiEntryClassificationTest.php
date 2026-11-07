<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\NewInhabitant;
use App\Models\User;
use App\Services\RbiArrivalReview;
use App\Support\RbiEntryClassification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RbiEntryClassificationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->travelTo(Carbon::parse('2026-11-07 12:00:00'));

        return User::factory()->create(['role' => 'barangay', 'barangay_id' => Barangay::where('name', 'Cawayan')->value('id')]);
    }

    private function arrival(): array
    {
        return ['household_head' => 'Arrival Example', 'first_name' => 'Arrival', 'last_name' => 'Example', 'sex' => 'Female', 'birth_date' => '2000-01-01',
            'entry_type' => 'in_migration', 'previous_residence' => 'Cebu City', 'arrival_month' => '2021-03'];
    }

    public function test_month_only_classification_is_saved_and_invalid_arrival_metadata_is_rejected(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff)->get(route('barangay.rbi-updates.residents'))->assertOk()->assertSee('Reason for this RBI entry')->assertSee('Exact arrival date (if known)');
        $payload = ['reporting_month' => '2021-03', 'form_section' => 'residents', 'rows' => [$this->arrival()]];
        foreach ([['previous_residence' => '', 'error' => 'previous_residence'], ['arrival_month' => '2021-04', 'error' => 'arrival_month'],
            ['arrival_date' => '2021-03-32', 'error' => 'arrival_date'], ['birth_date' => '2022-01-01', 'error' => 'arrival_month']] as $case) {
            $error = $case['error'];
            unset($case['error']);
            $this->post(route('barangay.rbi-updates.store'), array_replace($payload, ['rows' => [array_replace($this->arrival(), $case)]]))->assertSessionHasErrors('rows.0.'.$error);
        }
        $this->assertDatabaseCount('barangay_rbi_updates', 0);
        $this->post(route('barangay.rbi-updates.store'), $payload)->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::firstOrFail();
        $this->assertSame('in_migration', $report->rows[0]['entry_type']);
        $this->assertSame('2021-03', $report->rows[0]['arrival_month']);
        $this->assertEmpty($report->rows[0]['arrival_date']);
        $this->assertSame('Cebu City', $report->rbiFamilies()->first()->members()->first()->details['previous_residence']);
        $this->get(route('rbi-updates.show', $report))->assertOk()->assertSee('Day not recorded');
        $this->put(route('barangay.rbi-updates.update', $report), array_replace($payload, ['rows' => [array_replace($this->arrival(), ['entry_type' => 'late_registration'])]]))->assertSessionHasNoErrors();
        $this->assertSame('late_registration', $report->fresh()->rows[0]['entry_type']);
    }

    public function test_classification_distinguishes_a_migrant_infant_from_births_and_late_registration(): void
    {
        $staff = $this->staff();
        $report = BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => 'Cawayan', 'reporting_month' => '2021-03-01', 'status' => 'submitted', 'submitted_at' => now(),
            'rows' => [array_replace($this->arrival(), ['birth_date' => '2021-03-01']),
                ['first_name' => 'Newborn', 'last_name' => 'Example', 'entry_type' => 'birth', 'birth_date' => '2021-03-01'],
                ['first_name' => 'Existing', 'last_name' => 'Example', 'entry_type' => 'late_registration', 'birth_date' => '1990-01-01']]]);
        $service = app(RbiArrivalReview::class);
        $month = $service->periods($staff->barangay)[0]['months'][0];
        $this->assertSame(1, $month['eligible']);
        $this->assertSame(2, $month['excluded']);
        $this->assertSame(0, $month['unclassified']);
        $report->update(['rows' => [['first_name' => 'Unclassified', 'last_name' => 'Legacy']]]);
        $period = $service->periods($staff->barangay)[0];
        $this->assertFalse($period['complete']);
        $this->assertSame(1, $period['months'][0]['unclassified']);
        $this->assertSame(0, $period['months'][0]['eligible']);
    }

    public function test_preparing_cawayan_history_preserves_its_month_and_does_not_overwrite_classification(): void
    {
        $staff = $this->staff();
        $record = NewInhabitant::create(['barangay_id' => $staff->barangay_id, 'reporting_month' => '2021-03-01', 'household_number' => '7', 'first_name' => 'Arrival', 'last_name' => 'Example']);
        NewInhabitant::create(['barangay_id' => Barangay::where('name', 'Looc')->value('id'), 'household_number' => '8', 'reporting_month' => '2021-03-01', 'first_name' => 'OtherBarangay', 'last_name' => 'Example']);
        NewInhabitant::create(['barangay_id' => $staff->barangay_id, 'household_number' => '9', 'first_name' => 'Undated', 'last_name' => 'Example']);
        $url = route('registry.history.prepare-arrivals', '2021-03');
        $this->actingAs($staff)->get(route('rbi.arrivals.index'))->assertOk()->assertSee('March 2021')->assertSee('1 entries have no reporting month');
        $this->post($url)->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::firstOrFail();
        $this->assertSame('2021-03', $report->reporting_month->format('Y-m'));
        $this->assertSame('draft', $report->status);
        $this->assertCount(1, $report->rows);
        $this->assertSame($record->id, $report->rows[0]['history_record_id']);
        $this->assertArrayNotHasKey('entry_type', $report->rows[0]);
        $this->assertNull($record->fresh()->submitted_rbi_update_id);
        $this->get(route('barangay.rbi-updates.residents', ['edit' => $report->id]))->assertOk()->assertSee('Arrival')->assertDontSee('OtherBarangay');
        $report->update(['rows' => [array_merge($report->rows[0], $this->arrival())]]);
        $saved = $report->fresh()->rows;
        $this->post($url)->assertSessionHasNoErrors();
        $this->assertSame($saved, $report->fresh()->rows);
        $this->post(route('registry.new-inhabitant-monthly-reports.submit', '2021-03'))->assertSessionHasErrors('reporting_month');
        $this->assertSame($saved, $report->fresh()->rows);
        $this->assertDatabaseCount('barangay_rbi_updates', 1);
        $this->assertDatabaseCount('new_inhabitants', 3);
        $this->post(route('registry.history.prepare-arrivals', '2021-04'))->assertNotFound();
        $other = User::factory()->create(['role' => 'barangay', 'barangay_id' => $staff->barangay_id]);
        $this->actingAs($other)->post($url)->assertForbidden();
    }

    public function test_confirmed_migrant_with_unrecorded_origin_requires_an_explicit_flag(): void
    {
        $staff = $this->staff();
        $row = array_replace($this->arrival(), ['previous_residence' => '']);
        $payload = ['reporting_month' => '2021-03', 'form_section' => 'residents', 'rows' => [$row]];
        $this->actingAs($staff)->post(route('barangay.rbi-updates.store'), $payload)->assertSessionHasErrors('rows.0.previous_residence');
        $row['previous_residence_unknown'] = 1;
        $this->post(route('barangay.rbi-updates.store'), array_replace($payload, ['rows' => [$row]]))->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::firstOrFail();
        $saved = $report->rows[0];
        $this->assertSame('', $saved['previous_residence']);
        $this->assertSame('1', $saved['previous_residence_unknown']);
        $this->assertTrue(RbiEntryClassification::isArrival($saved, '2021-03'));
        $this->assertEmpty($saved['arrival_date']);
        $this->get(route('rbi-updates.show', $report))->assertOk()->assertSee('Not recorded — confirmed in-migrant');
        $this->assertDatabaseCount('migration_records', 0);
    }

    public function test_partial_reviews_can_be_saved_without_classifying_uncertain_residents(): void
    {
        $staff = $this->staff();
        $rows = [array_replace($this->arrival(), ['entry_type' => 'late_registration']),
            ['household_head' => 'Uncertain Example', 'first_name' => 'Uncertain', 'last_name' => 'Example', 'sex' => 'Female', 'entry_type' => '']];
        $this->actingAs($staff)->post(route('barangay.rbi-updates.store'), ['reporting_month' => '2021-03', 'form_section' => 'residents', 'rows' => $rows])->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::firstOrFail();
        $this->assertSame('draft', $report->status);
        $this->assertSame('', $report->rows[1]['entry_type']);
        $html = $this->get(route('barangay.rbi-updates.residents', ['edit' => $report->id]))->assertOk()->assertSee('Needs review — not yet confirmed')->getContent();
        preg_match('/<select data-entry-type[^>]*>/', $html, $select);
        $this->assertNotEmpty($select);
        $this->assertStringNotContainsString('required', $select[0]);
        $report->update(['status' => 'submitted', 'submitted_at' => now()]);
        $period = app(RbiArrivalReview::class)->periods($staff->barangay)[0];
        $this->assertSame(1, $period['months'][0]['unclassified']);
        $this->assertSame(0, $period['months'][0]['eligible']);
        $this->assertFalse($period['complete']);
    }

    public function test_individual_review_links_are_scoped_to_the_selected_history_month_and_person(): void
    {
        $staff = $this->staff();
        $record = NewInhabitant::create(['barangay_id' => $staff->barangay_id, 'reporting_month' => '2021-03-01', 'household_number' => '7', 'first_name' => 'ReviewResident', 'last_name' => 'Example', 'birth_date' => '2021-03-04']);
        $foreign = NewInhabitant::create(['barangay_id' => Barangay::where('name', 'Looc')->value('id'), 'reporting_month' => '2021-03-01', 'household_number' => '8', 'first_name' => 'ForeignResident', 'last_name' => 'Example']);
        $this->actingAs($staff)->get(route('rbi.arrivals.index'))->assertOk()->assertSee('Review each resident entry')->assertSee('ReviewResident')->assertSee('Mar 04, 2021')->assertDontSee('ForeignResident');
        $url = route('registry.history.prepare-arrivals', '2021-03');
        $this->post($url, ['history_record_id' => $foreign->id])->assertNotFound();
        $this->post(route('registry.history.prepare-arrivals', '2021-04'), ['history_record_id' => $record->id])->assertNotFound();
        $this->assertDatabaseCount('barangay_rbi_updates', 0);
        $this->post($url, ['history_record_id' => $record->id])->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::firstOrFail();
        $this->assertFalse(RbiEntryClassification::isClassified($report->rows[0], '2021-03'));
        $this->post($url, ['history_record_id' => $record->id])->assertRedirect(route('barangay.rbi-updates.residents', ['edit' => $report->id]).'#rbi-history-entry-'.$record->id);
        $this->get(route('barangay.rbi-updates.residents', ['edit' => $report->id]))->assertOk()->assertSee('id="rbi-history-entry-'.$record->id.'"', false);
        $rows = $report->rows;
        $rows[0]['entry_type'] = 'birth';
        $report->update(['rows' => $rows]);
        $this->get(route('rbi.arrivals.index'))->assertOk()->assertViewHas('historyClassifications', fn ($items) => $items[$record->id]['label'] === 'Birth / Newborn');
        $this->assertDatabaseCount('barangay_rbi_updates', 1);
    }

    public function test_undated_legacy_entries_do_not_establish_a_monthly_classification(): void
    {
        $staff = $this->staff();
        $record = NewInhabitant::create(['barangay_id' => $staff->barangay_id, 'household_number' => '7', 'first_name' => 'UndatedResident', 'last_name' => 'Example']);
        BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => 'Cawayan', 'rows' => [['history_record_id' => $record->id, 'entry_type' => 'birth']], 'status' => 'draft']);
        $this->actingAs($staff)->get(route('rbi.arrivals.index'))->assertOk()->assertSee('1 entries have no reporting month')
            ->assertViewHas('historyClassifications', fn ($items) => ! isset($items[$record->id]))->assertDontSee('UndatedResident');
    }
}
