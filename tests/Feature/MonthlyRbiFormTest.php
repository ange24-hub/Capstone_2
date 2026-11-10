<?php

namespace Tests\Feature;

use App\Models\{Barangay, BarangayRbiUpdate, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyRbiFormTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role'=>User::ROLE_BARANGAY,
            'barangay_id'=>Barangay::where('name','Maslog')->firstOrFail()->id]);
    }

    private function payload(): array
    {
        return ['form_section'=>'monthly','reporting_month'=>'2026-10',
            'rows'=>[['household_head'=>'Sample Family','inhabitant_name'=>'Sample Resident']],
            'deceased_rows'=>[['household_head'=>'Sample Family','deceased_name'=>'Sample Deceased','death_date'=>'2026-10-01']]];
    }

    public function test_one_monthly_save_keeps_residents_deceased_and_certification_together(): void
    {
        $staff = $this->staff();
        $response = $this->actingAs($staff)->get(route('barangay.rbi-updates.residents'))->assertOk()
            ->assertSeeInOrder(['id="family-section"','id="deceased-section"','id="certification-section"'], false);
        preg_match('/<nav class="side-nav"[^>]*>(.*?)<\/nav>/s', $response->getContent(), $navigation);
        $this->assertSame(1, substr_count($navigation[1], 'aria-current="page"'));
        $this->post(route('barangay.rbi-updates.store'), $this->payload()+['prepared_by'=>'Sample Encoder','certified_by'=>'Sample Secretary'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $report = BarangayRbiUpdate::firstOrFail();
        $this->assertSame('Sample Resident', $report->rows[0]['inhabitant_name']);
        $this->assertSame('Sample Deceased', $report->deceased_rows[0]['deceased_name']);
        $this->assertSame('Sample Secretary', $report->certified_by);
        $this->assertDatabaseCount('barangay_rbi_updates', 1);
        $updated = $this->payload();
        $updated['rows'][0]['inhabitant_name'] = 'Updated Resident';
        $updated['deceased_rows'][0]['deceased_name'] = 'Updated Deceased';
        $this->put(route('barangay.rbi-updates.update', $report), $updated)->assertSessionHasNoErrors();
        $this->assertSame('Updated Resident', $report->fresh()->rows[0]['inhabitant_name']);
        $this->assertSame('Updated Deceased', $report->fresh()->deceased_rows[0]['deceased_name']);
        $this->get(route('barangay.rbi-updates.residents', ['edit'=>$report->id]))->assertOk()
            ->assertSee('Updated Resident')->assertSee('Updated Deceased');
    }

    public function test_a_deceased_only_month_does_not_create_a_blank_resident_or_erase_existing_residents(): void
    {
        $this->actingAs($this->staff());
        $payload = $this->payload();
        unset($payload['rows']);
        $payload['no_new_residents'] = '1';
        $this->post(route('barangay.rbi-updates.store'), $payload)->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::firstOrFail();
        $this->assertSame([], $report->rows);
        $this->assertCount(1, $report->deceased_rows);
        $this->put(route('barangay.rbi-updates.update', $report), $this->payload())->assertSessionHasNoErrors();
        $original = $report->fresh()->toArray();
        $this->put(route('barangay.rbi-updates.update', $report), $payload)->assertSessionHasErrors('rows');
        $this->assertSame($original, $report->fresh()->toArray());
    }

    public function test_empty_deceased_lines_are_not_saved_and_a_month_cannot_be_duplicated(): void
    {
        $this->actingAs($this->staff());
        $payload = $this->payload();
        $payload['deceased_rows'] = [['deceased_name'=>'','death_date'=>'']];
        $this->post(route('barangay.rbi-updates.store'), $payload)->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::firstOrFail();
        $this->assertSame([], $report->deceased_rows);
        $this->post(route('barangay.rbi-updates.store'), $this->payload())->assertSessionHasErrors('reporting_month');
        $this->assertDatabaseCount('barangay_rbi_updates', 1);
        $this->assertSame([], $report->fresh()->deceased_rows);
    }

    public function test_optional_household_heads_preserve_separate_families_and_their_members(): void
    {
        $staff=$this->staff();$this->actingAs($staff);
        $one=(string) \Illuminate\Support\Str::uuid();$two=(string) \Illuminate\Support\Str::uuid();
        $rows=[['family_key'=>$one,'inhabitant_name'=>'Sample, One','sex'=>'Male'],
            ['family_key'=>$one,'inhabitant_name'=>'Sample, Two','sex'=>'Female'],
            ['family_key'=>$two,'inhabitant_name'=>'Sample, Three','sex'=>'Male']];
        $this->post(route('barangay.rbi-updates.store'),['form_section'=>'monthly','reporting_month'=>'2026-10','rows'=>$rows,
            'deceased_rows'=>[['family_key'=>$two,'deceased_name'=>'Sample Death','death_date'=>'2026-10-01']]])->assertSessionHasNoErrors();
        $report=BarangayRbiUpdate::firstOrFail();
        $this->assertSame('', $report->rows[0]['household_head']);
        $families=$report->rbiFamilies()->orderBy('position')->with('members','deceasedRecords')->get();
        $this->assertCount(2,$families);$this->assertCount(2,$families[0]->members);$this->assertCount(1,$families[1]->members);
        $this->assertCount(0,$families[0]->deceasedRecords);$this->assertCount(1,$families[1]->deceasedRecords);
        $response=$this->get(route('barangay.rbi-updates.residents',['edit'=>$report->id]))->assertOk();
        $this->assertCount(2,$response->viewData('draftRbiUpdate')->rbiFamilies);
        $this->put(route('barangay.rbi-updates.update',$report),['form_section'=>'monthly','reporting_month'=>'2026-10','rows'=>$report->rows,'deceased_rows'=>$report->deceased_rows])->assertSessionHasNoErrors();
        $this->assertSame(2,$report->rbiFamilies()->count());
        $this->get(route('rbi-updates.export-pdf',$report))->assertOk()->assertHeader('content-type','application/pdf');
        \Illuminate\Support\Facades\DB::transaction(fn()=>\App\Support\RbiReportRegistry::add($staff->barangay,$report));
        $people=\App\Models\Inhabitant::where('barangay_id',$staff->barangay_id)->orderBy('id')->get();
        $this->assertCount(3,$people);$this->assertSame($people[0]->household_id,$people[1]->household_id);
        $this->assertNotSame($people[0]->household_id,$people[2]->household_id);
    }
}
