<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class RegistryPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_print_scopes_include_all_matching_residents_and_preserve_the_original_table(): void
    {
        $barangay = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Hugpa')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => 'HH-01']);

        foreach (range(1, 27) as $number) {
            $this->resident($barangay, $household, 'Here'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), Inhabitant::RESIDENCE_HERE);
        }
        $this->resident($barangay, $household, 'ElsewherePerson', Inhabitant::RESIDENCE_ELSEWHERE);
        $this->resident($barangay, $household, 'UnconfirmedPerson', Inhabitant::RESIDENCE_UNCONFIRMED);
        $this->resident($barangay, $household, 'MovedPerson', Inhabitant::RESIDENCE_HERE, Inhabitant::STATUS_MIGRATED_OUT);
        $otherHousehold = Household::create(['barangay_id' => $other->id, 'household_number' => 'OTHER']);
        $this->resident($other, $otherHousehold, 'OtherBarangayPerson', Inhabitant::RESIDENCE_HERE);

        $this->actingAs($staff)->get(route('barangay.registry.active'))->assertOk()
            ->assertSee('Consolidated household records')->assertSee('Download Consolidated Excel')
            ->assertDontSee('View all RBI columns');

        $consolidated = $this->get(route('barangay.registry.print', ['scope' => 'consolidated']))->assertOk();
        $consolidated->assertSee('Here27')->assertSee('ElsewherePerson')->assertSee('UnconfirmedPerson')
            ->assertDontSee('MovedPerson')->assertDontSee('OtherBarangayPerson')
            ->assertDontSee('RESIDENT<br>NO.', false)->assertSee('size: 13in 8.5in')
            ->assertSee('Download Excel');

        $registered = $this->get(route('barangay.registry.print', ['scope' => 'registered']))->assertOk();
        $registered->assertSee('Here27')->assertDontSee('ElsewherePerson')->assertDontSee('UnconfirmedPerson');

        $elsewhere = $this->get(route('barangay.registry.print', ['scope' => 'living_elsewhere']))->assertOk();
        $elsewhere->assertSee('ElsewherePerson')->assertDontSee('Here01')->assertDontSee('UnconfirmedPerson');

        $unconfirmed = $this->get(route('barangay.registry.print', ['scope' => 'unconfirmed']))->assertOk();
        $unconfirmed->assertSee('UnconfirmedPerson')->assertDontSee('Here01')->assertDontSee('ElsewherePerson');

        foreach ([
            Inhabitant::RESIDENCE_HERE => 'registered',
            Inhabitant::RESIDENCE_ELSEWHERE => 'living_elsewhere',
            Inhabitant::RESIDENCE_UNCONFIRMED => 'unconfirmed',
        ] as $residenceScope => $reportScope) {
            $this->get(route('barangay.residence.index', ['scope' => $residenceScope]))->assertOk()
                ->assertSee('Download Excel')
                ->assertSee(route('barangay.registry.download', ['scope' => $reportScope]), false)
                ->assertDontSee('Download CSV');
        }

        $unconfirmedSheet = $this->worksheet($this->get(route('barangay.registry.download', ['scope' => 'unconfirmed']))->assertOk()->getContent());
        $this->assertStringContainsString('UnconfirmedPerson', $unconfirmedSheet);
        $this->assertStringContainsString('ref="C8:F8"', $unconfirmedSheet);
        $this->assertStringNotContainsString('Here01', $unconfirmedSheet);

        $this->get(route('reports.population'))->assertOk()->assertDontSee('Consolidated RBI registry');
    }

    public function test_excel_download_matches_the_printed_registry_and_uses_long_paper(): void
    {
        $barangay = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Hugpa')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => '001']);
        $otherHousehold = Household::create(['barangay_id' => $other->id, 'household_number' => '002']);
        $this->resident($barangay, $household, 'HerePerson', Inhabitant::RESIDENCE_HERE);
        $this->resident($barangay, $household, 'ElsewherePerson', Inhabitant::RESIDENCE_ELSEWHERE);
        $this->resident($barangay, $household, 'MovedPerson', Inhabitant::RESIDENCE_HERE, Inhabitant::STATUS_MIGRATED_OUT);
        $this->resident($other, $otherHousehold, 'OtherBarangayPerson', Inhabitant::RESIDENCE_HERE);

        $response = $this->actingAs($staff)->get(route('barangay.registry.download', ['scope' => 'consolidated']))->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $path = tempnam(sys_get_temp_dir(), 'registry-test-');
        $this->assertNotFalse($path);
        try {
            file_put_contents($path, $response->getContent());
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            try {
                $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
                $this->assertIsString($sheet);
                $this->assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet));
                $this->assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($zip->getFromName('xl/styles.xml')));
                $this->assertStringContainsString('CONSOLIDATED HOUSEHOLD RECORD OF BARANGAY INHABITANTS (RBI)', $sheet);
                $this->assertStringContainsString('A. REGION: VIII', $sheet);
                $this->assertStringContainsString('D. BARANGAY: LOOC', $sheet);
                $this->assertStringContainsString('HOUSEHOLDS: 1', $sheet);
                $this->assertStringContainsString('RESIDENTS: 2', $sheet);
                $this->assertStringContainsString('RELATIONSHIP TO HH HEAD', $sheet);
                $this->assertStringContainsString('ref="C8:F8"', $sheet);
                $this->assertStringContainsString('r="R10"', $sheet);
                $this->assertSame(18, preg_match_all('/<c r="[A-R]10"/', $sheet));
                $this->assertStringContainsString('paperWidth="13in" paperHeight="8.5in"', $sheet);
                $this->assertStringContainsString('HerePerson', $sheet);
                $this->assertStringContainsString('ElsewherePerson', $sheet);
                $this->assertStringNotContainsString('MovedPerson', $sheet);
                $this->assertStringNotContainsString('OtherBarangayPerson', $sheet);
            } finally {
                $zip->close();
            }
        } finally {
            unlink($path);
        }

        $registered = $this->get(route('barangay.registry.download', ['scope' => 'registered']))->assertOk()->getContent();
        $this->assertStringContainsString('HerePerson', $this->worksheet($registered));
        $this->assertStringNotContainsString('ElsewherePerson', $this->worksheet($registered));
        $elsewhere = $this->get(route('barangay.registry.download', ['scope' => 'living_elsewhere']))->assertOk()->getContent();
        $this->assertStringContainsString('ElsewherePerson', $this->worksheet($elsewhere));
        $this->assertStringNotContainsString('HerePerson', $this->worksheet($elsewhere));
    }

    public function test_invalid_scope_and_non_staff_users_cannot_print_the_registry(): void
    {
        $barangay = Barangay::where('name', 'Looc')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $resident = User::factory()->create(['role' => User::ROLE_RESIDENT, 'barangay_id' => $barangay->id]);

        $this->actingAs($staff)->get(route('barangay.registry.print', ['scope' => 'invalid']))->assertStatus(302);
        $this->actingAs($resident)->get(route('barangay.registry.print'))->assertForbidden();
        $this->actingAs($staff)->get(route('barangay.registry.download', ['scope' => 'invalid']))->assertStatus(302);
        $this->actingAs($resident)->get(route('barangay.registry.download'))->assertForbidden();
    }

    private function resident(Barangay $barangay, Household $household, string $firstName, string $residence, string $status = Inhabitant::STATUS_ACTIVE): void
    {
        Inhabitant::create([
            'barangay_id' => $barangay->id, 'household_id' => $household->id,
            'first_name' => $firstName, 'last_name' => 'Example', 'sex' => 'Female',
            'status' => $status, 'residence_status' => $residence,
        ]);
    }

    private function worksheet(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'registry-test-');
        $this->assertNotFalse($path);
        try {
            file_put_contents($path, $contents);
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            try {
                $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
                $this->assertIsString($sheet);

                return $sheet;
            } finally {
                $zip->close();
            }
        } finally {
            unlink($path);
        }
    }
}
