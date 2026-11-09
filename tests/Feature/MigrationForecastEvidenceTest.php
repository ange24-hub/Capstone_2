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



    public function test_forecast_evidence_download_is_restricted_to_municipal_admins(): void
    {
        $this->get(route('municipal.prediction-forecast-evidence'))->assertRedirect(route('login'));
        foreach ([User::ROLE_BARANGAY, 'resident'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('municipal.prediction-forecast-evidence'))->assertForbidden();
        }
    }
}
