<?php

namespace Tests\Feature;

use App\Models\{Barangay, BarangayRbiUpdate, Household, Inhabitant, NewInhabitant, User};
use App\Services\{BrbiAppendOnlySync, BrbiArchiveImport, MigrationForecastHistory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class BrbiAppendOnlySyncTest extends TestCase
{
    use RefreshDatabase;

    private function source(array $residents = [], array $history = []): array
    {
        return ['areas'=>[['barangay_id'=>999999, 'name'=>'Anahawan', 'source_file'=>'MISSING-SYNC-FIXTURE.xlsx',
            'registry'=>['residents'=>$residents, 'deceased'=>[], 'pending'=>[]], 'new_rows'=>$history]]];
    }

    private function person(string $first): array
    {
        return ['first_name'=>$first, 'last_name'=>'Fixture', 'birth_date'=>'2000-01-01', 'household_number'=>'7',
            'sex'=>'Female', '_transferred'=>false, '_needs_residence_review'=>false, '_source'=>'Anahawan fixture row'];
    }

    private function apply(array $source): array
    {
        Storage::fake('local');
        return app(BrbiAppendOnlySync::class)->apply($source, Storage::disk('local')->path('backups/run'));
    }

    public function test_append_and_second_run_preserve_every_existing_record_and_skip_duplicates(): void
    {
        $area = Barangay::where('name','Anahawan')->firstOrFail();
        $home = Household::create(['barangay_id'=>$area->id,'household_number'=>'7','address'=>'Keep existing address']);
        $old = Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$home->id,'first_name'=>'Existing',
            'last_name'=>'Fixture','birth_date'=>'2000-01-01','sex'=>'Female','remarks'=>'Keep original']);
        $demo = Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$home->id,'first_name'=>'Demo',
            'last_name'=>'Fixture','sex'=>'Male','remarks'=>MigrationForecastHistory::DEMO_MARKER]);
        $before = $old->fresh()->getAttributes(); $demoBefore = $demo->fresh()->getAttributes(); $homeBefore=$home->fresh()->getAttributes();
        $source = $this->source([$this->person('Existing'),$this->person('New')]);
        $result = $this->apply($source);
        $this->assertTrue($result['existing_records_preserved']);
        $this->assertSame(1,$result['barangays'][0]['residents_added']);
        $this->assertSame(1,$result['skipped']);
        $this->assertSame($before,$old->fresh()->getAttributes());
        $this->assertSame($demoBefore,$demo->fresh()->getAttributes());
        $this->assertSame($homeBefore,$home->fresh()->getAttributes());
        $this->assertDatabaseCount('households',1);
        $this->assertDatabaseHas('inhabitants',['first_name'=>'New','barangay_id'=>$area->id,'household_id'=>$home->id]);
        $again=app(BrbiAppendOnlySync::class)->apply($source,Storage::disk('local')->path('backups/again'));
        $this->assertSame(0,$again['barangays'][0]['residents_added']);
        $this->assertDatabaseCount('inhabitants',3);
        Storage::disk('local')->assertExists('backups/run/inhabitants.jsonl');
    }

    public function test_unknown_birth_date_name_collision_is_held_for_review_instead_of_duplicated(): void
    {
        $area=Barangay::where('name','Anahawan')->firstOrFail();
        $home=Household::create(['barangay_id'=>$area->id,'household_number'=>'7']);
        Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$home->id,'first_name'=>'Same',
            'last_name'=>'Fixture','birth_date'=>null,'sex'=>'Female']);
        $result=$this->apply($this->source([$this->person('Same')]));
        $this->assertSame(1,$result['identity_review']);
        $this->assertSame(0,$result['barangays'][0]['residents_added']);
        $this->assertDatabaseCount('inhabitants',1);
    }

    public function test_missing_history_adds_unsigned_draft_without_changing_existing_submitted_forms(): void
    {
        $area=Barangay::where('name','Anahawan')->firstOrFail();
        $owner=User::factory()->create(['role'=>'barangay','barangay_id'=>$area->id]);
        $form=BarangayRbiUpdate::create(['barangay_user_id'=>$owner->id,'barangay_name'=>$area->name,
            'reporting_month'=>'2021-03-01','status'=>'submitted','submitted_at'=>now(),'rows'=>[],'deceased_rows'=>[]]);
        $before=$form->fresh()->getAttributes();
        $known=$this->person('Known')+['reporting_month'=>'2021-04','source_position'=>2];
        $unknown=$this->person('Undated')+['reporting_month'=>null,'source_position'=>3];
        $result=$this->apply($this->source([],[$known,$unknown]));
        $this->assertSame($before,$form->fresh()->getAttributes());
        $this->assertSame(2,$result['barangays'][0]['history_added']);
        $this->assertDatabaseHas('new_inhabitants',['first_name'=>'Undated','reporting_month'=>null]);
        $draft=BarangayRbiUpdate::where('status','draft')->sole();
        $this->assertNull($draft->submitted_at);$this->assertNull($draft->source_file_path);
        $this->assertSame('2021-04',$draft->reporting_month->format('Y-m'));
        $this->assertCount(1,$draft->rows);
        $this->assertDatabaseCount('migration_records',0);
    }

    public function test_any_existing_record_mutation_rolls_back_entire_sync(): void
    {
        $area=Barangay::where('name','Anahawan')->firstOrFail();
        $home=Household::create(['barangay_id'=>$area->id,'household_number'=>'7','address'=>'Original']);
        $mock=\Mockery::mock(BrbiArchiveImport::class);
        $mock->shouldReceive('apply')->once()->andReturnUsing(function()use($home){
            DB::table('households')->where('id',$home->id)->update(['address'=>'Unwanted mutation']);
            NewInhabitant::create(['barangay_id'=>$home->barangay_id,'first_name'=>'Uncommitted','last_name'=>'Fixture','household_number'=>'','sex'=>'Female']);
            return [];
        });
        $this->app->instance(BrbiArchiveImport::class,$mock);
        try{$this->apply($this->source());$this->fail('Mutation must fail');}
        catch(\RuntimeException $e){$this->assertStringContainsString('rolled back',$e->getMessage());}
        $this->assertSame('Original',$home->fresh()->address);
        $this->assertDatabaseCount('new_inhabitants',0);
    }

    public function test_existing_draft_rows_and_members_are_preserved_when_missing_new_rows_are_appended(): void
    {
        $area=Barangay::where('name','Anahawan')->firstOrFail();
        $owner=User::factory()->create(['role'=>'barangay','barangay_id'=>$area->id]);
        $row=['first_name'=>'Original','last_name'=>'Fixture','birth_date'=>'1990-01-01',
            'household_head'=>'Original household','entry_type'=>'birth','first_saved_at'=>'2021-04-02T10:00:00+08:00'];
        $form=BarangayRbiUpdate::create(['barangay_user_id'=>$owner->id,'barangay_name'=>$area->name,
            'reporting_month'=>'2021-04-01','status'=>'draft','rows'=>[$row],'deceased_rows'=>[]]);
        $family=$form->rbiFamilies()->create(['household_head'=>'Original household','position'=>0]);
        $member=$family->members()->create(['inhabitant_name'=>'Original Fixture','position'=>0]);
        $familyBefore=$family->fresh()->getAttributes();$memberBefore=$member->fresh()->getAttributes();
        $source=$this->source([],[$this->person('Additional')+['reporting_month'=>'2021-04','source_position'=>2]]);
        $result=$this->apply($source);
        $this->assertTrue($result['existing_records_preserved']);
        $this->assertSame($row,$form->fresh()->rows[0]);$this->assertCount(2,$form->fresh()->rows);
        $this->assertSame($familyBefore,$family->fresh()->getAttributes());
        $this->assertSame($memberBefore,$member->fresh()->getAttributes());
        $this->assertDatabaseCount('barangay_rbi_updates',1);
    }

    public function test_conflicting_birth_dates_and_cross_registry_identity_require_review(): void
    {
        $area=Barangay::where('name','Anahawan')->firstOrFail();
        $home=Household::create(['barangay_id'=>$area->id,'household_number'=>'7']);
        Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$home->id,'first_name'=>'Conflicting',
            'last_name'=>'Fixture','birth_date'=>'1999-01-01','sex'=>'Female']);
        \App\Models\DeceasedInhabitant::create(['barangay_id'=>$area->id,'household_number'=>'7',
            'first_name'=>'Deceased','last_name'=>'Fixture','birth_date'=>'2000-01-01','sex'=>'Female']);
        $result=$this->apply($this->source([$this->person('Conflicting'),$this->person('Deceased')]));
        $this->assertSame(2,$result['identity_review']);
        $this->assertSame(0,$result['barangays'][0]['residents_added']);
        $this->assertDatabaseCount('inhabitants',1);
    }
}
