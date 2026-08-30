<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BarangayRbiUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for isolated RBI feature tests.');
        }

        parent::setUp();
    }

    public function test_barangay_can_complete_and_submit_the_monthly_rbi_form(): void
    {
        Storage::fake('local');
        $signatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $barangay = Barangay::where('name', 'Carnaga')->firstOrFail();
        $secretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => $barangay->id,
        ]);

        $this->actingAs($secretary)
            ->get(route('dashboard.barangay'))
            ->assertOk()
            ->assertSee('Open RBI Forms')
            ->assertDontSee('Complete RBI Form and Submit as Word Document');

        $this->actingAs($secretary)
            ->get(route('barangay.rbi-updates.index'))
            ->assertOk()
            ->assertSee('Updates of Barangay Registry of Barangay Inhabitants')
            ->assertSee('Add Another Family Form')
            ->assertSee('Add Member to This Family')
            ->assertSee('Monthly Form Certification')
            ->assertSee('Name of Household Head')
            ->assertSee('Deceased Registered Barangay Inhabitants')
            ->assertSee('Barangay Carnaga');

        $this->actingAs($secretary)
            ->post(route('barangay.rbi-updates.store'), [
                'reporting_month' => '2026-08',
                'prepared_by' => 'Mercidita L. Saga',
                'attested_by' => 'Wenceslao L. Resus',
                'prepared_signature_data' => $signatureData,
                'attested_signature_data' => $signatureData,
                'rows' => [[
                    'household_head' => 'Pedro Dela Cruz',
                    'inhabitant_name' => 'Dela Cruz, Ana Maria S.',
                    'sex' => 'Female',
                    'birth_date' => '2001-04-15',
                    'birth_place' => 'Tomas Oppus',
                    'civil_status' => 'Single',
                    'occupation' => 'Teacher',
                    'relationship' => 'Daughter',
                ], [
                    'household_head' => 'Juan Santos',
                    'inhabitant_name' => 'Santos, Maria L.',
                    'sex' => 'Female',
                    'relationship' => 'Spouse',
                ]],
                'deceased_rows' => [[
                    'household_head' => 'Juan Santos',
                    'deceased_name' => 'Juan Santos',
                    'death_date' => '2026-08-12',
                ]],
                'submit_to_municipal' => '1',
            ])
            ->assertRedirect(route('barangay.rbi-updates.index', ['new' => 1]))
            ->assertSessionHas('submitted_rbi_update_id');

        $update = BarangayRbiUpdate::firstOrFail();

        $this->assertSame('Carnaga', $update->barangay_name);
        $this->assertSame('Pedro Dela Cruz', $update->household_head);
        $this->assertSame('2026-08', $update->reporting_month->format('Y-m'));
        $this->assertSame(BarangayRbiUpdate::STATUS_SUBMITTED, $update->status);
        $this->assertNull($update->families);
        $this->assertCount(2, $update->rows);
        $this->assertSame('Pedro Dela Cruz', $update->rows[0]['household_head']);
        $this->assertSame('Juan Santos', $update->rows[1]['household_head']);
        $this->assertSame('Dela Cruz, Ana Maria S.', $update->rows[0]['inhabitant_name']);
        $this->assertSame('Juan Santos', $update->deceased_rows[0]['deceased_name']);
        $this->assertCount(2, $update->rbiFamilies);
        $this->assertSame('Juan Santos', $update->rbiFamilies->last()->deceasedRecords->first()->deceased_name);
        Storage::disk('local')->assertExists($update->prepared_signature_path);
        Storage::disk('local')->assertExists($update->attested_signature_path);

        $this->actingAs($secretary)
            ->get(route('barangay.rbi-updates.index', ['new' => 1]))
            ->assertOk()
            ->assertSee('Submission Complete')
            ->assertSee('View Submitted Form')
            ->assertSee('Download Consolidated PDF')
            ->assertSee('Download Word Copy')
            ->assertDontSee('Pedro Dela Cruz');

        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);

        $this->actingAs($municipal)
            ->get(route('dashboard.municipal'))
            ->assertOk()
            ->assertSee('Direct Municipal Receiving')
            ->assertSee('Monthly RBI Reports by Barangay')
            ->assertSee('Carnaga')
            ->assertSee('August 2026')
            ->assertSee('AI-assisted Decision Support');

        $this->actingAs($municipal)
            ->get(route('rbi-updates.show', $update))
            ->assertOk()
            ->assertSee('Updates of Barangay Registry of Barangay Inhabitants')
            ->assertSee('A. Newly Registered Barangay Inhabitants')
            ->assertSee('Monthly Form Certification')
            ->assertSee('Pedro Dela Cruz')
            ->assertSee('Juan Santos')
            ->assertSee('Deceased Registered Barangay Inhabitants')
            ->assertSee('Dela Cruz, Ana Maria S.')
            ->assertSee('Juan Santos');

        $pdfResponse = $this->actingAs($municipal)
            ->get(route('rbi-updates.export-pdf', $update))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $pdfDocument = $pdfResponse->getContent();
        $this->assertStringStartsWith('%PDF-', $pdfDocument);
        $this->assertStringContainsString('RBI_Carnaga_August_2026.pdf', $pdfResponse->headers->get('content-disposition'));

        $this->actingAs($municipal)
            ->get(route('rbi-updates.signature', [$update, 'secretary']))
            ->assertOk();

        $this->actingAs($municipal)
            ->get(route('rbi-updates.signature', [$update, 'captain']))
            ->assertOk();

        $wordResponse = $this->actingAs($municipal)
            ->get(route('rbi-updates.export-word', $update))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $wordDocument = $wordResponse->streamedContent();
        $this->assertStringStartsWith('PK', $wordDocument);

        $temporaryDocument = tempnam(sys_get_temp_dir(), 'rbi-test-');
        file_put_contents($temporaryDocument, $wordDocument);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($temporaryDocument));
        $documentXml = $zip->getFromName('word/document.xml');
        $documentRelationships = $zip->getFromName('word/_rels/document.xml.rels');
        $sealImage = $zip->getFromName('word/media/tomas-oppus-seal.png');
        $zip->close();
        unlink($temporaryDocument);

        $this->assertStringContainsString('Updates of Barangay Registry of Barangay Inhabitants', $documentXml);
        $this->assertStringContainsString('Name of Household Head', $documentXml);
        $this->assertStringContainsString('Pedro Dela Cruz', $documentXml);
        $this->assertStringContainsString('Juan Santos', $documentXml);
        $this->assertStringContainsString('<w:tblW w:w="13555"', $documentXml);
        $this->assertStringContainsString('<w:pgSz w:w="15840" w:h="12240" w:orient="landscape"', $documentXml);
        $this->assertStringContainsString('tomas-oppus-seal.png', $documentRelationships);
        $this->assertNotFalse($sealImage);
        $this->assertStringNotContainsString('Family Form 1', $documentXml);
        $this->assertSame(2, substr_count($documentXml, 'Updates of Barangay Registry of Barangay Inhabitants'));
        $this->assertSame(1, substr_count($documentXml, '<w:br w:type="page"/>'));
        $this->assertSame(2, substr_count($documentXml, 'Mercidita L. Saga'));
        $this->assertSame(2, substr_count($documentXml, 'Wenceslao L. Resus'));
    }

    public function test_barangay_can_save_multiple_family_forms_in_one_monthly_draft(): void
    {
        $barangay = Barangay::where('name', 'Carnaga')->firstOrFail();
        $secretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => $barangay->id,
        ]);

        $rows = [
            [
                'household_head' => 'Dela Cruz Family',
                'inhabitant_name' => 'Ana Dela Cruz',
                'relationship' => 'Child',
            ],
            [
                'household_head' => 'Santos Family',
                'inhabitant_name' => 'Juan Santos',
                'relationship' => 'Child',
            ],
        ];

        $this->actingAs($secretary)
            ->post(route('barangay.rbi-updates.store'), [
                'reporting_month' => '2026-08',
                'rows' => $rows,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('barangay_rbi_updates', 1);
        $this->assertDatabaseHas('barangay_rbi_updates', [
            'barangay_user_id' => $secretary->id,
            'household_head' => 'Dela Cruz Family',
            'status' => BarangayRbiUpdate::STATUS_DRAFT,
        ]);

        $draft = BarangayRbiUpdate::where('barangay_user_id', $secretary->id)->firstOrFail();
        $this->assertNull($draft->families);
        $this->assertCount(2, $draft->rows);

        $this->actingAs($secretary)
            ->get(route('barangay.rbi-updates.index'))
            ->assertOk()
            ->assertSee('Continue Monthly Draft')
            ->assertSee('Dela Cruz Family')
            ->assertSee('Santos Family');

        $this->actingAs($secretary)
            ->get(route('barangay.rbi-updates.index', ['edit' => $draft->id]))
            ->assertOk()
            ->assertSee('Continue Monthly Draft')
            ->assertSee('All families remain together in one monthly record and one consolidated PDF.');
    }

    public function test_large_family_continues_before_the_next_household_starts(): void
    {
        $barangay = Barangay::where('name', 'Carnaga')->firstOrFail();
        $secretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => $barangay->id,
        ]);
        $report = BarangayRbiUpdate::create([
            'barangay_user_id' => $secretary->id,
            'barangay_name' => 'Carnaga',
            'reporting_month' => '2026-08-01',
            'status' => BarangayRbiUpdate::STATUS_DRAFT,
        ]);
        $largeFamily = $report->rbiFamilies()->create([
            'household_head' => 'Juan Dela Cruz',
            'position' => 0,
        ]);

        foreach (range(1, 8) as $position) {
            $largeFamily->members()->create([
                'inhabitant_name' => 'Dela Cruz Member '.$position,
                'relationship' => $position === 1 ? 'Head' : 'Child',
                'position' => $position - 1,
            ]);
        }

        $nextFamily = $report->rbiFamilies()->create([
            'household_head' => 'Roberto Santos',
            'position' => 1,
        ]);
        $nextFamily->members()->create([
            'inhabitant_name' => 'Roberto Santos',
            'relationship' => 'Head',
            'position' => 0,
        ]);

        $report->load(['rbiFamilies.members', 'rbiFamilies.deceasedRecords', 'deceasedRecords']);
        $controller = app(\App\Http\Controllers\BarangayRbiUpdateController::class);
        $method = new \ReflectionMethod($controller, 'pdfPages');
        $pages = $method->invoke($controller, $report);

        $this->assertCount(3, $pages);
        $this->assertSame('Juan Dela Cruz', $pages[0]['household_head']);
        $this->assertCount(7, $pages[0]['members']);
        $this->assertSame('Juan Dela Cruz', $pages[1]['household_head']);
        $this->assertTrue($pages[1]['continued']);
        $this->assertCount(1, $pages[1]['members']);
        $this->assertSame('Roberto Santos', $pages[2]['household_head']);
        $this->assertFalse($pages[2]['continued']);
    }

    public function test_submitted_monthly_form_can_be_reopened_and_updated_for_both_dashboards(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('rbi-signatures/secretary.png', 'signature');
        Storage::disk('local')->put('rbi-signatures/captain.png', 'signature');
        $barangay = Barangay::where('name', 'Carnaga')->firstOrFail();
        $secretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => $barangay->id,
        ]);
        $report = BarangayRbiUpdate::create([
            'barangay_user_id' => $secretary->id,
            'barangay_name' => $barangay->name,
            'household_head' => 'Dela Cruz Family',
            'reporting_month' => '2026-08-01',
            'prepared_by' => 'Secretary Name',
            'prepared_signature_path' => 'rbi-signatures/secretary.png',
            'attested_by' => 'Captain Name',
            'attested_signature_path' => 'rbi-signatures/captain.png',
            'status' => BarangayRbiUpdate::STATUS_SUBMITTED,
            'rows' => [[
                'household_head' => 'Dela Cruz Family',
                'inhabitant_name' => 'Ana Dela Cruz',
            ]],
            'submitted_at' => now()->subHour(),
        ]);

        $this->actingAs($secretary)
            ->get(route('barangay.rbi-updates.index', ['edit' => $report->id]))
            ->assertOk()
            ->assertSee('Update Submitted Monthly Form')
            ->assertSee('Ana Dela Cruz')
            ->assertSee('Update Municipal Copy');

        $this->actingAs($secretary)
            ->put(route('barangay.rbi-updates.update', $report), [
                'reporting_month' => '2026-08',
                'prepared_by' => 'Secretary Name',
                'attested_by' => 'Captain Name',
                'rows' => [[
                    'household_head' => 'Dela Cruz Family',
                    'inhabitant_name' => 'Ana Dela Cruz',
                ], [
                    'household_head' => 'Santos Family',
                    'inhabitant_name' => 'Juan Santos',
                ]],
                'submit_to_municipal' => '1',
            ])
            ->assertRedirect(route('barangay.rbi-updates.index', ['new' => 1]))
            ->assertSessionHas('status', 'Monthly RBI form submitted successfully. The form below is now ready for a new report.');

        $this->assertDatabaseCount('barangay_rbi_updates', 1);
        $report->refresh();
        $this->assertSame(BarangayRbiUpdate::STATUS_SUBMITTED, $report->status);
        $this->assertCount(2, $report->rows);
        $this->assertSame('Santos Family', $report->rows[1]['household_head']);

        $this->actingAs($secretary)
            ->get(route('barangay.rbi-updates.index', ['new' => 1]))
            ->assertOk()
            ->assertSee('Submission Complete')
            ->assertDontSee('Ana Dela Cruz')
            ->assertDontSee('Juan Santos');

        $this->actingAs($secretary)
            ->get(route('dashboard.barangay'))
            ->assertOk()
            ->assertSee('Monthly RBI Form History')
            ->assertSee('Update form');

        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($municipal)
            ->get(route('dashboard.municipal'))
            ->assertOk()
            ->assertSee('Carnaga')
            ->assertSee('2 family/families');

        $this->actingAs($municipal)
            ->get(route('rbi-updates.show', $report))
            ->assertOk()
            ->assertSee('Ana Dela Cruz')
            ->assertSee('Juan Santos');
    }
}
