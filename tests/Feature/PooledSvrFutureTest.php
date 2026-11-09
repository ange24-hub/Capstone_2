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






}
