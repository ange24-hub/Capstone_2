<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class RbiSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_barangay_open_the_same_spreadsheet_with_the_original_print_form(): void
    {
        [$owner, $report] = $this->report();
        $admin = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($admin)->get(route('rbi-updates.show', $report))->assertOk()
            ->assertSee('Excel preview')->assertSee('RBI Monthly Report.xlsx')->assertSee('Download Excel')
            ->assertSee(route('rbi-updates.export-excel', $report))
            ->assertSee('rbi-spreadsheet.css')->assertSee('rbi-sheet-row-number')
            ->assertSee('Family One')->assertSee('Member 9')->assertSee('Family Two')->assertSee('Second Child')
            ->assertSee('Deceased 4')->assertSee('Encoder Official')->assertSee('Secretary Official')
            ->assertSee('rbi-report-print-document')->assertDontSee('Download Word Document')
            ->assertDontSee('contenteditable');
        $this->actingAs($owner)->get(route('rbi-updates.show', $report))->assertOk()
            ->assertSee('Excel preview')->assertSee('Download Excel')->assertSee('rbi-report-print-document')
            ->assertDontSee('Download Word Document');
    }

    public function test_excel_contains_every_household_continuation_and_signature_as_valid_open_xml(): void
    {
        Storage::fake('local');
        [$owner, $report] = $this->report();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::put('signatures/encoder.png', $png);
        $report->update(['prepared_signature_path' => 'signatures/encoder.png']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]));
        $response = $this->get(route('rbi-updates.export-excel', $report))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('rbi-monthly-san-isidro-2026-09.xlsx', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('PK', $response->getContent());
        $path = tempnam(sys_get_temp_dir(), 'rbi-workbook-test-');
        file_put_contents($path, $response->getContent());
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            try {
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $name = $zip->getNameIndex($index);
                    if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
                        $document = new DOMDocument;
                        $this->assertTrue($document->loadXML($zip->getFromIndex($index)), $name);
                    }
                }
                $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
                $xml = new DOMDocument;
                $xml->loadXML($sheet);
                $xpath = new DOMXPath($xml);
                $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $text = $xml->textContent;
                foreach (['RECORD OF BARANGAY INHABITANTS BY HOUSEHOLD', 'A. REGION: VIII', 'B. PROVINCE: SOUTHERN LEYTE',
                    'C. CITY/MUNICIPALITY: TOMAS OPPUS', 'D. BARANGAY: San Isidro', 'E. HOUSEHOLD NO.: 0075',
                    'DATE ACCOMPLISHED: 09/30/2026', 'SEPTEMBER 2026', 'Family One', 'Family Two', 'CONTINUED',
                    'Last name', 'School grade/year/level completed', 'Middle', 'Jr.', 'Son', 'Purok 2 & 3', 'Tomas Oppus',
                    '09-10-20', 'Single', 'Grade 1', 'Catholic', 'Student', '=SUM(1,1)', 'Second Child',
                    'Encoder Official', 'Secretary Official', 'Captain Official'] as $value) {
                    $this->assertStringContainsString($value, $text);
                }
                foreach (range(1, 9) as $number) {
                    $this->assertStringContainsString('Member '.$number, $text);
                }
                foreach (range(1, 4) as $number) {
                    $this->assertSame(1, substr_count($text, 'Deceased '.$number));
                }
                $this->assertSame(0, $xpath->query('//s:f')->length, 'User values must be literal text, never formulas.');
                $this->assertSame('M', $xpath->evaluate('string(//s:c[@r="J12"]/s:is/s:t)'));
                $this->assertStringContainsString('orientation="landscape"', $sheet);
                $this->assertStringContainsString('topLeftCell="A12"', $sheet);
                $this->assertStringContainsString('<rowBreaks count="3"', $sheet);
                $this->assertStringContainsString('A10:D10', $sheet);
                $this->assertStringContainsString('E10:E11', $sheet);
                $this->assertSame($png, $zip->getFromName('xl/media/secretary.png'));
                $this->assertStringContainsString('r:embed="secretary"', $zip->getFromName('xl/drawings/drawing1.xml'));
                $this->assertStringContainsString('../media/secretary.png', $zip->getFromName('xl/drawings/_rels/drawing1.xml.rels'));
                $this->assertNotFalse($zip->getFromName('xl/styles.xml'));
            } finally {
                $zip->close();
            }
        } finally {
            unlink($path);
        }
    }

    public function test_excel_respects_report_ownership_and_submitted_admin_access_and_empty_reports_still_open(): void
    {
        [$owner, $report] = $this->report();
        $url = route('rbi-updates.export-excel', $report);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($owner)->get($url)->assertOk();
        $other = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $owner->barangay_id]);
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_RESIDENT]))->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]));
        $report->update(['status' => BarangayRbiUpdate::STATUS_DRAFT]);
        $this->get($url)->assertForbidden();
        $this->get(route('rbi-updates.show', $report))->assertForbidden();
        $report->update(['status' => BarangayRbiUpdate::STATUS_SUBMITTED, 'rows' => [], 'deceased_rows' => []]);
        $this->get(route('rbi-updates.show', $report))->assertOk()->assertSee('Excel preview');
        $this->get($url)->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_legacy_report_print_and_excel_preview_show_the_completion_date_without_changing_the_saved_form(): void
    {
        [$owner, $report] = $this->report();
        $report->update(['as_of_date' => null, 'submitted_at' => '2026-09-30 18:30:00']);
        $this->actingAs($owner)->get(route('rbi-updates.show', $report))->assertOk()
            ->assertSee('DATE ACCOMPLISHED: 10/01/2026')->assertSee('10/01/2026');
        $this->assertNull($report->fresh()->as_of_date);
    }

    private function report(): array
    {
        $owner = User::factory()->create(['role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', 'San Isidro')->firstOrFail()->id]);
        $rows = [];
        foreach (range(1, 9) as $number) {
            $rows[] = ['household_head' => 'Family One', 'household_number' => '0075', 'last_name' => 'Example',
                'first_name' => 'Member '.$number, 'middle_name' => 'Middle', 'suffix' => 'Jr.', 'relationship' => 'Son',
                'complete_address' => 'Purok 2 & 3', 'birth_place' => 'Tomas Oppus', 'birth_date' => '2020-09-10',
                'recorded_age' => '6', 'sex' => 'Male', 'civil_status' => 'Single', 'education_level' => 'Grade 1',
                'religion' => 'Catholic', 'occupation' => 'Student', 'remarks' => '=SUM(1,1)'];
        }
        $rows[] = ['household_head' => 'Family Two', 'household_number' => '0080', 'inhabitant_name' => 'Second Child', 'sex' => 'Female'];
        $report = BarangayRbiUpdate::create(['barangay_user_id' => $owner->id, 'barangay_name' => 'San Isidro',
            'reporting_month' => '2026-09-01', 'as_of_date' => '2026-09-30', 'status' => BarangayRbiUpdate::STATUS_SUBMITTED,
            'prepared_by' => 'Encoder Official', 'certified_by' => 'Secretary Official', 'attested_by' => 'Captain Official',
            'rows' => $rows, 'deceased_rows' => array_map(fn ($number) => ['deceased_name' => 'Deceased '.$number,
                'death_date' => '2026-09-15'], range(1, 4))]);

        return [$owner, $report];
    }
}
