<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, PopulationReportSubmission, User};
use App\Services\{PopulationReportSubmissionSnapshot, SemestralPopulationProjection};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PopulationForecastSourceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function report(Barangay $area, int $year, int $semester, int $population, bool $archive = false, array $overrides = [], ?string $submittedAt = null): PopulationReportSubmission
    {
        $month = $semester === 1 ? '06' : '12';
        $snapshot = $archive
            ? ['kind'=>'historical_population_photo', 'population'=>$population, 'age_total_sum'=>$population]
            : ['generatedAt'=>"$year-$month-15T04:00:00.000000Z", 'totalRecords'=>$population,
                'monitoring'=>['year'=>$year, 'semester'=>$semester, 'ages'=>[['total'=>$population]]]];
        $snapshot = array_replace_recursive($snapshot, $overrides);
        $hash = app(PopulationReportSubmissionSnapshot::class)->hash($snapshot);

        return PopulationReportSubmission::create(['barangay_id'=>$area->id, 'barangay_name'=>$area->name,
            'report_year'=>$year, 'semester'=>$semester, 'total_records'=>$population, 'submitted_by_name'=>'Source fixture',
            'snapshot'=>$snapshot, 'snapshot_hash'=>$hash, 'content_hash'=>$hash, 'submission_key'=>(string) Str::uuid(),
            'submitted_at'=>$submittedAt ?? "$year-$month-16 04:00:00"]);
    }

    public function test_completed_submissions_extend_archive_history_without_changing_any_saved_report(): void
    {
        $this->travelTo(Carbon::parse('2028-05-10 12:00:00', 'UTC'));
        $area = Barangay::where('name', 'Carnaga')->firstOrFail();
        $archive = $this->report($area, 2026, 1, 290, true);
        $this->report($area, 2026, 2, 300);
        $this->report($area, 2027, 1, 310);
        $this->report($area, 2027, 2, 320);
        $this->report(Barangay::where('name', 'Looc')->firstOrFail(), 2027, 2, 999);
        $before = PopulationReportSubmission::orderBy('id')->get()->map->getAttributes()->all();

        $service = app(SemestralPopulationProjection::class);
        $result = $service->forBarangay($area->id);
        $this->assertSame([290,300,310,320], array_column($result['history'], 'population'));
        $this->assertSame([300,310,320], array_column($result['inputs'], 'population'));
        $this->assertSame(['archive','submission','submission','submission'], array_column($result['history'], 'source'));
        $this->assertSame($archive->id, $result['history'][0]['id']);
        $this->assertEqualsWithDelta(315, $result['estimate'], .000001);
        $this->assertNull($result['mape']);
        $this->assertSame([], $result['excluded_reports']);
        $staff = User::factory()->create(['role'=>User::ROLE_BARANGAY, 'barangay_id'=>$area->id]);
        $this->actingAs($staff)->get(route('prediction.population'))->assertOk()
            ->assertSee('Archived report')->assertSee('Submitted report')->assertSee('315');
        $this->assertSame($before, PopulationReportSubmission::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($result, $service->forBarangay($area->id));
        $this->assertDatabaseCount('forecast_logs', 0);
    }

    public function test_only_completed_periods_are_used_and_a_genuine_zero_is_not_missing(): void
    {
        $this->travelTo(Carbon::parse('2028-05-10 12:00:00', 'UTC'));
        $area = Barangay::where('name', 'Hugpa')->firstOrFail();
        $this->report($area, 2026, 2, 0);
        $this->report($area, 2027, 1, 0);
        $this->report($area, 2027, 2, 0);
        $this->report($area, 2028, 1, 900);
        $this->report($area, 2028, 2, 999);
        $result = app(SemestralPopulationProjection::class)->forBarangay($area->id);
        $this->assertSame([0,0,0], array_column($result['inputs'], 'population'));
        $this->assertEquals(0, $result['estimate']);
        $this->assertCount(3, $result['history']);
        $this->assertNull($result['mape']);
    }

    public function test_a_real_signed_submission_becomes_a_source_after_its_semester_ends_and_stays_frozen(): void
    {
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-11-15 04:00:00.654321', 'UTC'));
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $staff = User::factory()->create(['role'=>User::ROLE_BARANGAY, 'barangay_id'=>$area->id]);
        $house = Household::create(['barangay_id'=>$area->id, 'household_number'=>'SIGNED-SOURCE']);
        $attributes = ['barangay_id'=>$area->id, 'household_id'=>$house->id, 'first_name'=>'Source', 'last_name'=>'Fixture',
            'sex'=>'Female', 'status'=>'active', 'birth_date'=>'1995-01-01'];
        Inhabitant::create($attributes);
        $preview = $this->actingAs($staff)->get(route('reports.population', ['section'=>'summary','report_year'=>2026,'semester'=>2]))->assertOk();
        $image = imagecreatetruecolor(240,80);
        imagefill($image,0,0,imagecolorallocate($image,255,255,255));
        imagesetthickness($image,3);
        imageline($image,10,60,120,15,imagecolorallocate($image,20,40,60));
        ob_start(); imagepng($image); $signature='data:image/png;base64,'.base64_encode(ob_get_clean());
        $this->post(route('reports.population.submissions.store'), ['preview_token'=>$preview->viewData('submissionPreviewToken'),
            'prepared_name'=>'Source Secretary', 'submitted_name'=>'Source Captain',
            'prepared_signature_data'=>$signature, 'submitted_signature_data'=>$signature])->assertSessionHasNoErrors();
        $saved = PopulationReportSubmission::sole();
        $before = $saved->getAttributes();
        $service = app(SemestralPopulationProjection::class);
        $this->assertSame([], $service->forBarangay($area->id)['history']);
        $this->travelTo(Carbon::parse('2027-01-03 04:00:00', 'UTC'));
        Inhabitant::create($attributes + ['middle_name'=>'New registry entry']);
        $result = $service->forBarangay($area->id);
        $this->assertSame([1], array_column($result['history'], 'population'));
        $this->assertSame($saved->id, $result['history'][0]['id']);
        $this->assertSame([], $result['excluded_reports']);
        $this->assertSame($before, $saved->fresh()->getAttributes());
    }

    public function test_capture_just_before_philippine_semester_end_can_be_submitted_after_the_boundary(): void
    {
        $this->travelTo(Carbon::parse('2027-07-02 04:00:00', 'UTC'));
        $area = Barangay::where('name', 'Hugpa')->firstOrFail();
        $saved = $this->report($area, 2027, 1, 100,
            overrides: ['generatedAt'=>'2027-06-30T15:59:59.999999Z'], submittedAt:'2027-07-01 04:00:00');
        $result = app(SemestralPopulationProjection::class)->forBarangay($area->id);
        $this->assertSame($saved->id, $result['history'][0]['id']);
        $this->assertSame([], $result['excluded_reports']);
    }

    public static function invalidObservations(): array
    {
        return [
            'wrong observation year'=>[['generatedAt'=>'2026-12-15T04:00:00Z'], null, 'outside its labeled semester'],
            'wrong observation semester'=>[['generatedAt'=>'2027-12-15T04:00:00Z'], '2027-12-16 04:00:00', 'outside its labeled semester'],
            'Manila semester boundary'=>[['generatedAt'=>'2027-06-30T16:00:00Z'], '2027-07-01 04:00:00', 'outside its labeled semester'],
            'missing timestamp'=>[['generatedAt'=>null], null, 'valid saved generation timestamp'],
            'invalid calendar date'=>[['generatedAt'=>'2027-06-31T04:00:00Z'], null, 'valid saved generation timestamp'],
            'unqualified local timestamp'=>[['generatedAt'=>'2027-06-15 12:00:00'], null, 'valid saved generation timestamp'],
            'conflicting saved period'=>[['monitoring'=>['semester'=>2]], null, 'does not match its semester label'],
            'generated after submission'=>[['generatedAt'=>'2027-06-17T04:00:00Z'], null, 'not valid for this observation'],
            'future submission'=>[[], '2029-06-16 04:00:00', 'not valid for this observation'],
            'future generation'=>[['generatedAt'=>'2029-06-15T04:00:00Z'], '2029-06-16 04:00:00', 'not valid for this observation'],
        ];
    }

    #[DataProvider('invalidObservations')]
    public function test_mislabeled_or_undated_live_registry_snapshots_are_excluded_with_a_reason(array $overrides, ?string $submittedAt, string $reason): void
    {
        $this->travelTo(Carbon::parse('2028-05-10 12:00:00', 'UTC'));
        $area = Barangay::where('name', 'Hugpa')->firstOrFail();
        $report = $this->report($area, 2027, 1, 100, overrides: $overrides, submittedAt: $submittedAt);
        $before = $report->fresh()->getAttributes();
        $result = app(SemestralPopulationProjection::class)->forBarangay($area->id);
        $this->assertSame([], $result['history']);
        $this->assertNull($result['estimate']);
        $this->assertSame($report->id, $result['excluded_reports'][0]['id']);
        $this->assertStringContainsString($reason, $result['excluded_reports'][0]['reason']);
        $this->assertSame($before, $report->fresh()->getAttributes());
    }

    public function test_integrity_and_conflicting_totals_fail_closed_without_rewriting_originals(): void
    {
        $this->travelTo(Carbon::parse('2028-05-10 12:00:00', 'UTC'));
        $area = Barangay::where('name', 'Hugpa')->firstOrFail();
        $archive = $this->report($area, 2027, 1, 100, true);
        $this->report($area, 2027, 1, 101);
        $before = PopulationReportSubmission::orderBy('id')->get()->map->getAttributes()->all();
        $service = app(SemestralPopulationProjection::class);
        $result = $service->forBarangay($area->id);
        $this->assertNull($result['estimate']);
        $this->assertStringContainsString('Conflicting', $result['reason']);
        $this->assertSame($before, PopulationReportSubmission::orderBy('id')->get()->map->getAttributes()->all());
        // Corrupt only this disposable in-memory fixture to check hash validation.
        DB::table('population_report_submissions')->where('id', $archive->id)->update(['snapshot_hash'=>str_repeat('0', 64)]);
        $result = $service->forBarangay($area->id);
        $this->assertNull($result['estimate']);
        $this->assertStringContainsString('integrity', $result['reason']);
    }

    public function test_equal_population_totals_do_not_duplicate_a_period_or_replace_its_archive_reference(): void
    {
        $this->travelTo(Carbon::parse('2028-05-10 12:00:00', 'UTC'));
        $area = Barangay::where('name', 'Hugpa')->firstOrFail();
        $archive = $this->report($area, 2027, 1, 100, true);
        $this->report($area, 2027, 1, 100);
        $result = app(SemestralPopulationProjection::class)->forBarangay($area->id);
        $this->assertCount(1, $result['history']);
        $this->assertSame($archive->id, $result['history'][0]['id']);
    }

    public function test_the_page_explains_why_a_relabelled_snapshot_is_not_a_historical_observation(): void
    {
        $this->travelTo(Carbon::parse('2028-05-10 12:00:00', 'UTC'));
        $area = Barangay::where('name', 'Hugpa')->firstOrFail();
        $report = $this->report($area, 2027, 1, 100, overrides:['generatedAt'=>'2026-12-15T04:00:00Z']);
        $before = $report->fresh()->getAttributes();
        $staff = User::factory()->create(['role'=>User::ROLE_BARANGAY, 'barangay_id'=>$area->id]);
        $this->actingAs($staff)->get(route('prediction.population'))->assertOk()
            ->assertSee('1 submitted report(s) excluded from forecast inputs')
            ->assertSee('generated outside its labeled semester');
        $this->assertSame($before, $report->fresh()->getAttributes());
    }

    public static function invalidCounts(): array
    {
        return [
            'missing total'=>[['totalRecords'=>null]],
            'negative total'=>[['totalRecords'=>-1]],
            'fractional total'=>[['totalRecords'=>1.5]],
            'metadata disagreement'=>[['totalRecords'=>101,'monitoring'=>['ages'=>[['total'=>101]]]]],
            'missing age totals'=>[['monitoring'=>['ages'=>null]]],
            'fractional age count'=>[['monitoring'=>['ages'=>[['total'=>100.5]]]]],
            'unreconciled age sum'=>[['monitoring'=>['ages'=>[['total'=>99]]]]],
        ];
    }

    #[DataProvider('invalidCounts')]
    public function test_inconsistent_submitted_totals_cannot_silently_become_forecast_inputs(array $overrides): void
    {
        $this->travelTo(Carbon::parse('2028-05-10 12:00:00', 'UTC'));
        $area = Barangay::where('name', 'Hugpa')->firstOrFail();
        $this->report($area, 2027, 1, 100, overrides: $overrides);
        $result = app(SemestralPopulationProjection::class)->forBarangay($area->id);
        $this->assertNull($result['estimate']);
        $this->assertStringContainsString('inconsistent population totals', $result['reason']);
    }
}
