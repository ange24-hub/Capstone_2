<?php

namespace Tests\Feature;

use App\Models\{Barangay, Inhabitant, MigrationRecord, User};
use App\Services\ExperimentalPooledSvrForecast as Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PooledSvrFutureTest extends TestCase
{
    use RefreshDatabase;
    private string $bytes;
    private array $artifact;

    protected function setUp(): void
    {
        parent::setUp();Storage::fake('local');
        config(['services.experimental_pooled_svr.bundled_fallback'=>false]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-08 12:00:00','Asia/Manila'));
        $this->bytes=file_get_contents(base_path('tests/Fixtures/pooled-svr-inference.json'));
        $this->artifact=json_decode($this->bytes,true,flags:JSON_THROW_ON_ERROR);
        Storage::disk('local')->put(Model::PATH,$this->bytes);
        $sample=['sample_barangays'=>['Cabascan','Cawayan'],'training_rows'=>7,'test_rows'=>5,'test_months'=>['2021-05','2021-06','2021-09'],
            'mae'=>1.9333,'rmse'=>2.5933,'baseline'=>['mae'=>3.4,'rmse'=>4.0249],
            'parameters'=>$this->artifact['training']['parameters'],'test_predictions'=>[]];
        $source=['configured_barangays'=>29,'generated_at'=>'2026-11-08T00:00:00Z','existing_classified_arrivals'=>22,
            'arrival_entries_with_unknown_origin'=>22,'unclassified_dated_entries'=>231,'undated_departure_profiles'=>1280,
            'non_demo_dated_events'=>[['type'=>'out']],'departure_verified_periods'=>0,'review_queue_items'=>1599,'coverage'=>[]];
        Storage::disk('local')->put('migration-reconstruction/published-pooled-evidence.json',json_encode([
            'run'=>$this->artifact['run'],'source'=>$source,
            'result'=>['source_sha256'=>$this->artifact['source_sha256'],'recorded_arrival_sample_svr'=>$sample],
            'workbooks'=>['workbooks_reviewed'=>29,'sheets_reviewed'=>211]]));
    }

    private function inputs(): array
    {
        return ['target_month'=>'2026-12','inference_artifact_sha256'=>hash('sha256',$this->bytes)];
    }

    public function test_admin_can_view_future_ml_estimates_with_scope_and_extrapolation_disclosures(): void
    {
        $this->actingAs(User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]));
        $area=Barangay::where('name','Cabascan')->firstOrFail();
        $this->get(route('municipal.prediction-forecast',['barangay_id'=>$area->id]))->assertOk()
            ->assertSee('Cabascan · Experimental SVR in-migration')->assertSee('December 2026')
            ->assertSee('1.54')->assertSee('highly uncertain extrapolation')->assertSee('intercept')
            ->assertSee('Future MAE/RMSE: not yet measurable')
            ->assertViewHas('experimentalArrivalForecast',fn($f)=>$f['ready']&&$f['municipal_total']===null);
        Storage::disk('local')->assertDirectoryEmpty('migration-reconstruction/future-forecasts');
    }

    public function test_saved_forecast_is_immutable_after_model_and_calendar_change_and_changes_no_source_rows(): void
    {
        $admin=User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($admin);
        $before=[Inhabitant::count(),MigrationRecord::count()];
        $response=$this->post(route('municipal.prediction-pooled.future.store'),$this->inputs())->assertRedirect();
        $url=$response->headers->get('Location');$id=basename($url);
        $this->get($url)->assertOk()->assertSee('Saved future estimate')->assertSee('Download future forecast evidence');
        $first=Storage::disk('local')->get('migration-reconstruction/future-forecasts/'.$id.'.json');
        $changed=$this->artifact;$changed['svr']['intercept']+=10;
        Storage::disk('local')->put(Model::PATH,json_encode($changed));
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-01-10 12:00:00','Asia/Manila'));
        $this->get($url)->assertOk()->assertSee('December 2026')->assertViewHas('future',fn($f)=>abs($f['covered_sample_sum']-3.070265962558345)<1e-10);
        $download=$this->get(route('municipal.prediction-pooled.future.evidence',$id))->assertOk();
        $data=json_decode($download->streamedContent(),true,flags:JSON_THROW_ON_ERROR);
        $this->assertSame('2026-12',$data['forecast']['target_month']);
        $this->assertSame(hash('sha256',$this->bytes),$data['forecast']['inference_artifact_sha256']);
        $this->assertSame($first,Storage::disk('local')->get('migration-reconstruction/future-forecasts/'.$id.'.json'));
        $this->assertSame($before,[Inhabitant::count(),MigrationRecord::count()]);
        $this->assertFalse($data['forecast']['source_records_changed']);
        $this->assertSame($admin->id,$data['saved_by']['id']);
    }

    public function test_invalid_targets_changed_models_and_missing_artifacts_cannot_save_estimates(): void
    {
        $this->actingAs(User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]));
        foreach(['2026-11','2027-03','2026-13']as$month)$this->postJson(route('municipal.prediction-pooled.future.store'),array_merge($this->inputs(),['target_month'=>$month]))->assertUnprocessable()->assertJsonValidationErrors('target_month');
        $this->postJson(route('municipal.prediction-pooled.future.store'),array_merge($this->inputs(),['inference_artifact_sha256'=>str_repeat('0',64)]))->assertStatus(409);
        Storage::disk('local')->delete(Model::PATH);
        $this->postJson(route('municipal.prediction-pooled.future.store'),$this->inputs())->assertUnprocessable();
        Storage::disk('local')->assertDirectoryEmpty('migration-reconstruction/future-forecasts');
    }

    public function test_only_approved_municipal_admins_can_create_read_or_download_future_snapshots(): void
    {
        $id='00000000-0000-0000-0000-000000000000';
        $urls=[route('municipal.prediction-pooled.future.show',$id),route('municipal.prediction-pooled.future.evidence',$id)];
        foreach($urls as$url)$this->get($url)->assertRedirect(route('login'));
        $this->post(route('municipal.prediction-pooled.future.store'),$this->inputs())->assertRedirect(route('login'));
        foreach([['role'=>'barangay','barangay_id'=>Barangay::firstOrFail()->id],['role'=>'resident'],
            ['role'=>User::ROLE_MUNICIPAL_LGU,'approval_status'=>'pending']]as$attributes){
            $this->actingAs(User::factory()->create($attributes));
            foreach($urls as$url)$this->get($url)->assertForbidden();
            $this->post(route('municipal.prediction-pooled.future.store'),$this->inputs())->assertForbidden();
        }
        Storage::disk('local')->assertDirectoryEmpty('migration-reconstruction/future-forecasts');
    }

    public function test_cabascan_filter_displays_only_its_svr_estimate_in_the_municipal_forecast(): void
    {
        $area=Barangay::where('name','Cabascan')->firstOrFail();
        $this->actingAs(User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]));
        $params=['year'=>2026,'barangay_id'=>$area->id];
        $this->get(route('municipal.prediction-forecast',$params))->assertOk()
            ->assertSee('Cabascan · Experimental SVR in-migration')->assertSee('1.54')
            ->assertSee('data-prediction-target="2026-12"',false)
            ->assertSee('Only Cabascan is shown')
            ->assertViewHas('experimentalArrivalForecast',fn($f)=>$f['ready']&&count($f['rows'])===1&&$f['rows'][0]['barangay_id']===$area->id)
            ->assertViewHas('predictedInMigration',fn($p)=>$p===null);
        $this->get(route('migration.dashboard',$params+['section'=>'forecast']))->assertOk()
            ->assertViewHas('experimentalArrivalForecast',fn($f)=>$f['ready']&&count($f['rows'])===1);
        $this->get(route('municipal.prediction-forecast',['year'=>2025,'barangay_id'=>$area->id]))->assertOk()
            ->assertViewHas('experimentalArrivalForecast',fn($f)=>!$f['ready'])
            ->assertDontSee('data-prediction-target="2026-12"',false);
    }

    public function test_legacy_preview_redirect_and_saved_evidence_keep_the_cabascan_only_scope(): void
    {
        $area=Barangay::where('name','Cabascan')->firstOrFail();
        $this->actingAs(User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]));
        $this->get(route('municipal.prediction-pooled',['barangay_id'=>$area->id]))
            ->assertRedirect(route('municipal.prediction-forecast',['barangay_id'=>$area->id]));
        $response=$this->post(route('municipal.prediction-pooled.future.store'),$this->inputs()+['barangay_id'=>$area->id])->assertRedirect();
        $id=basename($response->headers->get('Location'));
        $download=$this->get(route('municipal.prediction-pooled.future.evidence',$id))->assertOk();
        $forecast=json_decode($download->streamedContent(),true,flags:JSON_THROW_ON_ERROR)['forecast'];
        $this->assertSame($area->id,$forecast['selected_barangay_id']);
        $this->assertCount(1,$forecast['rows']);
        $this->assertSame('Cabascan',$forecast['rows'][0]['barangay']);
        $this->assertEqualsWithDelta(1.5351329812791725,$forecast['covered_sample_sum'],1e-10);
    }

    public function test_unrepresented_barangay_never_borrows_another_barangays_prediction(): void
    {
        $area=Barangay::where('name','Tinago')->firstOrFail();
        $this->actingAs(User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]));
        $this->get(route('municipal.prediction-pooled',['barangay_id'=>$area->id]))
            ->assertRedirect(route('municipal.prediction-forecast',['barangay_id'=>$area->id]));
        $this->get(route('municipal.prediction-forecast',['year'=>2026,'barangay_id'=>$area->id]))->assertOk()
            ->assertViewHas('experimentalArrivalForecast',fn($f)=>!$f['ready'])
            ->assertDontSee('data-prediction-target="2026-12"',false);
        $this->postJson(route('municipal.prediction-pooled.future.store'),$this->inputs()+['barangay_id'=>$area->id])->assertUnprocessable();
        $this->getJson(route('municipal.prediction-pooled',['barangay_id'=>99999]))->assertUnprocessable()->assertJsonValidationErrors('barangay_id');
    }
}
