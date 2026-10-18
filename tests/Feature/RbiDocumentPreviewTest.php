<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbiDocumentPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_templates_use_only_the_assigned_barangays_official_names(): void
    {
        foreach (['Biasong', 'Carnaga'] as $name) {
            $barangay = Barangay::where('name', $name)->firstOrFail();
            $staff = User::factory()->create(['name' => 'Demo Account Name',
                'role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
            $this->actingAs($staff);
            $data = ['registryBarangay' => $barangay, 'barangay' => $barangay,
                'activeHouseholdOptions' => collect(), 'newInhabitantRecords' => collect(),
                'families' => collect(), 'month' => '2026-09'];

            foreach (['registry._biasong_new_inhabitants', 'registry.new-inhabitants-pdf'] as $template) {
                $html = view($template, $data)->render();
                foreach (['NENA E. GONZAGA', 'MARY GRACE M. POLISTICO', 'MARCOS L. MAQUILANG', $staff->name] as $demoName) {
                    $this->assertStringNotContainsString($demoName, $html);
                }
                $this->assertStringContainsString('Certified Correct:', $html);
                $this->assertStringContainsString('Verified by:', $html);

                $barangay->secretary_name = $name.' Saved Secretary';
                $barangay->punong_barangay_name = $name.' Saved Chair';
                $html = view($template, $data)->render();
                $this->assertStringContainsString($barangay->secretary_name, $html);
                $this->assertStringContainsString($barangay->punong_barangay_name, $html);
                $barangay->secretary_name = null;
                $barangay->punong_barangay_name = null;
            }
        }
    }

    public function test_blank_certification_names_do_not_fall_back_to_the_account_name(): void
    {
        $barangay = Barangay::where('name', 'Carnaga')->firstOrFail();
        $staff = User::factory()->create(['name' => 'Demo Account Name',
            'role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $this->actingAs($staff)->post(route('barangay.rbi-updates.store'), [
            'reporting_month' => '2026-09',
            'rows' => [['household_head' => 'Saved Head', 'inhabitant_name' => 'Saved Resident']],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::where('barangay_user_id', $staff->id)->firstOrFail();
        $this->assertEmpty($report->prepared_by);
        $this->assertEmpty($report->certified_by);
        $this->assertEmpty($report->attested_by);

        $this->get(route('rbi-updates.show', $report))->assertOk()
            ->assertSee('Saved Resident')->assertSee('<strong></strong>', false);
        $this->get(route('barangay.rbi-updates.certification', ['rbiUpdate' => $report, 'action' => 'submit']))
            ->assertOk()->assertSee('name="certified_by" type="text" value=""', false);
        $this->get(route('rbi-updates.export-pdf', $report))->assertOk()->assertHeader('content-type', 'application/pdf');
        $word = $this->get(route('rbi-updates.export-word', $report))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'rbi-blank-names-');
        try {
            file_put_contents($path, $word->streamedContent());
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            try {
                $xml = $zip->getFromName('word/document.xml');
                $this->assertStringNotContainsString($staff->name, $xml);
                $this->assertStringContainsString('Saved Resident', $xml);
            } finally {
                $zip->close();
            }
        } finally {
            unlink($path);
        }
    }

    public function test_preview_is_read_only_and_word_uses_compact_rows_with_signatures(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', 'San Isidro')->firstOrFail()->id]);
        $report = BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => 'San Isidro',
            'reporting_month' => '2026-09-01', 'status' => 'draft', 'prepared_by' => 'Prepared Official', 'certified_by' => 'Certified Official', 'attested_by' => 'Noted Official',
            'rows' => [['household_head' => 'Household Head', 'inhabitant_name' => 'Example, Child', 'sex' => 'Female']]]);
        $this->actingAs($staff)->get(route('rbi-updates.show', $report))->assertOk()
            ->assertSee('Print Report')
            ->assertSee('rbi-word-page')->assertSee('Example, Child')->assertSee('Prepared Official')
            ->assertSee('Certified Official')->assertSee('BHW / Encoder')->assertSee('Barangay Captain / Punong Barangay')->assertDontSee('Brgy. Secretary')
            ->assertDontSee('contenteditable')->assertDontSee('Update Form')
            ->assertViewHas('wordPages', fn ($pages) => count($pages) === 1 && $pages[0]['show_signatures']);
        $response = $this->get(route('rbi-updates.export-word', $report))->assertOk();
        $pdf = $this->get(route('rbi-updates.export-pdf', $report))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf->getContent()), 'A household form and its signatures must fit on one PDF page.');
        $path = tempnam(sys_get_temp_dir(), 'rbi-layout-test-');
        try {
            file_put_contents($path, $response->streamedContent());
            $zip = new \ZipArchive();
            $zip->open($path);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            $this->assertNotFalse(simplexml_load_string($xml));
            $this->assertStringContainsString('Prepared Official', $xml);
            $this->assertStringContainsString('<w:cantSplit/>', $xml);
            $this->assertStringNotContainsString('w:val="4000"', $xml);
            $this->assertStringNotContainsString('w:val="2200"', $xml);
            $this->assertStringNotContainsString('<w:br w:type="page"/>', $xml);
        } finally {
            unlink($path);
        }
        $rows = [];
        foreach (range(1, 15) as $i) $rows[] = ['household_head' => 'Household Head', 'inhabitant_name' => 'Member '.$i];
        $report->update(['rows' => $rows]);
        $this->get(route('rbi-updates.show', $report))->assertOk()->assertSee('Member 15')
            ->assertViewHas('wordPages', fn ($pages) => count($pages) === 3 && count($pages[2]['members']) === 1 && $pages[2]['show_signatures']);
        $continuedPdf = $this->get(route('rbi-updates.export-pdf', $report))->assertOk();
        $this->assertSame(3, preg_match_all('/\/Type\s*\/Page\b/', $continuedPdf->getContent()), 'Continued forms must retain one physical page per household page.');
        $other = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $staff->barangay_id]);
        $this->actingAs($other)->get(route('rbi-updates.show', $report))->assertForbidden();
    }
}
