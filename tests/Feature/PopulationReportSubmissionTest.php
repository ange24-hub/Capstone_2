<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, PopulationReportSubmission, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PopulationReportSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function signaturePayload(): array
    {
        $image = imagecreatetruecolor(240, 80);
        imagefill($image, 0, 0, imagecolorallocate($image,255,255,255));
        $ink=imagecolorallocate($image,20,30,50);
        imagesetthickness($image,3);
        imageline($image,15,60,80,15,$ink); imageline($image,80,15,130,65,$ink); imageline($image,130,65,220,25,$ink);
        ob_start(); imagepng($image); $bytes=ob_get_clean();
        return ['prepared_name'=>'Test Secretary', 'submitted_name'=>'Test Captain',
            'prepared_signature_data'=>'data:image/png;base64,'.base64_encode($bytes),
            'submitted_signature_data'=>'data:image/png;base64,'.base64_encode($bytes)];
    }

    public function test_signing_step_requires_both_signatures_and_accepts_drawn_and_uploaded_images(): void
    {
        $staff=$this->secretary();
        $token=$this->preview($staff);
        $this->get(route('reports.population.submissions.sign',['preview_token'=>$token]))->assertOk()->assertSee('Confirm signatures and submit');
        $this->assertDatabaseCount('population_report_submissions',0);
        $this->postJson(route('reports.population.submissions.store'),['preview_token'=>$token])
            ->assertUnprocessable()->assertJsonValidationErrors(['prepared_name','submitted_name']);
        $payload=$this->signaturePayload();
        $this->postJson(route('reports.population.submissions.store'),['preview_token'=>$token]+array_diff_key($payload,['submitted_signature_data'=>1]))
            ->assertUnprocessable()->assertJsonValidationErrors('submitted_signature_data');
        $bytes=base64_decode(explode(',',$payload['submitted_signature_data'],2)[1]);
        unset($payload['submitted_signature_data']);
        $payload['submitted_signature_upload']=\Illuminate\Http\UploadedFile::fake()->createWithContent('signature.png',$bytes);
        $this->post(route('reports.population.submissions.store'),['preview_token'=>$token]+$payload)->assertSessionHasNoErrors()->assertRedirect();
        $saved=PopulationReportSubmission::firstOrFail();
        $this->assertSame('drawn_signature',$saved->snapshot['signatures']['prepared']['method']);
        $this->assertSame('uploaded_image',$saved->snapshot['signatures']['submitted']['method']);
        $response=$this->get(route('reports.population.submissions.show',$saved))->assertOk()->assertSee('data:image/png;base64,',false)->assertSee('Test Captain');
        $html=view('reports.population-pdf',['report'=>$response->viewData('report')])->render();
        $this->assertStringContainsString('data:image/png;base64,',$html);
        $this->assertStringContainsString('Barangay Secretary signature',$html);
    }

    public function test_blank_or_invalid_signatures_do_not_save_and_foreign_signing_preview_is_rejected(): void
    {
        $staff=$this->secretary(); $token=$this->preview($staff); $payload=$this->signaturePayload();
        $image=imagecreatetruecolor(200,80); imagefill($image,0,0,imagecolorallocate($image,255,255,255));
        ob_start(); imagepng($image); $blank=ob_get_clean();
        foreach (['data:image/png;base64,'.base64_encode($blank), 'data:image/svg+xml;base64,'.base64_encode('<svg></svg>')] as $data) {
            $payload['prepared_signature_data']=$data;
            $this->postJson(route('reports.population.submissions.store'),['preview_token'=>$token]+$payload)
                ->assertUnprocessable()->assertJsonValidationErrors('prepared_signature_data');
        }
        $other=$this->secretary('Canlupao');
        $this->actingAs($other)->get(route('reports.population.submissions.sign',['preview_token'=>$token]))
            ->assertRedirect(route('reports.population'))->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('population_report_submissions',0);
    }

    private function secretary(string $area = 'Looc'): User
    {
        return User::factory()->create(['role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', $area)->firstOrFail()->id]);
    }

    private function resident(User $staff, array $attributes = []): Inhabitant
    {
        $house = Household::firstOrCreate(['barangay_id' => $staff->barangay_id, 'household_number' => 'SUBMISSION-TEST']);
        return Inhabitant::create($attributes + ['barangay_id' => $staff->barangay_id, 'household_id' => $house->id,
            'first_name' => 'Example', 'last_name' => 'Resident', 'sex' => 'Male',
            'status' => Inhabitant::STATUS_ACTIVE, 'birth_date' => '2000-01-01']);
    }

    private function preview(User $staff): string
    {
        return $this->actingAs($staff)->get(route('reports.population', ['report_year' => 2026, 'semester' => 2]))
            ->assertOk()->assertSee('Submit to Municipal DILG/Admin')->viewData('submissionPreviewToken');
    }

    private function submit(User $staff): PopulationReportSubmission
    {
        $token = $this->preview($staff);
        $this->post(route('reports.population.submissions.store'), ['preview_token' => $token] + $this->signaturePayload())->assertSessionHasNoErrors()->assertRedirect();
        return PopulationReportSubmission::latest('id')->firstOrFail();
    }

    public function test_submission_saves_reviewed_report_once_and_municipal_can_receive_and_export_it(): void
    {
        $staff = $this->secretary();
        $this->resident($staff);
        $this->resident($staff, ['remarks' => 'RBIM migration demo v1']);
        $this->resident($staff, ['status' => Inhabitant::STATUS_MIGRATED_OUT]);
        $token = $this->preview($staff);
        $this->assertDatabaseCount('population_report_submissions', 0);
        foreach (range(1, 2) as $attempt) {
            $this->post(route('reports.population.submissions.store'), ['preview_token' => $token] + $this->signaturePayload())->assertRedirect()->assertSessionHasNoErrors();
        }
        $saved = $this->submit($staff); // New preview, same report: no duplicate.
        $this->assertDatabaseCount('population_report_submissions', 1);
        $this->assertSame(1, $saved->total_records);
        $this->assertSame($staff->id, $saved->submitted_by);
        $this->assertDatabaseCount('registry_activities', 1);
        $this->assertStringNotContainsString('Example', json_encode($saved->snapshot));
        $admin = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($admin)->get(route('reports.population.submissions.index'))->assertOk()->assertSee($saved->reference)->assertSee($staff->name);
        $this->get(route('reports.population.submissions.show', $saved))->assertOk()->assertSee('MONITORING REPORT')->assertDontSee('name="preview_token"', false);
        $this->get(route('reports.population.submissions.pdf', $saved))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('reports.population.submissions.excel', $saved))->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertDatabaseCount('population_report_submissions', 1);
    }

    public function test_saved_report_stays_frozen_and_revised_summary_creates_another_copy(): void
    {
        $staff = $this->secretary();
        $person = $this->resident($staff);
        $saved = $this->submit($staff);
        $original = $saved->snapshot;
        $person->update(['sex' => 'Female']);
        $this->resident($staff);
        Barangay::whereKey($staff->barangay_id)->update(['secretary_name' => 'Changed signatory']);
        $report = $this->get(route('reports.population.submissions.show', $saved))->assertOk()->viewData('report');
        $this->assertSame(1, $report['totalRecords']);
        $this->assertSame(1, $report['sex']['Male']);
        $this->assertSame(0, $report['sex']['Female']);
        $this->assertSame($original['monitoring'], $report['monitoring']);
        $new = $this->submit($staff);
        $this->assertNotSame($saved->id, $new->id);
        $this->assertSame(2, $new->total_records);
        $this->assertSame($original, $saved->fresh()->snapshot);
        $this->get(route('reports.population.submissions.index'))->assertOk()->assertSee($saved->reference)->assertSee($new->reference);
    }

    public function test_stale_missing_expired_and_foreign_previews_cannot_be_submitted(): void
    {
        $staff = $this->secretary();
        $person = $this->resident($staff);
        $token = $this->preview($staff);
        $person->update(['sex' => 'Female']);
        $this->postJson(route('reports.population.submissions.store'), ['preview_token' => $token] + $this->signaturePayload())->assertUnprocessable()->assertJsonValidationErrors('submission');
        $this->postJson(route('reports.population.submissions.store'), ['preview_token' => (string) Str::uuid()])->assertUnprocessable();
        $token = $this->preview($staff);
        $this->withSession(['population_submission_previews' => [$token => ['user_id' => $staff->id,
            'barangay_id' => $staff->barangay_id, 'expires_at' => time() - 1]]])
            ->postJson(route('reports.population.submissions.store'), ['preview_token' => $token] + $this->signaturePayload())->assertUnprocessable();
        $token = $this->preview($staff);
        $this->actingAs($this->secretary('Canlupao'))->postJson(route('reports.population.submissions.store'), ['preview_token' => $token] + $this->signaturePayload())->assertUnprocessable();
        $this->assertDatabaseCount('population_report_submissions', 0);
    }

    public function test_barangay_scope_is_enforced_for_history_saved_report_and_exports(): void
    {
        $owner = $this->secretary();
        $saved = $this->submit($owner);
        $other = $this->secretary('Canlupao');
        $this->actingAs($other)->get(route('reports.population.submissions.index'))->assertOk()->assertDontSee($saved->reference);
        $this->get(route('reports.population.submissions.index', ['barangay_id' => $owner->barangay_id]))->assertForbidden();
        foreach (['show', 'pdf', 'excel'] as $action) {
            $this->get(route('reports.population.submissions.'.$action, $saved))->assertForbidden();
        }
        $admin = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($admin)->post(route('reports.population.submissions.store'), ['preview_token' => (string) Str::uuid()])->assertForbidden();
        $this->get(route('reports.population.submissions.index', ['barangay_id' => $other->barangay_id]))->assertOk()->assertDontSee($saved->reference);
        $this->get(route('reports.population.submissions.index', ['semester' => 1]))->assertOk()->assertDontSee($saved->reference);
    }

    public function test_submit_button_only_appears_on_approved_secretary_summary(): void
    {
        $staff = $this->secretary();
        $this->preview($staff);
        foreach ([['print' => 1], ['section' => 'residents']] as $query) {
            $this->get(route('reports.population', $query))->assertOk()->assertDontSee('name="preview_token"', false);
        }
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]))
            ->get(route('reports.population'))->assertOk()->assertSee('Submission History')->assertDontSee('name="preview_token"', false);
        foreach ([['role' => User::ROLE_RESIDENT], ['role' => User::ROLE_BARANGAY, 'barangay_id' => null],
            ['role' => User::ROLE_BARANGAY, 'barangay_id' => $staff->barangay_id, 'approval_status' => 'pending']] as $attributes) {
            $user = User::factory()->create($attributes);
            $history = $this->actingAs($user)->get(route('reports.population.submissions.index'));
            $submit = $this->post(route('reports.population.submissions.store'), ['preview_token' => (string) Str::uuid()]);
            if (($attributes['approval_status'] ?? '') === 'pending') {
                $history->assertRedirect(route('approval.pending'));
                $submit->assertRedirect(route('approval.pending'));
            } else {
                $history->assertForbidden();
                $submit->assertForbidden();
            }
        }
    }

    public function test_saved_snapshot_integrity_is_checked(): void
    {
        $saved = $this->submit($this->secretary());
        DB::table('population_report_submissions')->where('id', $saved->id)->update(['snapshot_hash' => str_repeat('0', 64)]);
        foreach (['show', 'pdf', 'excel'] as $action) {
            $this->get(route('reports.population.submissions.'.$action, $saved))->assertStatus(409);
        }
    }
}
