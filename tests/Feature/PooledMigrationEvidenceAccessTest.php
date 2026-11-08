<?php

namespace Tests\Feature;

use App\Models\{Barangay, MigrationRecord, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PooledMigrationEvidenceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $sample=['sample_barangays'=>['Fixture A','Fixture B'],'training_rows'=>7,'test_rows'=>5,'test_months'=>['2021-05','2021-06','2021-09'],
            'mae'=>2.0,'rmse'=>3.0,'baseline'=>['mae'=>3.0,'rmse'=>4.0],
            'parameters'=>['svr__C'=>10,'svr__epsilon'=>1,'svr__gamma'=>0.1],'test_predictions'=>[]];
        $source=['configured_barangays'=>29,'generated_at'=>'2026-11-08T00:00:00Z','existing_classified_arrivals'=>22,
            'arrival_entries_with_unknown_origin'=>22,'unclassified_dated_entries'=>231,'undated_departure_profiles'=>1280,
            'non_demo_dated_events'=>[['type'=>'out']],'departure_verified_periods'=>0,'review_queue_items'=>1599,'coverage'=>[]];
        Storage::disk('local')->put('migration-reconstruction/published-pooled-evidence.json',json_encode([
            'run'=>'fixture-review','source'=>$source,'result'=>['recorded_arrival_sample_svr'=>$sample],
            'workbooks'=>['workbooks_reviewed'=>29,'sheets_reviewed'=>211]]));
        Storage::disk('local')->put('migration-reconstruction/fixture-review/review-queue.json',json_encode([['source_note'=>'PRIVATE SOURCE NOTE']]));
    }

    public function test_admin_downloads_model_evidence_and_defense_while_source_review_queue_is_preserved(): void
    {
        $admin=User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($admin)->get(route('municipal.prediction-pooled'))->assertRedirect(route('municipal.prediction-forecast'));
        $download=$this->get(route('municipal.prediction-pooled.evidence'))->assertOk();
        $this->assertStringNotContainsString('PRIVATE SOURCE NOTE',$download->streamedContent());
        Storage::disk('local')->assertExists('migration-reconstruction/fixture-review/review-queue.json');
        $this->get(route('municipal.prediction-pooled.defense'))->assertOk()->assertDownload('pooled-svr-defense.txt');
        $this->assertSame(0,MigrationRecord::count());
    }

    public function test_all_evidence_and_private_download_routes_require_approved_municipal_role(): void
    {
        $urls=array_map(fn($name)=>route($name),['municipal.prediction-pooled','municipal.prediction-pooled.evidence',
            'municipal.prediction-pooled.defense']);
        foreach($urls as$url)$this->get($url)->assertRedirect(route('login'));
        foreach([['role'=>'barangay','barangay_id'=>Barangay::firstOrFail()->id],['role'=>'resident'],
            ['role'=>User::ROLE_MUNICIPAL_LGU,'approval_status'=>'pending']]as$attributes){
            $this->actingAs(User::factory()->create($attributes));
            foreach($urls as$url)$this->get($url)->assertForbidden();
        }
    }
}
