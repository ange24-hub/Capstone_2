<?php
namespace Tests\Feature;
use App\Models\{Barangay, User, NewInhabitant, Inhabitant};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AddSavedMemberTest extends TestCase {
 use RefreshDatabase;
 public function test_member_is_added_once_to_own_household_and_other_barangays_are_blocked(): void {
  $b=Barangay::where('name','San Roque')->firstOrFail();
  $u=User::factory()->create(['role'=>'barangay','barangay_id'=>$b->id]);
  $r=NewInhabitant::create(['barangay_id'=>$b->id,'household_number'=>'12','first_name'=>'Example','last_name'=>'Person','sex'=>'Male']);
  $this->actingAs($u)->get(route('barangay.rbi-updates.index'))->assertOk()->assertSee(route('registry.new-inhabitants.add-to-active',$r));
  $this->post(route('registry.new-inhabitants.add-to-active',$r))->assertSessionHasNoErrors();
  $active=Inhabitant::findOrFail($r->fresh()->active_inhabitant_id);
  $this->assertSame('12',$active->household->household_number);
  $this->assertSame('active',$active->status);
  $this->post(route('registry.new-inhabitants.add-to-active',$r))->assertSessionHasNoErrors();
  $this->assertDatabaseCount('inhabitants',1);
  $copy=$r->replicate(['active_inhabitant_id','added_to_active_at']);$copy->save();
  $this->post(route('registry.new-inhabitants.add-to-active',$copy))->assertSessionHasNoErrors();
  $this->assertSame($active->id,$copy->fresh()->active_inhabitant_id);
  $this->assertDatabaseCount('inhabitants',1);
  $other=User::factory()->create(['role'=>'barangay','barangay_id'=>Barangay::where('name','San Isidro')->firstOrFail()->id]);
  $this->actingAs($other)->post(route('registry.new-inhabitants.add-to-active',$r))->assertForbidden();
 }
}
