<?php
namespace Tests\Feature;
use App\Models\{Barangay, Household, Inhabitant, MigrationRecord, RegistryActivity, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class RegistryTransferConfirmationTest extends TestCase {
 use RefreshDatabase;
 public function test_transfer_requires_confirmation_and_is_logged_and_isolated(): void {
  $b = Barangay::where('name','San Roque')->firstOrFail();
  $u = User::factory()->create(['role'=>'barangay','barangay_id'=>$b->id]);
  $h = Household::create(['barangay_id'=>$b->id,'household_number'=>'1']);
  $r = Inhabitant::create(['barangay_id'=>$b->id,'household_id'=>$h->id,'first_name'=>'Person','last_name'=>'Example','sex'=>'Male','status'=>'active','residence_status'=>'living_here']);
  $data = ['barangay_id'=>$b->id,'household_number'=>'1','first_name'=>'Person','last_name'=>'Example','sex'=>'Male','status'=>'active','residence_status'=>'transferred','transfer_destination'=>'Cebu'];
  $this->actingAs($u)->put(route('registry.update',$r),$data)->assertSessionHasErrors('transfer_confirmed');
  $this->assertSame('active',$r->fresh()->status); $this->assertDatabaseCount('migration_records',0);
  $this->put(route('registry.update',$r),$data+['transfer_confirmed'=>'1'])->assertSessionHasNoErrors();
  $this->assertSame('migrated_out',$r->fresh()->status);
  $this->assertDatabaseHas('migration_records',['inhabitant_id'=>$r->id,'type'=>'out','destination'=>'Cebu','recorded_by'=>$u->id]);
  $this->assertDatabaseHas('registry_activities',['inhabitant_id'=>$r->id,'user_id'=>$u->id]);
  $this->get(route('barangay.registry.active'))->assertDontSee('Example');
  $this->get(route('barangay.registry.moved-out'))->assertSee('Example')->assertSee('Cebu');
  $this->get(route('dashboard.barangay'))->assertOk()->assertSee('Transferred resident to Cebu');
  $other = User::factory()->create(['role'=>'barangay','barangay_id'=>Barangay::where('name','San Isidro')->firstOrFail()->id]);
  $this->actingAs($other)->get(route('dashboard.barangay'))->assertDontSee('Transferred resident to Cebu');
  $this->put(route('registry.update',$r),$data+['transfer_confirmed'=>'1'])->assertForbidden();
 }
 public function test_remark_transfer_is_detected_but_negated_transfer_remains_active(): void {
  $b=Barangay::where('name','San Roque')->firstOrFail(); $u=User::factory()->create(['role'=>'barangay','barangay_id'=>$b->id]);
  $h=Household::create(['barangay_id'=>$b->id,'household_number'=>'1']);
  $r=Inhabitant::create(['barangay_id'=>$b->id,'household_id'=>$h->id,'first_name'=>'Person','last_name'=>'Example','sex'=>'Male','status'=>'active']);
  $d=['barangay_id'=>$b->id,'household_number'=>'1','first_name'=>'Person','last_name'=>'Example','sex'=>'Male','status'=>'active'];
  $this->actingAs($u)->put(route('registry.update',$r),$d+['remarks'=>'NOT TRANSFERRED TO CEBU'])->assertSessionHasNoErrors();
  $this->assertSame('active',$r->fresh()->status);
  $this->put(route('registry.update',$r),$d+['remarks'=>'TRANSFERED TO CEBU'])->assertSessionHasErrors('transfer_confirmed');
  $this->put(route('registry.update',$r),$d+['remarks'=>'TRANSFERED TO CEBU','transfer_confirmed'=>'1'])->assertSessionHasNoErrors();
  $this->assertSame('migrated_out',$r->fresh()->status);
 }
 public function test_residence_list_offers_transfer_and_moves_only_after_confirmation(): void {
  $b=Barangay::where('name','San Roque')->firstOrFail(); $u=User::factory()->create(['role'=>'barangay','barangay_id'=>$b->id]);
  $h=Household::create(['barangay_id'=>$b->id,'household_number'=>'1']);
  $r=Inhabitant::create(['barangay_id'=>$b->id,'household_id'=>$h->id,'first_name'=>'Person','last_name'=>'Example','sex'=>'Male','status'=>'active','residence_status'=>'living_elsewhere']);
  $this->actingAs($u)->get(route('barangay.residence.index',['scope'=>'living_elsewhere']))->assertOk()->assertSee('Transferred to...')->assertSee('data-residence-update',false);
  $d=['residence_status'=>'transferred','transfer_destination'=>'Cebu'];
  $this->put(route('barangay.residence.update',$r),$d)->assertSessionHasErrors('transfer_confirmed');
  $this->assertSame('active',$r->fresh()->status);
  $this->put(route('barangay.residence.update',$r),$d+['transfer_confirmed'=>'1'])->assertSessionHasNoErrors();
  $this->assertSame('migrated_out',$r->fresh()->status);
  $this->assertDatabaseHas('migration_records',['inhabitant_id'=>$r->id,'destination'=>'Cebu']);
  $this->assertDatabaseHas('registry_activities',['inhabitant_id'=>$r->id]);
  $this->put(route('barangay.residence.update',$r),$d+['transfer_confirmed'=>'1'])->assertStatus(422);
  $this->assertDatabaseCount('migration_records',1);
 }
}
