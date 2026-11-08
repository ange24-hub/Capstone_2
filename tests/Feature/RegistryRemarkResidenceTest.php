<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, MigrationRecord, User};
use App\Support\RegistryRemarkResidence as Rule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistryRemarkResidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_outside_notes_keep_registration_but_transfers_leave_consolidated_without_fake_dates(): void
    {
        $area=Barangay::where('name','Looc')->firstOrFail();$owner=User::factory()->create(['role'=>'barangay','barangay_id'=>$area->id]);
        $home=Household::create(['barangay_id'=>$area->id,'household_number'=>'NOTE-1']);
        $away=Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$home->id,'first_name'=>'OutsidePerson','last_name'=>'Fixture',
            'sex'=>'Female','status'=>'active','residence_status'=>'living_here','remarks'=>'AT CEBU']);
        $transfer=Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$home->id,'first_name'=>'TransferPerson','last_name'=>'Fixture',
            'sex'=>'Male','status'=>'active','remarks'=>'TRANSFERED TO CARNAGA']);
        $this->assertSame('active',$away->fresh()->status);$this->assertSame('living_elsewhere',$away->fresh()->residence_status);
        $this->assertSame('migrated_out',$transfer->fresh()->status);
        $this->assertSame(0,MigrationRecord::count());$this->assertSame(2,Inhabitant::count());
        $this->actingAs($owner)->get(route('barangay.registry.active'))->assertOk()->assertSee('OutsidePerson')->assertDontSee('TransferPerson');
        $this->get(route('registry.index'))->assertOk()->assertSee('OutsidePerson')->assertDontSee('TransferPerson');
        $this->get(route('barangay.registry.moved-out'))->assertOk()->assertSee('OutsidePerson')->assertSee('TransferPerson')->assertSee('Not recorded');
    }

    public function test_context_and_metadata_cannot_create_false_moves(): void
    {
        foreach(['LOOC','LIVING ALONE','AT HOME','IN SCHOOL','NOT TRANSFERRED','FOR TRANSFER TO CEBU','TRANSFERRED FROM CEBU',
            'BIRTHPLACE CEBU','FROM CEBU NOW LIVING IN LOOC','[Source birth_place: MANILA] [Household number missing in source; purok: SOGOD]']as$note){
            $this->assertNull(Rule::decide($note,'Looc')['action'],$note);
        }
        foreach(['CEBU','MANILA','SOGOD, SOUTHERN LEYTE','AT SAN ROQUE','OFW','JEDDAH','NOW IN POLAND']as$note)$this->assertSame('moved_out',Rule::decide($note,'Looc')['action'],$note);
        foreach(['TRANSFERED','TRANSFERRED TO CEBU','TRANSFFERED','TRANSFER TO SOGOD','TRANSFER']as$note)$this->assertSame('transferred',Rule::decide($note,'Looc')['action'],$note);
        $this->assertNull(Rule::decide('SAN ROQUE SOUTHERN LEYTE','San Roque')['action']);
        $this->assertSame('moved_out',Rule::decide('SAN ROQUE CEBU','San Roque')['action']);
    }

    public function test_later_remark_edits_apply_rule_while_explicit_confirmed_movement_remains_possible(): void
    {
        $area=Barangay::where('name','Looc')->firstOrFail();$home=Household::create(['barangay_id'=>$area->id,'household_number'=>'NOTE-2']);
        $person=Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$home->id,'first_name'=>'Edited','last_name'=>'Fixture','sex'=>'Female','status'=>'active','residence_status'=>'living_here']);
        $person->update(['remarks'=>'SOGOD']);$this->assertSame('living_elsewhere',$person->fresh()->residence_status);
        $person->update(['residence_status'=>'living_here','residence_source'=>'Confirmed in Resident Registry']);
        $this->assertSame('living_here',$person->fresh()->residence_status);
        $person->update(['remarks'=>'TRANSFERRED TO CEBU']);$this->assertSame('migrated_out',$person->fresh()->status);
        $this->assertSame(0,MigrationRecord::count());
    }
}
