<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\MigrationRecord;
use App\Models\MigrationReportingPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationForecastEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_download_contains_aggregate_readiness_and_hashes_without_writing_sources(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $before = [BarangayRbiUpdate::count(), MigrationRecord::count(), MigrationReportingPeriod::count()];
        $response = $this->actingAs($admin)->get(route('municipal.prediction-forecast-evidence'));
        $response->assertOk()->assertHeader('content-type', 'application/json');
        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));
        $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(Barangay::count()+1, $data['scopes']);
        $this->assertSame(hash_file('sha256', app_path('Services/ValidatedMigrationForecast.php')), $data['implementation_sha256']['ValidatedMigrationForecast']);
        $this->assertFalse($data['source_records_changed']);
        foreach ($data['scopes'] as $scope) {
            $this->assertFalse($scope['in']['evaluation']['ready']);
            $this->assertFalse($scope['out']['evaluation']['ready']);
            $this->assertNull($scope['in']['evaluation']['rmse']);
            $this->assertSame([], $scope['future_estimates']);
        }
        $this->assertSame($before, [BarangayRbiUpdate::count(), MigrationRecord::count(), MigrationReportingPeriod::count()]);
        $this->assertStringNotContainsString('first_name', json_encode($data));
        $this->assertStringNotContainsString('birth_date', json_encode($data));
    }

    public function test_forecast_evidence_download_is_restricted_to_municipal_admins(): void
    {
        $this->get(route('municipal.prediction-forecast-evidence'))->assertRedirect(route('login'));
        foreach ([User::ROLE_BARANGAY, 'resident'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('municipal.prediction-forecast-evidence'))->assertForbidden();
        }
    }
}
