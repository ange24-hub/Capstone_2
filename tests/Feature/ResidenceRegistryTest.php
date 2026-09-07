<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidenceRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_biasong_is_excluded_from_residence_update(): void
    {
        $barangay = Barangay::where('name', 'Biasong')->firstOrFail();
        $staff = User::factory()->create(['role'=>User::ROLE_BARANGAY,'barangay_id'=>$barangay->id]);
        $resident = $this->resident($barangay, 'BiasongPerson', Inhabitant::RESIDENCE_UNCONFIRMED);
        $this->actingAs($staff)->get(route('barangay.registry.active'))->assertOk()
            ->assertSee('BiasongPerson')->assertDontSee('Living in Barangay')->assertDontSee('Current residence');
        $this->get(route('dashboard.barangay'))->assertOk()->assertDontSee('Living in Barangay')->assertDontSee('Living in barangay');
        $this->get(route('barangay.residence.index'))->assertForbidden();
        $this->get(route('barangay.residence.download'))->assertForbidden();
        $this->put(route('barangay.residence.update', $resident), ['residence_status'=>'living_here'])->assertForbidden();
        $plan = \App\Support\SourceResidenceSync::plan($barangay, 'missing.xlsx');
        $this->assertSame([], $plan['updates']);
        $result = \App\Support\SourceResidenceSync::apply($barangay, 'missing.xlsx');
        $this->assertSame(0, $result['updated']);
        $this->assertSame(Inhabitant::RESIDENCE_UNCONFIRMED, $resident->fresh()->residence_status);
    }

    private function resident(Barangay $barangay, string $name, string $residence, string $status = Inhabitant::STATUS_ACTIVE): Inhabitant
    {
        $household = Household::firstOrCreate(['barangay_id'=>$barangay->id,'household_number'=>'1']);
        return Inhabitant::create(['barangay_id'=>$barangay->id,'household_id'=>$household->id,
            'first_name'=>$name,'last_name'=>'Example','sex'=>'Female','status'=>$status,'residence_status'=>$residence]);
    }

    public function test_consolidated_keeps_green_residents_and_local_file_excludes_them(): void
    {
        $barangay = Barangay::where('name', 'Looc')->firstOrFail();
        $staff = User::factory()->create(['role'=>User::ROLE_BARANGAY,'barangay_id'=>$barangay->id]);
        $this->resident($barangay, 'HerePerson', Inhabitant::RESIDENCE_HERE);
        $away = $this->resident($barangay, 'ElsewherePerson', Inhabitant::RESIDENCE_ELSEWHERE);
        $this->resident($barangay, 'UnknownPerson', Inhabitant::RESIDENCE_UNCONFIRMED);
        $this->resident($barangay, 'MovedPerson', Inhabitant::RESIDENCE_HERE, Inhabitant::STATUS_MIGRATED_OUT);
        $other = Barangay::where('name', 'Hugpa')->firstOrFail();
        $this->resident($other, 'OtherBarangayPerson', Inhabitant::RESIDENCE_HERE);
        $this->actingAs($staff)->get(route('barangay.registry.active'))->assertOk()
            ->assertSee('HerePerson')->assertSee('ElsewherePerson')->assertSee('UnknownPerson')
            ->assertSee('residence-elsewhere')->assertDontSee('MovedPerson')->assertDontSee('OtherBarangayPerson');
        $this->get(route('barangay.residence.index'))->assertOk()
            ->assertViewHas('residents', fn ($rows) => $rows->total() === 1)
            ->assertSee('HerePerson')->assertDontSee('ElsewherePerson')->assertDontSee('UnknownPerson');
        $csv = $this->get(route('barangay.residence.download', ['barangay_id'=>$other->id]))->assertOk()->streamedContent();
        $this->assertStringContainsString('HerePerson', $csv);
        foreach (['ElsewherePerson','UnknownPerson','MovedPerson','OtherBarangayPerson'] as $excluded) $this->assertStringNotContainsString($excluded, $csv);
        $registered = $this->get(route('barangay.residence.download', ['scope'=>'registered']))->assertOk()->streamedContent();
        $this->assertStringContainsString('ElsewherePerson', $registered);
        $this->assertStringContainsString('UnknownPerson', $registered);
        $this->assertStringNotContainsString('MovedPerson', $registered);
        $this->put(route('barangay.residence.update', $away), ['residence_status'=>'living_here'])->assertSessionHasNoErrors();
        $this->assertSame('Confirmed by barangay staff', $away->fresh()->residence_source);
        $this->get(route('barangay.residence.index'))->assertViewHas('residents', fn ($rows) => $rows->total() === 2);
    }

    public function test_residence_changes_and_exports_are_scoped_to_the_barangay(): void
    {
        $looc = Barangay::where('name', 'Looc')->firstOrFail();
        $hugpa = Barangay::where('name', 'Hugpa')->firstOrFail();
        $staff = User::factory()->create(['role'=>User::ROLE_BARANGAY,'barangay_id'=>$looc->id]);
        $other = $this->resident($hugpa, 'OtherPerson', Inhabitant::RESIDENCE_ELSEWHERE);
        $this->actingAs($staff)->put(route('barangay.residence.update', $other), ['residence_status'=>'living_here'])->assertForbidden();
        $this->get(route('barangay.residence.download', ['scope'=>'invalid']))->assertStatus(422);
        $residentUser = User::factory()->create(['role'=>User::ROLE_RESIDENT,'barangay_id'=>$looc->id]);
        $this->actingAs($residentUser)->get(route('barangay.residence.index'))->assertForbidden();
        $this->get(route('barangay.residence.download'))->assertForbidden();
        $this->assertSame(Inhabitant::RESIDENCE_ELSEWHERE, $other->fresh()->residence_status);
    }
}
