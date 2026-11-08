<?php

namespace Tests\Feature;

use App\Models\{Barangay, BarangayRbiUpdate, Household, Inhabitant, MigrationRecord, NewInhabitant, User};
use App\Services\{BrbiArchiveImport, MigrationForecastHistory};
use App\Support\BrbiArchiveWorkbook as Workbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BrbiArchiveImportTest extends TestCase
{
    use RefreshDatabase;

    private function history(Barangay $area, string $first, ?string $month='2021-03'): NewInhabitant
    {
        return NewInhabitant::create(['barangay_id'=>$area->id,'first_name'=>$first,'last_name'=>'Fixture','birth_date'=>'2000-01-01',
            'household_number'=>'','sex'=>'Female','source_position'=>12,'reporting_month'=>$month ? $month.'-01' : null]);
    }

    private function plan(Barangay $area, ?array $registry=null): array
    {
        return ['source_hashes'=>[],'areas'=>[['barangay_id'=>$area->id,'name'=>$area->name,'source_file'=>'FIXTURE.xlsx',
            'registry'=>$registry,'new_rows'=>[],'existing_history_skipped'=>0]]];
    }

    public function test_known_new_entries_become_unsigned_drafts_and_unknown_dates_stay_pending(): void
    {
        $this->travelTo(Carbon::parse('2026-11-08 10:00:00'));
        $area=Barangay::where('name','Anahawan')->firstOrFail();
        $owner=User::factory()->create(['role'=>'barangay','barangay_id'=>$area->id]);
        $known=$this->history($area,'Known'); $this->history($area,'Unknown',null);
        $result=app(BrbiArchiveImport::class)->apply($this->plan($area))[0];
        $report=BarangayRbiUpdate::sole();
        $this->assertSame($owner->id,$report->barangay_user_id);
        $this->assertSame('2021-03',$report->reporting_month->format('Y-m'));
        $this->assertSame('draft',$report->status);$this->assertNull($report->submitted_at);
        $this->assertSame($known->id,$report->rows[0]['history_record_id']);
        $this->assertStringContainsString('Household not recorded',$report->rows[0]['household_head']);
        $this->assertArrayNotHasKey('entry_type',$report->rows[0]);
        $this->assertSame('2026-11',Carbon::parse($report->rows[0]['first_saved_at'])->format('Y-m'));
        $this->assertSame(1,$report->rbiFamilies()->first()->members()->count());
        $this->actingAs($owner)->get(route('barangay.rbi-updates.residents',['edit'=>$report->id]))
            ->assertOk()->assertSee('Known')->assertSee('Fixture');
        $this->assertSame(1,$result['form_rows_added']);$this->assertCount(1,$result['pending']);
        $again=app(BrbiArchiveImport::class)->apply($this->plan($area))[0];
        $this->assertSame(0,$again['form_rows_added']);$this->assertSame(1,BarangayRbiUpdate::count());
    }

    public function test_existing_draft_rows_classification_and_first_save_remain_exactly_unchanged(): void
    {
        $area=Barangay::where('name','Banday')->firstOrFail();
        $owner=User::factory()->create(['role'=>'barangay','barangay_id'=>$area->id]);
        $history=$this->history($area,'Already');$this->history($area,'Additional');
        $original=['history_record_id'=>$history->id,'first_name'=>'Already','last_name'=>'Fixture','birth_date'=>'2000-01-01',
            'household_head'=>'Original household','entry_type'=>'birth','first_saved_at'=>'2021-04-02T10:00:00+08:00','source_row_id'=>'existing-source'];
        $report=BarangayRbiUpdate::create(['barangay_user_id'=>$owner->id,'barangay_name'=>$area->name,'reporting_month'=>'2021-03-01',
            'status'=>'draft','rows'=>[$original],'deceased_rows'=>[]]);
        $result=app(BrbiArchiveImport::class)->apply($this->plan($area))[0];
        $this->assertSame($original,$report->fresh()->rows[0]);$this->assertCount(2,$report->fresh()->rows);
        $this->assertSame(2,$report->fresh()->rbiFamilies()->withCount('members')->get()->sum('members_count'));
        $this->assertSame(1,$result['already_in_forms']);$this->assertSame(1,$result['form_rows_added']);
    }

    public function test_submitted_forms_and_populated_registries_are_preserved(): void
    {
        $area=Barangay::where('name','Bogo')->firstOrFail();
        $owner=User::factory()->create(['role'=>'barangay','barangay_id'=>$area->id]);
        $this->history($area,'Missing');
        $report=BarangayRbiUpdate::create(['barangay_user_id'=>$owner->id,'barangay_name'=>$area->name,'reporting_month'=>'2021-03-01',
            'status'=>'submitted','submitted_at'=>now(),'rows'=>[],'deceased_rows'=>[]]);
        $before=$report->fresh()->getAttributes();
        $result=app(BrbiArchiveImport::class)->apply($this->plan($area))[0];
        $this->assertSame($before,$report->fresh()->getAttributes());$this->assertSame(0,$result['form_rows_added']);
        $this->assertCount(1,$result['pending']);
        $home=Household::create(['barangay_id'=>$area->id,'household_number'=>'EXISTING']);
        Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$home->id,'first_name'=>'Real','last_name'=>'Fixture','sex'=>'Male']);
        $this->expectException(\RuntimeException::class);
        app(BrbiArchiveImport::class)->apply($this->plan($area,['residents'=>[],'deceased'=>[],'pending'=>[]]));
    }

    public function test_only_unlinked_demo_placeholders_are_replaced_without_inventing_movements(): void
    {
        $area=Barangay::where('name','Maanyag')->firstOrFail();
        User::factory()->create(['role'=>'barangay','barangay_id'=>$area->id]);
        $home=Household::create(['barangay_id'=>$area->id,'household_number'=>'DEMO-MIGRATION-V1']);
        $person=Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$home->id,'first_name'=>'Demo','last_name'=>'Fixture','sex'=>'Male','remarks'=>MigrationForecastHistory::DEMO_MARKER]);
        MigrationRecord::create(['barangay_id'=>$area->id,'inhabitant_id'=>$person->id,'type'=>'out','movement_date'=>'2026-01-01','reason'=>MigrationForecastHistory::DEMO_MARKER]);
        $registry=Workbook::registry('Maanyag',['CONSOLIDATED RBI'=>[11=>['B'=>'5','C'=>'Imported','D'=>'Resident','G'=>'HEAD','J'=>'36526','K'=>'26','L'=>'F']]]);
        $result=app(BrbiArchiveImport::class)->apply($this->plan($area,$registry))[0];
        $this->assertSame(1,$result['residents_added']);$this->assertSame(1,Inhabitant::count());
        $this->assertSame('Imported',Inhabitant::sole()->last_name);$this->assertSame(0,MigrationRecord::count());
        $this->assertSame('5',Household::sole()->household_number);
    }

    public function test_workbook_maps_shifted_fields_and_does_not_guess_bare_month_years(): void
    {
        $this->assertNull(Workbook::month('SEPTEMBER'));
        $this->assertSame('2024-01',Workbook::month('JANUARY 04,2024'));
        $this->assertNull(Workbook::date('NOVEMBER 2020'));
        $this->assertSame('2020-09',Workbook::month('SEPTEMBER','2020'));
        $rows=Workbook::newEntries('Anahawan','NIF',[2=>['A'=>'1','B'=>'Last','C'=>'First','F'=>'DAUGHTER','G'=>'Purok 1','H'=>'Place',
            'I'=>'36526','J'=>'26','K'=>'F','L'=>'Single','M'=>'College','N'=>'JANUARY 04,2024']]);
        $this->assertSame('2000-01-01',$rows[0]['birth_date']);$this->assertSame('Female',$rows[0]['sex']);
        $this->assertSame('2024-01',$rows[0]['reporting_month']);$this->assertSame('Purok 1',$rows[0]['complete_address']);
        $this->assertNull($rows[0]['religion']);
        $maanyag=Workbook::newEntries('Maanyag','NEW',[2=>['B'=>'Last','C'=>'First','F'=>'36526','H'=>'M','M'=>'SEPTEMBER']]);
        $this->assertNull($maanyag[0]['reporting_month']);
        $this->assertStringNotContainsString('SEPTEMBER',$maanyag[0]['remarks']);
        $shifted=Workbook::newEntries('Canlupao','NEW INHABITANTS',[2=>['B'=>'Last','C'=>'First','E'=>'HEAD','F'=>'Purok 1',
            'G'=>'Place','H'=>'36526','I'=>'26','J'=>'M','K'=>'M','L'=>'College','M'=>'Worker','N'=>'SEPTEMBER 10, 2024']]);
        $this->assertNull($shifted[0]['suffix']);$this->assertSame('HEAD',$shifted[0]['relationship_to_head']);
        $this->assertSame('2024-09',$shifted[0]['reporting_month']);
        $mixed=Workbook::registry('Cambite',['CONSOLIDATED BRBI'=>[], 'DECEASED'=>[29=>['B'=>'Last','C'=>'First','F'=>'WIFE','G'=>'Purok 4','H'=>'Place','L'=>'F','M'=>'MARRIED','P'=>'SC']]]);
        $this->assertSame('WIFE',$mixed['deceased'][0]['relationship_to_head']);
        $this->assertSame('Purok 4',$mixed['deceased'][0]['purok']);
        $this->assertSame('Female',$mixed['deceased'][0]['sex']);
        $missingSex=Workbook::registry('Maanyag',['CONSOLIDATED RBI'=>[11=>['B'=>'1','C'=>'Last','D'=>'First','G'=>'HEAD','J'=>'36526','K'=>'26','M'=>'M','N'=>'College']]]);
        $this->assertNull($missingSex['residents'][0]['sex']);
    }

    public function test_pending_assigned_secretary_can_own_an_import_draft_without_being_approved(): void
    {
        $area=Barangay::where('name','Banday')->firstOrFail();
        $owner=User::factory()->create(['role'=>'barangay','barangay_id'=>$area->id,'approval_status'=>User::APPROVAL_PENDING]);
        $this->history($area,'Pending owner draft');
        app(BrbiArchiveImport::class)->apply($this->plan($area));
        $this->assertSame($owner->id,BarangayRbiUpdate::sole()->barangay_user_id);
        $this->assertSame(User::APPROVAL_PENDING,$owner->fresh()->approval_status);
        $this->actingAs($owner)->get(route('barangay.rbi-updates.history'))->assertRedirect(route('approval.pending'));
    }
}
