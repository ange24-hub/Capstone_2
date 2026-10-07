<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RbiSignatureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function staff(): User
    {
        $barangay = Barangay::where('name', 'San Isidro')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $this->actingAs($staff);
        return $staff;
    }

    private function payload(string $section = 'residents'): array
    {
        return ['form_section' => $section, 'reporting_month' => '2026-09',
            'prepared_by' => 'Example Encoder', 'certified_by' => 'Example Secretary', 'attested_by' => 'Example Captain',
            'rows' => [['household_head' => 'Example, Parent', 'inhabitant_name' => 'Example, Parent',
                'sex' => 'Female', 'birth_date' => '1990-01-01', 'relationship' => 'Head']],
            'deceased_rows' => [['deceased_name' => 'Other, Person', 'death_date' => '2026-09-01']]];
    }

    private function draft(): BarangayRbiUpdate
    {
        $this->staff();
        $this->post(route('barangay.rbi-updates.store'), $this->payload())->assertSessionHasNoErrors();
        return BarangayRbiUpdate::sole();
    }

    public function test_resident_and_deceased_saves_ignore_signature_inputs_and_show_no_signature_controls(): void
    {
        $this->staff();
        foreach (['residents', 'deceased'] as $index => $section) {
            $this->get(route('barangay.rbi-updates.'.$section))->assertOk()
                ->assertDontSee('<canvas', false)->assertDontSee('type="file"', false);
            $payload = $this->payload($section);
            $payload['reporting_month'] = '2026-0'.($index + 8);
            $payload += ['prepared_signature_data' => self::SIGNATURE, 'attested_signature_data' => self::SIGNATURE,
                'certified_signature_upload' => UploadedFile::fake()->create('ignored.txt', 1)];
            $this->post(route('barangay.rbi-updates.store'), $payload)->assertSessionHasNoErrors();
            $report = BarangayRbiUpdate::latest('id')->firstOrFail();
            foreach (['prepared', 'certified', 'attested'] as $type) {
                $this->assertNull($report->{$type.'_signature_path'});
            }
            $this->assertSame('draft', $report->status);
            $this->assertNull($report->submitted_at);
        }
        $this->assertSame([], Storage::allFiles('rbi-signatures'));
    }

    public function test_history_actions_open_upload_and_draw_step_for_the_saved_report(): void
    {
        $report = $this->draft();
        foreach (['submit', 'consolidate'] as $action) {
            $url = route('barangay.rbi-updates.certification', ['rbiUpdate' => $report, 'action' => $action]);
            $this->get(route('barangay.rbi-updates.history'))->assertOk()->assertSee($url);
            $this->get($url)->assertOk()->assertSee('enctype="multipart/form-data"', false)
                ->assertSee('name="prepared_signature_upload"', false)->assertSee('<canvas', false)
                ->assertSee('Example Captain')->assertSee('rbi-signatures.js');
        }
        $this->assertSame('draft', $report->fresh()->status);
        $this->assertSame([], Storage::allFiles('rbi-signatures'));
    }

    public function test_uploaded_signatures_are_saved_at_submission_and_exported(): void
    {
        $report = $this->draft();
        $this->post(route('barangay.rbi-updates.submit', $report), [
            'prepared_signature_upload' => UploadedFile::fake()->image('encoder.jpg'),
            'certified_signature_upload' => UploadedFile::fake()->image('secretary.png'),
            'attested_signature_upload' => UploadedFile::fake()->image('captain.png'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $report->refresh();
        $this->assertSame('submitted', $report->status);
        $this->assertNotNull($report->submitted_at);
        foreach (['prepared', 'certified', 'attested'] as $type) {
            Storage::assertExists($report->{$type.'_signature_path'});
        }
        $this->get(route('rbi-updates.signature', [$report, 'secretary']))->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->get(route('rbi-updates.export-pdf', $report))->assertOk()->assertHeader('content-type', 'application/pdf');
        $word = $this->get(route('rbi-updates.export-word', $report))->assertOk()->streamedContent();
        $this->assertStringContainsString('secretary-signature.jpg', $word);
        $this->assertStringContainsString('certified-signature.png', $word);
    }

    public function test_consolidation_collects_signatures_and_later_submission_reuses_them(): void
    {
        $report = $this->draft();
        $this->post(route('barangay.rbi-updates.add-to-registry', $report), [
            'prepared_signature_data' => self::SIGNATURE, 'attested_signature_data' => self::SIGNATURE,
        ])->assertSessionHasNoErrors()->assertRedirect(route('barangay.rbi-updates.history'));
        $report->refresh();
        $this->assertSame('draft', $report->status);
        $this->assertSame(1, Inhabitant::count());
        $this->assertNotEmpty($report->rows[0]['inhabitant_id']);
        $paths = [$report->prepared_signature_path, $report->attested_signature_path];
        $this->post(route('barangay.rbi-updates.submit', $report))->assertSessionHasNoErrors();
        $report->refresh();
        $this->assertSame('submitted', $report->status);
        $this->assertSame($paths, [$report->prepared_signature_path, $report->attested_signature_path]);
        $this->assertCount(2, Storage::allFiles('rbi-signatures'));
    }

    public function test_saving_changes_preserves_existing_signatures_without_replacing_them(): void
    {
        $report = $this->draft();
        Storage::put('rbi-signatures/existing.png', base64_decode(explode(',', self::SIGNATURE)[1]));
        $report->update(['prepared_signature_path' => 'rbi-signatures/existing.png']);
        $this->put(route('barangay.rbi-updates.update', $report), $this->payload() + [
            'prepared_signature_data' => self::SIGNATURE, 'attested_signature_data' => self::SIGNATURE,
        ])->assertSessionHasNoErrors();
        $this->assertSame('rbi-signatures/existing.png', $report->fresh()->prepared_signature_path);
        $this->assertNull($report->fresh()->attested_signature_path);
        $this->assertCount(1, Storage::allFiles('rbi-signatures'));
    }

    public function test_missing_or_invalid_signatures_do_not_submit_or_create_registry_records(): void
    {
        $report = $this->draft();
        foreach (['submit', 'add-to-registry'] as $action) {
            $url = route('barangay.rbi-updates.'.$action, $report);
            $this->post($url)->assertSessionHasErrors(['prepared_signature_data', 'attested_signature_data']);
            $this->post($url, ['prepared_signature_data' => self::SIGNATURE,
                'attested_signature_upload' => UploadedFile::fake()->create('invalid.png', 1, 'image/png')])
                ->assertSessionHasErrors('attested_signature_upload');
            $this->post($url, ['prepared_signature_data' => self::SIGNATURE,
                'attested_signature_upload' => UploadedFile::fake()->image('large.jpg')->size(2049)])
                ->assertSessionHasErrors('attested_signature_upload');
            $this->post($url, ['prepared_signature_data' => self::SIGNATURE, 'attested_signature_data' => self::SIGNATURE,
                'certified_signature_data' => 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\ninvalid")])
                ->assertSessionHasErrors('certified_signature_data');
            $this->post($url, ['prepared_signature_data' => self::SIGNATURE, 'attested_signature_data' => self::SIGNATURE,
                'prepared_signature_upload' => UploadedFile::fake()->image('both.png')])
                ->assertSessionHasErrors('prepared_signature_data');
        }
        $this->assertSame('draft', $report->fresh()->status);
        $this->assertNull($report->fresh()->submitted_at);
        $this->assertSame(0, Inhabitant::count());
        $this->assertSame(0, Household::count());
        $this->assertSame([], Storage::allFiles('rbi-signatures'));
    }

    public function test_another_account_cannot_collect_or_upload_signatures_for_a_report(): void
    {
        $report = $this->draft();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $report->barangayUser->barangay_id]));
        $this->get(route('barangay.rbi-updates.certification', ['rbiUpdate' => $report, 'action' => 'submit']))->assertForbidden();
        foreach (['submit', 'add-to-registry'] as $action) {
            $this->post(route('barangay.rbi-updates.'.$action, $report), [
                'prepared_signature_data' => self::SIGNATURE, 'attested_signature_data' => self::SIGNATURE,
            ])->assertForbidden();
        }
        $this->assertSame([], Storage::allFiles('rbi-signatures'));
    }
}
