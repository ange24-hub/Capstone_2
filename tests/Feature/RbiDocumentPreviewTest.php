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

    public function test_preview_is_read_only_and_word_uses_compact_rows_with_signatures(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', 'San Isidro')->firstOrFail()->id]);
        $report = BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => 'San Isidro',
            'reporting_month' => '2026-09-01', 'status' => 'draft', 'prepared_by' => 'Prepared Official', 'attested_by' => 'Noted Official',
            'rows' => [['household_head' => 'Household Head', 'inhabitant_name' => 'Example, Child', 'sex' => 'Female']]]);
        $this->actingAs($staff)->get(route('rbi-updates.show', $report))->assertOk()
            ->assertSee('rbi-word-page')->assertSee('Example, Child')->assertSee('Prepared Official')
            ->assertDontSee('contenteditable')->assertDontSee('Update Form')
            ->assertViewHas('wordPages', fn ($pages) => count($pages) === 1 && $pages[0]['show_signatures']);
        $response = $this->get(route('rbi-updates.export-word', $report))->assertOk();
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
        $other = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $staff->barangay_id]);
        $this->actingAs($other)->get(route('rbi-updates.show', $report))->assertForbidden();
    }
}
