<?php

namespace Tests\Feature;

use App\Models\{Barangay, PopulationReportSubmission, User};
use App\Services\HistoricalPopulationReportImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MunicipalPopulationInboxTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $name): User
    {
        return User::factory()->create(['role'=>User::ROLE_BARANGAY,'barangay_id'=>Barangay::where('name',$name)->firstOrFail()->id]);
    }

    private function submit(User $staff, int $year, int $semester): PopulationReportSubmission
    {
        $token = $this->actingAs($staff)->get(route('reports.population',['report_year'=>$year,'semester'=>$semester]))->assertOk()->viewData('submissionPreviewToken');
        $image=imagecreatetruecolor(240,80); imagefill($image,0,0,imagecolorallocate($image,255,255,255));imagesetthickness($image,3);
        imageline($image,10,60,220,20,imagecolorallocate($image,20,40,60));ob_start();imagepng($image);$png='data:image/png;base64,'.base64_encode(ob_get_clean());
        $this->post(route('reports.population.submissions.store'),['preview_token'=>$token,'prepared_name'=>'Sample Secretary','submitted_name'=>'Sample Captain','prepared_signature_data'=>$png,'submitted_signature_data'=>$png])->assertSessionHasNoErrors();
        return PopulationReportSubmission::latest('id')->firstOrFail();
    }

    public function test_admin_inbox_contains_only_submissions_for_the_selected_year_and_semester(): void
    {
        $first=$this->submit($this->staff('Carnaga'),2027,1);
        $second=$this->submit($this->staff('Maslog'),2027,2);
        $older=$this->submit($this->staff('Looc'),2026,2);
        $admin=User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($admin)->get(route('reports.population',['report_year'=>2027,'semester'=>1]))
            ->assertRedirect(route('reports.population.submissions.index',['report_year'=>2027,'semester'=>1]));
        $response=$this->get(route('reports.population.submissions.index',['report_year'=>2027,'semester'=>1]))->assertOk()
            ->assertSee($first->reference)->assertDontSee($second->reference)->assertDontSee($older->reference)
            ->assertDontSee('Canlupao')->assertSee('1st Semester 2027')->assertDontSee('Latest Consolidated records');
        $this->assertSame(1,$response->viewData('submissions')->total());
        $this->get(route('reports.population.submissions.index',['report_year'=>2027,'semester'=>2]))->assertOk()
            ->assertSee($second->reference)->assertDontSee($first->reference);
        $this->get(route('reports.population.submissions.index',['report_year'=>2027,'semester'=>1,'barangay_id'=>$second->barangay_id]))->assertOk()
            ->assertSee('No reports received for this period')->assertDontSee($first->reference);
    }

    public function test_admin_cannot_generate_unsubmitted_reports_or_read_private_historical_archives(): void
    {
        Storage::fake('local');$staff=$this->staff('Carnaga');$photo=UploadedFile::fake()->image('archive.jpg',80,80);
        $archive=app(HistoricalPopulationReportImport::class)->import($staff->barangay,$photo->getPathname(),
            ['year'=>2024,'semester'=>1,'population'=>459,'households'=>107,'families'=>112,'age_totals'=>array_fill(0,17,0)]);
        $admin=User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]);$this->actingAs($admin);
        foreach (['show','pdf','excel','source'] as $action) $this->get(route('reports.population.submissions.'.$action,$archive))->assertNotFound();
        foreach (['reports.population.pdf','reports.population.excel'] as $route) {
            foreach (['summary','residents','families','households'] as $section) $this->get(route($route,
                ['barangay_id'=>$staff->barangay_id,'report_year'=>2027,'semester'=>1,'section'=>$section]))->assertNotFound();
            $this->get(route($route,['report_year'=>2027,'semester'=>1]))->assertNotFound();
        }
        $this->get(route('reports.population.submissions.index',['report_year'=>2024,'semester'=>1]))->assertOk()
            ->assertDontSee($archive->reference)->assertDontSee('459');
        $this->actingAs($staff)->get(route('reports.population.submissions.show',$archive))->assertOk()->assertSee('459');
        $this->get(route('reports.population.submissions.source',$archive))->assertOk();
        $this->assertSame($archive->snapshot,$archive->fresh()->snapshot);
    }

    public function test_municipal_exports_resolve_only_the_exact_submitted_snapshot_even_after_account_deletion(): void
    {
        $staff=$this->staff('Carnaga');$saved=$this->submit($staff,2027,1);$original=$saved->snapshot;
        $staff->delete();
        $this->actingAs(User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]));
        $period=['barangay_id'=>$saved->barangay_id,'report_year'=>2027,'semester'=>1];
        $this->get(route('reports.population.pdf',$period))->assertRedirect(route('reports.population.submissions.pdf',$saved));
        $this->get(route('reports.population.excel',$period))->assertRedirect(route('reports.population.submissions.excel',$saved));
        $this->get(route('reports.population.pdf',array_replace($period,['semester'=>2])))->assertNotFound();
        $this->get(route('reports.population.submissions.show',$saved))->assertOk()->assertSee('Back to semester reports');
        $this->get(route('reports.population.submissions.index',['report_year'=>2027,'semester'=>1]))->assertOk()->assertSee($saved->reference);
        $this->assertSame($original,$saved->fresh()->snapshot);
    }
}
