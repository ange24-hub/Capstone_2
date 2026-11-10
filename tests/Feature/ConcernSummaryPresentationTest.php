<?php

namespace Tests\Feature;

use App\Models\{Barangay, ResidentConcern, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcernSummaryPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_keeps_open_resolved_and_closed_counts_separate_without_exposing_or_changing_records(): void
    {
        $area = Barangay::where('name','Looc')->firstOrFail();
        $other = Barangay::where('name','Maslog')->firstOrFail();
        $resident = User::factory()->create(['name'=>'Private Concern Resident','barangay_id'=>$area->id]);
        $otherResident = User::factory()->create(['barangay_id'=>$other->id]);
        foreach (['submitted','under_review','in_progress','resolved','closed'] as $index=>$status) {
            ResidentConcern::create(['reference'=>'PRIVATE-REFERENCE-'.$index,'resident_id'=>$resident->id,
                'barangay_id'=>$area->id,'title'=>'PRIVATE CONCERN TITLE','description'=>'PRIVATE CONCERN DESCRIPTION',
                'category'=>'roads','status'=>$status]);
        }
        ResidentConcern::create(['reference'=>'PRIVATE-OTHER','resident_id'=>$otherResident->id,
            'barangay_id'=>$other->id,'title'=>'PRIVATE OTHER TITLE','description'=>'PRIVATE OTHER DESCRIPTION',
            'category'=>'water','status'=>'in_progress']);
        $before = ResidentConcern::orderBy('id')->get()->map->getAttributes()->all();
        $admin = User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]);
        $response = $this->actingAs($admin)->get(route('concerns.summary'))->assertOk();
        foreach (['total'=>6,'open'=>4,'resolved'=>1,'closed'=>1] as $key=>$value) {
            $response->assertSee('data-concern-total="'.$key.'">'.$value,false);
        }
        $response->assertDontSee($resident->name)->assertDontSee('PRIVATE CONCERN')->assertDontSee('PRIVATE-REFERENCE')
            ->assertSee('Closed is separate from resolved.')->assertSee('data-concern-search',false);
        $scoped = $this->get(route('concerns.summary',['barangay_id'=>$area->id,'days'=>30]))->assertOk();
        foreach (['total'=>5,'open'=>3,'resolved'=>1,'closed'=>1] as $key=>$value) {
            $scoped->assertSee('data-concern-total="'.$key.'">'.$value,false);
        }
        $this->assertSame($before,ResidentConcern::orderBy('id')->get()->map->getAttributes()->all());
    }
}
