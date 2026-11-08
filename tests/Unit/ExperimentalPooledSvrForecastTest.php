<?php

namespace Tests\Unit;

use App\Services\ExperimentalPooledSvrForecast as Model;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExperimentalPooledSvrForecastTest extends TestCase
{
    private array $artifact;
    protected function setUp(): void
    {
        parent::setUp();Storage::fake('local');
        config(['services.experimental_pooled_svr.bundled_fallback'=>false]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-08 12:00:00','Asia/Manila'));
        $bytes=file_get_contents(base_path('tests/Fixtures/pooled-svr-inference.json'));
        $this->artifact=json_decode($bytes,true,flags:JSON_THROW_ON_ERROR);
        Storage::disk('local')->put(Model::PATH,$bytes);
    }

    private function evidence(): array
    {
        return ['run'=>$this->artifact['run'],'result'=>['source_sha256'=>$this->artifact['source_sha256']]];
    }

    public function test_native_inference_matches_sklearn_for_near_history_and_distant_future(): void
    {
        $service=new Model;
        foreach($this->artifact['parity_cases']as$case){
            $p=$service->predictArea($case['month'],$case['area_id']);
            $this->assertEqualsWithDelta($case['raw_prediction'],$p['raw_prediction'],1e-10);
            $this->assertEqualsWithDelta(max(0,$case['raw_prediction']),$p['prediction'],1e-10);
        }
    }

    public function test_future_estimates_preserve_old_observations_and_do_not_become_municipal_totals(): void
    {
        $future=(new Model)->build($this->evidence());
        $this->assertTrue($future['ready']);$this->assertSame('2026-12',$future['target_month']);
        $this->assertSame([66,63],array_column($future['rows'],'gap_months'));
        $this->assertSame([1,2],array_column($future['rows'],'last_observed_count'));
        $this->assertSame(['2021-06','2021-09'],array_column($future['rows'],'last_observed_month'));
        $this->assertEqualsWithDelta(3.070265962558345,$future['covered_sample_sum'],1e-10);
        $this->assertNull($future['municipal_total']);$this->assertNull($future['out_migration_prediction']);
        $this->assertNull($future['future_accuracy']['mae']);$this->assertNull($future['future_accuracy']['rmse']);
        foreach($future['rows']as$row){$this->assertTrue($row['outside_fitted_gap_range']);$this->assertTrue($row['intercept_dominated']);}
        $this->assertSame(12,$future['training']['rows']);
        $this->assertFalse($future['historical_evaluation']['applies_to_future_forecast_accuracy']);
        $this->assertCount(2,$future['rows']);
    }

    public function test_invalid_targets_and_unrepresented_barangays_cannot_produce_estimates(): void
    {
        foreach(['2026-11','2027-03','2026-13','not-a-month']as$month){
            try{(new Model)->build($this->evidence(),$month);$this->fail('Accepted invalid target');}
            catch(\InvalidArgumentException){$this->addToAssertionCount(1);}
        }
        $this->expectException(\InvalidArgumentException::class);
        (new Model)->predictArea('2026-12',9999);
    }

    public function test_missing_corrupt_or_mismatched_models_fail_closed(): void
    {
        $wrong=$this->evidence();$wrong['run']='different-run';
        $this->assertFalse((new Model)->build($wrong)['ready']);
        Storage::disk('local')->put(Model::PATH,'{broken-json');
        $this->assertFalse((new Model)->build($this->evidence())['ready']);
        $data=$this->artifact;$data['svr']['gamma']=0;
        Storage::disk('local')->put(Model::PATH,json_encode($data));
        $this->assertFalse((new Model)->build($this->evidence())['ready']);
        Storage::disk('local')->delete(Model::PATH);
        $this->assertFalse((new Model)->build($this->evidence())['ready']);
    }

    public function test_production_bundle_works_without_copying_private_local_storage(): void
    {
        config(['services.experimental_pooled_svr.bundled_fallback'=>true]);
        Storage::disk('local')->delete(Model::PATH);
        $forecast=(new Model)->preview(5);
        $this->assertTrue($forecast['ready']);
        $this->assertCount(1,$forecast['rows']);
        $this->assertSame('Cabascan',$forecast['rows'][0]['barangay']);
        $this->assertEqualsWithDelta(1.5351329812791725,$forecast['rows'][0]['prediction'],1e-10);
        Storage::disk('local')->assertDirectoryEmpty('migration-reconstruction');
        $bundle=file_get_contents(resource_path('forecast-models/pooled-recorded-arrivals-evidence.json'));
        foreach(['first_name','last_name','birth_date','inhabitant_id','private_review_queue']as$field)$this->assertStringNotContainsString('"'.$field.'"',$bundle);
    }

    public function test_invalid_private_host_export_is_not_silently_replaced_by_the_release_bundle(): void
    {
        config(['services.experimental_pooled_svr.bundled_fallback'=>true]);
        Storage::disk('local')->put(Model::PATH,'invalid-json');
        $this->assertFalse((new Model)->preview(5)['ready']);
    }
}
