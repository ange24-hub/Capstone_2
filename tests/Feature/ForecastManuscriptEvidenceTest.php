<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, MigrationRecord, PopulationReportSubmission, User};
use App\Services\{MigrationPredictionService, PopulationReportSubmissionSnapshot, RecordedMigrationHistory, SemestralPopulationProjection};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ForecastManuscriptEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reproducible_examples_for_the_manuscript(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 12:00','Asia/Manila'));
        $area = Barangay::where('name','Hugpa')->firstOrFail();
        $staff = User::factory()->create(['role'=>User::ROLE_BARANGAY,'barangay_id'=>$area->id]);
        $house = Household::create(['barangay_id'=>$area->id,'household_number'=>'EVIDENCE']);
        $service = app(MigrationPredictionService::class);
        foreach (['2026-08'=>2,'2026-09'=>4,'2026-10'=>8] as $month=>$count) {
            for ($i=0;$i<$count;$i++) {
                $person = Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$house->id,
                    'first_name'=>'Synthetic','last_name'=>'Example','sex'=>'Female','status'=>'active']);
                MigrationRecord::create(['barangay_id'=>$area->id,'inhabitant_id'=>$person->id,'type'=>'in','movement_date'=>$month.'-02']);
            }
            $state = $service->monthSnapshot($area->id,'in',$month);
            $service->confirmActualMonth($area->id,'in',$month,$staff->id,$state['source_hash']);
        }
        $forecast = $service->forecastNextMonth($area->id,'in');
        $this->assertSame(5.6667,$forecast['predicted_value']);
        $this->assertSame('2026-12',$forecast['log_month']);
        $this->assertSame('2026-10',$forecast['data_through']);
        $this->assertNull($service->calculateForecastAccuracy($area->id,'in')['mape']);
        $person = Inhabitant::firstOrFail();
        MigrationRecord::create(['barangay_id'=>$area->id,'inhabitant_id'=>$person->id,'type'=>'in','movement_date'=>'2026-08-02']);
        MigrationRecord::create(['barangay_id'=>$area->id,'inhabitant_id'=>$person->id,'type'=>'out','movement_date'=>'2026-10-04']);
        $trends = app(RecordedMigrationHistory::class)->build($area->id,2026);
        $this->assertSame([14,1,1],[$trends['in'],$trends['out'],$trends['duplicates']]);
        $this->assertFalse($trends['months'][6]['in_known']);
        foreach ([[2025,1,600],[2025,2,620],[2026,1,640]] as [$year,$semester,$population]) {
            $data = ['kind'=>'historical_population_photo','population'=>$population,'age_total_sum'=>$population];
            $hash = app(PopulationReportSubmissionSnapshot::class)->hash($data);
            PopulationReportSubmission::create(['barangay_id'=>$area->id,'barangay_name'=>$area->name,
                'submitted_by_name'=>'Synthetic test source','report_year'=>$year,'semester'=>$semester,'total_records'=>$population,
                'snapshot'=>$data,'snapshot_hash'=>$hash,'content_hash'=>$hash,'submission_key'=>(string)Str::uuid(),'submitted_at'=>now()]);
        }
        $before = PopulationReportSubmission::orderBy('id')->get()->map->getAttributes()->all();
        $projection = app(SemestralPopulationProjection::class)->forBarangay($area->id);
        $this->assertEqualsWithDelta(626.6666667,$projection['steps'][0]['estimate'],.000001);
        $this->assertEqualsWithDelta(630,$projection['estimate'],.000001);
        $this->assertSame('2027 · 1st semester',$projection['target']);
        $this->assertNull($projection['mape']);
        $this->assertSame($before,PopulationReportSubmission::orderBy('id')->get()->map->getAttributes()->all());
        $this->actingAs($staff)->get(route('prediction.migration'))->assertOk()->assertSee('2-month lead');
    }
}
