<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbiDuplicateEntriesTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', 'Maslog')->value('id')]);
    }

    private function row(): array
    {
        return ['household_head' => 'Santos Family', 'first_name' => 'Maria', 'last_name' => 'Santos', 'birth_date' => '1990-01-02'];
    }

    public function test_duplicate_resident_warns_preserves_input_and_does_not_save(): void
    {
        $this->actingAs($this->staff());
        $url = route('barangay.rbi-updates.residents');
        $this->from($url)->post(route('barangay.rbi-updates.store'), [
            'reporting_month' => '2026-09', 'form_section' => 'residents',
            'rows' => [$this->row(), array_replace($this->row(), ['first_name' => ' MARIA ', 'last_name' => 'santos'])],
        ])->assertRedirect($url)->assertSessionHasErrors('duplicates')->assertSessionHasInput('rows.0.first_name', 'Maria');
        $this->assertDatabaseCount('barangay_rbi_updates', 0);
        $this->get($url)->assertOk()->assertSee('Duplicate entry warning')->assertSee('rbi-duplicates.js');
    }

    public function test_updates_exclude_self_and_reject_repeated_rows_without_changing_saved_data(): void
    {
        $this->actingAs($this->staff());
        $payload = ['reporting_month' => '2026-09', 'rows' => [$this->row()]];
        $this->post(route('barangay.rbi-updates.store'), $payload)->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::firstOrFail();
        $this->put(route('barangay.rbi-updates.update', $report), $payload)->assertSessionHasNoErrors();
        $this->put(route('barangay.rbi-updates.update', $report), array_replace($payload, ['rows' => [$this->row(), $this->row()]]))
            ->assertSessionHasErrors('duplicates');
        $this->assertCount(1, $report->fresh()->rows);
        $this->post(route('barangay.rbi-updates.store'), array_replace($payload, ['reporting_month' => '2026-10']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('barangay_rbi_updates', 2);
    }

    public function test_same_names_with_different_birth_dates_are_allowed(): void
    {
        $this->actingAs($this->staff())->post(route('barangay.rbi-updates.store'), [
            'reporting_month' => '2026-09', 'rows' => [$this->row(), array_replace($this->row(), ['birth_date' => '2001-01-02'])],
        ])->assertSessionHasNoErrors();
        $this->assertCount(2, BarangayRbiUpdate::firstOrFail()->rows);
    }

    public function test_duplicate_deceased_records_are_blocked_on_save_and_legacy_submission(): void
    {
        $user = $this->staff();
        $deceased = ['deceased_name' => 'Juan Santos', 'death_date' => '2026-09-01'];
        $this->actingAs($user)->post(route('barangay.rbi-updates.store'), [
            'form_section' => 'deceased', 'reporting_month' => '2026-09', 'deceased_rows' => [$deceased, $deceased],
        ])->assertSessionHasErrors('duplicates');
        $report = BarangayRbiUpdate::create(['barangay_user_id' => $user->id, 'barangay_name' => $user->barangay->name,
            'reporting_month' => '2026-09-01', 'status' => 'draft', 'deceased_rows' => [$deceased, $deceased]]);
        $this->post(route('barangay.rbi-updates.submit', $report))->assertSessionHasErrors('duplicates');
        $this->assertSame('draft', $report->fresh()->status);
    }

    public function test_saved_duplicates_are_scoped_to_barangay_and_reporting_month(): void
    {
        $owner = $this->staff();
        BarangayRbiUpdate::create(['barangay_user_id' => $owner->id, 'barangay_name' => $owner->barangay->name,
            'reporting_month' => '2026-09-01', 'status' => 'draft', 'rows' => [$this->row()]]);
        $second = $this->staff();
        $payload = ['reporting_month' => '2026-09', 'rows' => [$this->row()]];
        $this->actingAs($second)->post(route('barangay.rbi-updates.store'), $payload)->assertSessionHasErrors('duplicates');
        $other = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => Barangay::where('name', 'Looc')->value('id')]);
        $this->actingAs($other)->post(route('barangay.rbi-updates.store'), $payload)->assertSessionHasNoErrors();
    }
}
