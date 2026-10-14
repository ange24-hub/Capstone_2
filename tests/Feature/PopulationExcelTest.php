<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class PopulationExcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_download_is_a_complete_workbook_for_the_selected_section_and_barangay(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        $home = Household::create(['barangay_id' => $area->id, 'household_number' => '64.10']);
        $foreignHome = Household::create(['barangay_id' => $other->id, 'household_number' => '1']);
        for ($number = 1; $number <= 51; $number++) {
            Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $home->id,
                'first_name' => 'Resident '.$number, 'last_name' => 'Example', 'sex' => 'Female']);
        }
        Inhabitant::create(['barangay_id' => $other->id, 'household_id' => $foreignHome->id,
            'first_name' => 'ForeignResident', 'last_name' => 'Example', 'sex' => 'Male']);
        $staff = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);

        $this->actingAs($staff)->get(route('reports.population', ['barangay_id' => $area->id, 'section' => 'residents']))
            ->assertOk()->assertSee('Resident 1')->assertDontSee('Resident 9 Example')
            ->assertSee(route('reports.population.excel', ['barangay_id' => $area->id, 'section' => 'residents']))
            ->assertSee('population-spreadsheet.css')
            ->assertSee('REGION: VIII')->assertSee('PROVINCE: SOUTHERN LEYTE')
            ->assertSee('CITY/MUNICIPALITY: TOMAS OPPUS')->assertSee('BARANGAY: <strong>Looc</strong>', false);
        $response = $this->get(route('reports.population.excel', ['barangay_id' => $area->id, 'section' => 'residents']))
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('PK', $response->getContent());
        $this->withWorkbook($response->getContent(), function (ZipArchive $zip): void {
            $workbook = $zip->getFromName('xl/workbook.xml');
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('Registered residents', $workbook);
            $this->assertStringContainsString('Resident 1 Example', $sheet);
            $this->assertStringContainsString('Resident 51 Example', $sheet);
            $this->assertStringContainsString('D. BARANGAY:', $sheet);
            $this->assertStringContainsString('>Looc</t>', $sheet);
            $this->assertStringNotContainsString('ForeignResident', $sheet);
            $this->assertStringContainsString('topLeftCell="A10"', $sheet);
            $this->assertStringNotContainsString('Registry coverage', $sheet);
            $this->assertStringNotContainsString('completeness not verified', $sheet);
            $this->assertStringContainsString('DATE ACCOMPLISHED:', $sheet);
            $this->assertStringContainsString('mergeCell ref="C3:D3"', $sheet);
            $this->assertStringContainsString('mergeCell ref="F3:F6"', $sheet);
            $this->assertStringContainsString('autoFilter', $sheet);
            $this->assertNotFalse($zip->getFromName('xl/styles.xml'));
        });

        $print = $this->get(route('reports.population', ['barangay_id' => $area->id, 'section' => 'residents', 'print' => 1]))
            ->assertOk()->assertSee('Resident 9 Example')->assertSee('population-spreadsheet.css')
            ->assertSee('BARANGAY: <strong>Looc</strong>', false);
        $this->assertNull($print->viewData('detailPagination'));
    }

    public function test_summary_workbook_has_all_visible_tables_and_excel_scope_matches_screen_access(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        $home = Household::create(['barangay_id' => $area->id, 'household_number' => '1']);
        Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $home->id,
            'first_name' => 'A', 'last_name' => 'Resident', 'sex' => 'Female']);
        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $workbook = $this->actingAs($municipal)->get(route('reports.population.excel'))->assertOk()->getContent();
        $this->withWorkbook($workbook, function (ZipArchive $zip): void {
            $names = $zip->getFromName('xl/workbook.xml');
            foreach (['Summary', 'By Sex', 'By Age', 'By Barangay', 'Age by Barangay'] as $name) {
                $this->assertStringContainsString('name="'.$name.'"', $names);
            }
            $summary = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('Total registered residents', $summary);
            $this->assertStringContainsString('<c r="G10" s="5"><v>1</v></c>', $summary);
            $coverage = $zip->getFromName('xl/worksheets/sheet4.xml');
            $this->assertStringContainsString('Looc', $coverage);
            $this->assertStringContainsString('Canlupao', $coverage);
            $this->assertStringContainsString('Total recorded', $coverage);
            $this->assertStringContainsString('orientation="landscape"', $coverage);
        });

        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->actingAs($secretary)->get(route('reports.population.excel', ['barangay_id' => $other->id]))->assertForbidden();
        $this->get(route('reports.population.excel', ['section' => 'invalid']))->assertSessionHasErrors('section');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_RESIDENT]))
            ->get(route('reports.population.excel'))->assertForbidden();
    }

    public function test_municipal_reports_group_details_and_breakdowns_by_barangay_across_outputs(): void
    {
        $canlupao = Barangay::where('name', 'Canlupao')->firstOrFail();
        $looc = Barangay::where('name', 'Looc')->firstOrFail();
        foreach ([[$canlupao, 'Alpha'], [$looc, 'Beta']] as [$barangay, $name]) {
            $house = Household::create(['barangay_id' => $barangay->id, 'household_number' => '1']);
            Inhabitant::create([
                'barangay_id' => $barangay->id, 'household_id' => $house->id, 'family_number' => '1',
                'first_name' => $name, 'last_name' => 'Resident', 'sex' => 'Female',
                'birth_date' => '1940-01-01', 'remarks' => 'PWD',
            ]);
        }
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]));

        foreach (['residents', 'seniors', 'pwd'] as $section) {
            $screen = $this->get(route('reports.population', ['section' => $section]))->assertOk()
                ->assertSeeInOrder(['population-barangay-heading">Barangay Canlupao', 'Alpha Resident',
                    'population-barangay-heading">Barangay Looc', 'Beta Resident'], false);
            $this->assertCount(2, $screen->viewData('detailGroups'));
        }
        $print = $this->get(route('reports.population', ['section' => 'residents', 'print' => 1]))->assertOk()
            ->assertSeeInOrder(['population-barangay-heading">Barangay Canlupao', 'Alpha Resident',
                'population-barangay-heading">Barangay Looc', 'Beta Resident'], false);
        $this->assertNull($print->viewData('detailPagination'));

        $this->get(route('reports.population', ['section' => 'families']))->assertOk()
            ->assertSeeInOrder(['Barangay Canlupao', 'Family 1', 'Alpha Resident',
                'Barangay Looc', 'Family 1', 'Beta Resident']);
        $this->get(route('reports.population.pdf', ['section' => 'residents']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $workbook = $this->get(route('reports.population.excel', ['section' => 'residents']))->assertOk()->getContent();
        $this->withWorkbook($workbook, function (ZipArchive $zip): void {
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('<c r="A10" s="6" t="inlineStr"><is><t>Barangay Canlupao</t>', $sheet);
            $this->assertStringContainsString('Barangay Looc', $sheet);
            $this->assertTrue(strpos($sheet, 'Barangay Canlupao') < strpos($sheet, 'Alpha Resident'));
            $this->assertTrue(strpos($sheet, 'Barangay Looc') < strpos($sheet, 'Beta Resident'));
            $this->assertStringContainsString('horizontal="center"', $zip->getFromName('xl/styles.xml'));
            $this->assertStringNotContainsString('FF166534', $zip->getFromName('xl/styles.xml'));
        });

        foreach (['sex' => 'By Barangay', 'ages' => 'Age by Barangay'] as $section => $sheetName) {
            $this->get(route('reports.population', ['section' => $section]))->assertOk()
                ->assertSee($section === 'sex' ? 'Summary by barangay' : 'Age groups by barangay');
            $this->withWorkbook($this->get(route('reports.population.excel', ['section' => $section]))->assertOk()->getContent(),
                function (ZipArchive $zip) use ($sheetName): void {
                    $this->assertStringContainsString('name="'.$sheetName.'"', $zip->getFromName('xl/workbook.xml'));
                    $this->assertStringContainsString('Canlupao', $zip->getFromName('xl/worksheets/sheet2.xml'));
                    $this->assertStringContainsString('Looc', $zip->getFromName('xl/worksheets/sheet2.xml'));
                });
        }
        $this->assertStringNotContainsString('#166534', file_get_contents(public_path('css/population-spreadsheet.css')));
    }

    private function withWorkbook(string $contents, callable $inspect): void
    {
        $path = tempnam(sys_get_temp_dir(), 'population-test-');
        file_put_contents($path, $contents);
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            try {
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $name = $zip->getNameIndex($index);
                    if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
                        $xml = new DOMDocument;
                        $this->assertTrue($xml->loadXML($zip->getFromIndex($index)), $name.' must be valid XML');
                    }
                    if (str_starts_with($name, 'xl/worksheets/')) {
                        foreach (['A. REGION:', 'VIII', 'B. PROVINCE:', 'SOUTHERN LEYTE', 'C. CITY/MUNICIPALITY:', 'TOMAS OPPUS', 'D. BARANGAY:', 'E. HOUSEHOLD NO.:', 'DATE ACCOMPLISHED:', 'PSGC'] as $label) {
                            $this->assertStringContainsString($label, $zip->getFromIndex($index));
                        }
                    }
                }
                $inspect($zip);
            } finally {
                $zip->close();
            }
        } finally {
            unlink($path);
        }
    }
}
