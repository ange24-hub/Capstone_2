<?php

namespace Tests\Feature;

use App\Models\{Barangay, BarangayRbiUpdate, Household, Inhabitant, MigrationRecord, NewInhabitant, User};
use App\Services\PooledMigrationReconstruction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PooledMigrationReconstructionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_existing_classified_observed_months_enter_the_sample_and_gaps_are_not_zeros(): void
    {
        $area = Barangay::where('name','Cabascan')->firstOrFail();
        $owner = User::factory()->create(['role'=>'barangay','barangay_id'=>$area->id]);
        $rows = [
            '2020-11' => ['first_name'=>'BirthFixture','last_name'=>'Private','birth_date'=>'2020-11-04','entry_type'=>'birth'],
            '2020-12' => ['first_name'=>'ArrivalFixture','last_name'=>'Private','birth_date'=>'2000-01-02','entry_type'=>'in_migration','arrival_month'=>'2020-12','previous_residence_unknown'=>'1'],
            '2021-02' => ['first_name'=>'UnclassifiedFixture','last_name'=>'Private','birth_date'=>'1990-01-01','remarks'=>'FROM TINAGO'],
        ];
        foreach ($rows as $month=>$row) BarangayRbiUpdate::create(['barangay_user_id'=>$owner->id,'barangay_name'=>$area->name,
            'reporting_month'=>$month.'-01','status'=>'draft','rows'=>[$row]]);
        $before = BarangayRbiUpdate::orderBy('id')->get()->map->getRawOriginal()->all();
        $result = app(PooledMigrationReconstruction::class)->build();
        $source = $result['aggregate'];
        $this->assertSame(['2020-11','2020-12'], array_column($source['experimental_arrival_samples'],'month'));
        $this->assertSame([0,1], array_column($source['experimental_arrival_samples'],'count'));
        $this->assertSame(1,$source['arrival_entries_with_unknown_origin']);
        $this->assertSame(1,$source['unclassified_dated_entries']);
        $this->assertSame([], $source['actual_municipal_in_training_rows']);
        $this->assertSame([], $source['actual_municipal_out_training_rows']);
        foreach ($source['experimental_arrival_samples'] as $row) $this->assertFalse($row['complete_physical_migration_total']);
        $this->assertSame($before, BarangayRbiUpdate::orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertSame(0, MigrationRecord::count());
        $this->assertStringNotContainsString('ArrivalFixture', json_encode($source));
    }

    public function test_undated_departures_and_new_records_get_review_items_without_invented_dates(): void
    {
        $area=Barangay::firstOrFail();
        $home=Household::create(['barangay_id'=>$area->id,'household_number'=>'REVIEW-REAL']);
        $person=Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$home->id,'first_name'=>'AwayFixture','last_name'=>'Private',
            'sex'=>'Male','status'=>'active','residence_status'=>'living_elsewhere','remarks'=>'AT CEBU']);
        NewInhabitant::create(['barangay_id'=>$area->id,'household_number'=>'REVIEW-REAL','first_name'=>'UndatedFixture','last_name'=>'Private','sex'=>'Male','month_submitted'=>'JUNE','source_position'=>4]);
        $before=$person->fresh()->getRawOriginal();
        $result=app(PooledMigrationReconstruction::class)->build();
        $this->assertSame(1,$result['aggregate']['undated_departure_profiles']);
        $kinds=array_column($result['private_review_queue'],'kind');
        $this->assertContains('undated_departure',$kinds);
        $this->assertContains('undated_new_history',$kinds);
        foreach($result['private_review_queue'] as $row) $this->assertNull($row['confirmed_movement_month']);
        $this->assertSame($before,$person->fresh()->getRawOriginal());
        $this->assertSame(0,MigrationRecord::count());
        $this->assertFalse($result['aggregate']['source_records_changed']);
    }
}
