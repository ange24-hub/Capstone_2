<?php

namespace Tests\Feature;

use App\Models\{Barangay, PopulationReportSubmission, User};
use App\Services\HistoricalPopulationReportImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HistoricalPopulationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_is_private_deduplicated_and_does_not_create_residents_or_migration_data(): void
    {
        Storage::fake('local');
        $area = Barangay::where('name', 'Carnaga')->firstOrFail();
        $photo = UploadedFile::fake()->image('report.jpg', 100, 100);
        $data = ['year'=>2024, 'semester'=>1, 'population'=>459, 'households'=>107, 'families'=>112,
            'age_totals'=>[21,25,34,56,35,32,28,23,28,33,35,14,13,15,18,8,11]];
        $service = app(HistoricalPopulationReportImport::class);
        $saved = $service->import($area, $photo->getPathname(), $data);
        $this->assertSame($saved->id, $service->import($area, $photo->getPathname(), $data)->id);
        $this->assertDatabaseCount('population_report_submissions', 1);
        foreach (['inhabitants', 'migration_logs', 'forecast_logs'] as $table) $this->assertDatabaseCount($table, 0);
        $this->assertNull($saved->snapshot['original_submission_date']);
        $this->assertSame(429, $saved->snapshot['age_total_sum']);
        $user = User::factory()->create(['role'=>User::ROLE_BARANGAY, 'barangay_id'=>$area->id]);
        $this->actingAs($user)->get(route('reports.population.submissions.index'))->assertOk()->assertSee('Archived historical report');
        foreach (['reports.population','reports.population.pdf','reports.population.excel'] as $route) {
            $this->get(route($route, ['report_year'=>2024,'semester'=>1]))
                ->assertRedirect(route('reports.population.submissions.show',$saved));
        }
        $this->get(route('reports.population',['report_year'=>2024,'semester'=>2]))->assertOk();
        $this->get(route('reports.population.submissions.show', $saved))->assertOk()->assertSee('Needs reconciliation')->assertSee('459')->assertSee('429');
        $this->get(route('reports.population.submissions.source', $saved))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('reports.population.submissions.source', ['submission'=>$saved->id, 'download'=>1]))
            ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="'.$saved->reference.'-original.jpg"');
        $this->get(route('reports.population.submissions.excel', $saved))->assertNotFound();
        $other = User::factory()->create(['role'=>User::ROLE_BARANGAY, 'barangay_id'=>Barangay::where('name','Looc')->firstOrFail()->id]);
        $this->actingAs($other)->get(route('reports.population.submissions.source', $saved))->assertForbidden();
        $this->get(route('reports.population.submissions.show', $saved))->assertForbidden();
        $admin = User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($admin)->get(route('reports.population.submissions.show', $saved))->assertOk();
        Storage::disk('local')->put('private/population-reports/'.$saved->snapshot['source_sha256'].'.jpg', 'corrupt');
        $this->get(route('reports.population.submissions.source', $saved))->assertStatus(409);
        $this->assertDatabaseCount('population_report_submissions', 1);
    }
}
