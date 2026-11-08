<?php

namespace Tests\Support;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\RbiArrivalPeriod;
use App\Models\User;
use App\Services\RbiArrivalReview;
use Illuminate\Support\Carbon;

trait SubmittedArrivalFixture
{
    private function submittedArrivals(Barangay $area, User $staff, string $first, array $counts, bool $review = true): void
    {
        foreach ($counts as $i => $count) {
            $month = Carbon::createFromFormat('!Y-m', $first)->addMonths($i)->format('Y-m');
            $rows = [];
            for ($j = 0; $j < $count; $j++) {
                $rows[] = ['first_name' => 'Fixture-'.$area->id.'-'.$month.'-'.$j, 'last_name' => 'Arrival', 'birth_date' => '1990-01-01', 'entry_type' => 'in_migration', 'previous_residence' => 'Cebu', 'arrival_date' => $month.'-01'];
            }
            $savedAt = Carbon::createFromFormat('!Y-m', $month)->addDays(10);
            $report = BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => $area->name, 'reporting_month' => $month.'-01', 'rows' => $rows, 'status' => 'submitted', 'submitted_at' => now()]);
            $report->timestamps = false;
            $report->forceFill(['created_at' => $savedAt, 'updated_at' => $savedAt])->saveQuietly();
        }
        if ($review) {
            $this->reviewArrivals($area, $staff);
        }
    }

    private function reviewArrivals(Barangay $area, User $staff): void
    {
        foreach (app(RbiArrivalReview::class)->periods($area) as $period) {
            if (! $period['complete']) {
                continue;
            }
            RbiArrivalPeriod::updateOrCreate(['barangay_id' => $area->id, 'start_month' => $period['start']], ['end_month' => $period['end'],
                'source_fingerprint' => $period['fingerprint'], 'monthly_counts' => array_column($period['months'], 'eligible', 'month'),
                'reviewed_by' => $staff->id, 'reviewed_at' => now()]);
        }
    }
}
