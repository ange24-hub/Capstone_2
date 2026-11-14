<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, PopulationReportSubmission, User};
use App\Services\HistoricalPopulationReportImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PopulationReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role'=>User::ROLE_BARANGAY,
            'barangay_id'=>Barangay::where('name','Carnaga')->firstOrFail()->id]);
    }

    private function resident(User $staff, Household $house, array $extra=[]): Inhabitant
    {
        return Inhabitant::create($extra + ['barangay_id'=>$staff->barangay_id,'household_id'=>$house->id,
            'first_name'=>'Fixture','last_name'=>'Resident','sex'=>'Male','family_number'=>'1',
            'birth_date'=>'2000-01-01','status'=>Inhabitant::STATUS_ACTIVE]);
    }

    private function archive(User $staff): PopulationReportSubmission
    {
        Storage::fake('local');
        $photo=UploadedFile::fake()->image('old-original.jpg',100,100);
        return app(HistoricalPopulationReportImport::class)->import($staff->barangay,$photo->getPathname(),
            ['year'=>2024,'semester'=>1,'population'=>459,'households'=>107,'families'=>112,
                'age_totals'=>[21,25,34,56,35,32,28,23,28,33,35,14,13,15,18,8,11]]);
    }

    public function test_new_period_uses_latest_consolidated_residents_and_households(): void
    {
        $this->travelTo(Carbon::parse('2027-03-01', 'Asia/Manila'));
        $staff=$this->staff();
        $house=Household::create(['barangay_id'=>$staff->barangay_id,'household_number'=>'1']);
        $this->resident($staff,$house);
        $this->resident($staff,$house,['sex'=>'Female','residence_status'=>Inhabitant::RESIDENCE_ELSEWHERE]);
        $this->resident($staff,$house,['remarks'=>\App\Services\MigrationForecastHistory::DEMO_MARKER]);
        foreach ([Inhabitant::STATUS_MIGRATED_OUT,Inhabitant::STATUS_INACTIVE] as $index=>$status) {
            $old=Household::create(['barangay_id'=>$staff->barangay_id,'household_number'=>(string)($index+2)]);
            $this->resident($staff,$old,['status'=>$status]);
        }
        Household::create(['barangay_id'=>$staff->barangay_id,'household_number'=>'4']);
        $archive=$this->archive($staff);
        $original=$archive->snapshot;
        $registry=$this->actingAs($staff)->get(route('barangay.registry.active'))->assertOk();
        $response=$this->get(route('reports.population',['report_year'=>2027,'semester'=>1]))->assertOk()
            ->assertSee('Latest Consolidated records')->assertSee('data-population-report-controls',false)
            ->assertDontSee('Download original photo');
        $report=$response->viewData('report');
        $this->assertSame($registry->viewData('inhabitants')->total(),$report['totalRecords']);
        $this->assertSame($registry->viewData('registryHouseholdCount'),$report['totalHouseholds']);
        $this->assertSame(2,$report['totalRecords']);
        $this->assertSame(2,$report['totalHouseholds']);
        $this->assertSame(1,$report['totalFamilies']);
        $this->assertSame(2027,$report['monitoring']['year']);
        $this->assertSame(1,$report['monitoring']['semester']);
        $this->resident($staff,$house);
        $updated=$this->get(route('reports.population',['report_year'=>2027,'semester'=>1]))->assertOk()->viewData('report');
        $this->assertSame(3,$updated['totalRecords']);
        $this->assertSame($original,$archive->fresh()->snapshot);
        $this->assertDatabaseCount('population_report_submissions',1);
        $this->get(route('reports.population.submissions.show',$archive))->assertOk()->assertSee('Original photographed RBI Form C')->assertSee('459');
        $this->get(route('reports.population.submissions.source',$archive))->assertOk()->assertHeader('Content-Type','image/jpeg');
    }

    public function test_period_exports_are_document_files_from_current_data_and_do_not_replace_archives(): void
    {
        $this->travelTo(Carbon::parse('2027-03-01', 'Asia/Manila'));
        $staff=$this->staff();$archive=$this->archive($staff);
        $original=$archive->snapshot;
        $house=Household::create(['barangay_id'=>$staff->barangay_id,'household_number'=>'1']);
        $this->resident($staff,$house);
        $this->actingAs($staff);
        $period=['report_year'=>2027,'semester'=>1];
        $this->get(route('reports.population.pdf',$period))->assertOk()->assertHeader('Content-Type','application/pdf');
        $response=$this->get(route('reports.population.excel',$period))->assertOk();
        $this->assertStringContainsString('2027-semester-1',$response->headers->get('Content-Disposition'));
        $path=tempnam(sys_get_temp_dir(),'current-report-');file_put_contents($path,$response->getContent());
        $zip=new \ZipArchive;$zip->open($path);
        try {
            $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('1st Semester of CY 2027',$sheet);
            $this->assertStringContainsString('Current registry as of',$sheet);
            $this->assertStringNotContainsString('459',$sheet);
        } finally {$zip->close();unlink($path);}
        $this->assertSame($original,$archive->fresh()->snapshot);
        $this->assertDatabaseCount('population_report_submissions',1);
    }

    public function test_saved_names_and_signatures_appear_in_new_document_without_breaking_submission_validation(): void
    {
        $staff=$this->staff();
        $house=Household::create(['barangay_id'=>$staff->barangay_id,'household_number'=>'1']);
        $this->resident($staff,$house);
        $image=imagecreatetruecolor(200,80);imagefill($image,0,0,imagecolorallocate($image,255,255,255));
        $ink=imagecolorallocate($image,10,30,50);imagesetthickness($image,3);
        imageline($image,15,65,90,15,$ink);imageline($image,90,15,170,65,$ink);
        ob_start();imagepng($image);$signature='data:image/png;base64,'.base64_encode(ob_get_clean());
        $this->travelTo(Carbon::parse('2026-11-13', 'Asia/Manila'));
        $preview=$this->actingAs($staff)->get(route('reports.population'))->assertOk();
        $this->post(route('reports.population.submissions.store'),['preview_token'=>$preview->viewData('submissionPreviewToken'),
            'prepared_name'=>'Remembered Secretary','submitted_name'=>'Remembered Captain',
            'prepared_signature_data'=>$signature,'submitted_signature_data'=>$signature])->assertSessionHasNoErrors()->assertRedirect();
        $first=PopulationReportSubmission::firstOrFail();
        $this->resident($staff,$house);
        $this->travelTo(Carbon::parse('2027-08-01', 'Asia/Manila'));
        $next=$this->get(route('reports.population',['report_year'=>2027,'semester'=>2]))->assertOk()
            ->assertSee('Remembered Secretary')->assertSee('Remembered Captain')->assertSee('Saved signatures included');
        $report=$next->viewData('report');
        $this->assertTrue($next->viewData('usingSavedSignatures'));
        $this->assertSame($first->snapshot['signatures'],$report['signatures']);
        $this->assertSame(2,$report['totalRecords']);
        $this->post(route('reports.population.submissions.store'),['preview_token'=>$next->viewData('submissionPreviewToken'),
            'signature_source_id'=>$first->id,'prepared_name'=>'Remembered Secretary','submitted_name'=>'Remembered Captain',
            'prepared_signature_reuse'=>1,'submitted_signature_reuse'=>1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('population_report_submissions',2);
        $other=User::factory()->create(['role'=>User::ROLE_BARANGAY,'barangay_id'=>$staff->barangay_id]);
        $this->travelTo(Carbon::parse('2028-03-01', 'Asia/Manila'));
        $this->actingAs($other)->get(route('reports.population'))->assertOk()->assertDontSee('Remembered Secretary')->assertDontSee('Saved signatures included');
    }

    public function test_only_the_exact_saved_year_and_semester_opens_an_archive(): void
    {
        $this->travelTo(Carbon::parse('2026-11-13', 'Asia/Manila'));
        $staff = $this->staff();
        $saved = $this->archive($staff);

        $this->actingAs($staff)->get(route('reports.population', ['report_year' => 2024, 'semester' => 1]))
            ->assertRedirect(route('reports.population.submissions.show', $saved));
        $this->get(route('reports.population', ['report_year' => 2026, 'semester' => 1]))
            ->assertOk()->assertSee('No saved report for this period')->assertDontSee('Latest Consolidated records');
        $this->get(route('reports.population', ['report_year' => 2026, 'semester' => 2]))
            ->assertOk()->assertSee('Latest Consolidated records');
    }

    public function test_a_future_period_cannot_preview_print_export_or_sign_current_records(): void
    {
        $this->travelTo(Carbon::parse('2026-12-31 23:59:00', 'Asia/Manila'));
        $staff = $this->staff();
        $this->actingAs($staff);
        $current = $this->get(route('reports.population'))->assertOk()->assertDontSee('<option value="2027"', false);
        $token = $current->viewData('submissionPreviewToken');
        $future = ['report_year' => 2027, 'semester' => 1];

        foreach ([[], ['section' => 'residents'], ['print' => 1]] as $options) {
            $this->get(route('reports.population', $future + $options))->assertOk()
                ->assertSee('This reporting period has yet to start')->assertDontSee('Latest Consolidated records');
        }
        foreach (['pdf', 'excel'] as $format) {
            $this->get(route('reports.population.'.$format, $future))->assertNotFound();
        }
        $preview = session()->get('population_submission_previews.'.$token);
        $preview['snapshot']['monitoring']['year'] = 2027;
        $preview['snapshot']['monitoring']['semester'] = 1;
        $this->withSession(['population_submission_previews' => [$token => $preview]])
            ->get(route('reports.population.submissions.sign', ['preview_token' => $token]))
            ->assertRedirect(route('reports.population'))->assertSessionHasErrors('submission');
        $this->postJson(route('reports.population.submissions.store'), ['preview_token' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('submission');

        $this->travelTo(Carbon::parse('2027-01-01 00:01:00', 'Asia/Manila'));
        $this->get(route('reports.population', $future))->assertOk()->assertSee('Latest Consolidated records');
        $this->get(route('reports.population', ['report_year' => 2026, 'semester' => 2]))
            ->assertOk()->assertSee('No saved report for this period');
    }

    public function test_missing_historical_scope_never_falls_back_to_current_registry_or_another_barangay(): void
    {
        $staff=$this->staff();$this->archive($staff);
        $other=User::factory()->create(['role'=>User::ROLE_BARANGAY,'barangay_id'=>Barangay::where('name','Looc')->firstOrFail()->id]);
        $this->actingAs($other)->get(route('reports.population',['report_year'=>2026,'semester'=>1]))->assertOk()
            ->assertViewIs('reports.population-period-empty')->assertSee('No saved report for this period');
        $this->get(route('reports.population.pdf',['report_year'=>2026,'semester'=>1]))->assertNotFound();
        $this->actingAs(User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]))
            ->get(route('reports.population',['report_year'=>2026,'semester'=>1]))
            ->assertRedirect(route('reports.population.submissions.index',['report_year'=>2026,'semester'=>1]));
        $this->get(route('reports.population.submissions.index',['report_year'=>2026,'semester'=>1]))->assertOk()
            ->assertSee('No reports received for this period');
        $this->assertDatabaseCount('population_report_submissions',1);
    }

    public function test_an_old_open_preview_cannot_submit_current_counts_as_a_historical_period(): void
    {
        $staff=$this->staff();
        $this->travelTo(Carbon::parse('2026-11-13', 'Asia/Manila'));
        $preview=$this->actingAs($staff)->get(route('reports.population'))->assertOk();
        $token=$preview->viewData('submissionPreviewToken');
        $stored=session()->get('population_submission_previews.'.$token);
        $stored['snapshot']['monitoring']['year']=2026;$stored['snapshot']['monitoring']['semester']=1;
        $this->withSession(['population_submission_previews'=>[$token=>$stored]])
            ->postJson(route('reports.population.submissions.store'),['preview_token'=>$token])
            ->assertUnprocessable()->assertJsonValidationErrors('submission');
        $this->assertDatabaseCount('population_report_submissions',0);
    }
}
