<?php

namespace App\Services;

use App\Models\PopulationReportSubmission;

class PopulationReportPeriod
{
    public static function maximumYear(): int
    {
        return max(2027, now()->year + 5);
    }

    public function usesHistory(int $year, int $semester): bool
    {
        // Legacy periods end at 2026 H1. The current 2026 H2 rollout and
        // 2027 onward use the live Consolidated registry.
        return $year < 2026 || ($year === 2026 && $semester === 1);
    }

    public function nearestSaved(int $barangayId, int $year, int $semester): ?PopulationReportSubmission
    {
        return PopulationReportSubmission::where('barangay_id', $barangayId)
            ->where(fn ($query) => $query->where('report_year', '<', $year)
                ->orWhere(fn ($period) => $period->where('report_year', $year)->where('semester', '<=', $semester)))
            ->orderByDesc('report_year')->orderByDesc('semester')
            ->orderByDesc('submitted_at')->orderByDesc('id')->first();
    }

    public function submitted(int $barangayId, int $year, int $semester): ?PopulationReportSubmission
    {
        return PopulationReportSubmission::where('barangay_id', $barangayId)
            ->where('report_year', $year)->where('semester', $semester)
            ->orderByDesc('submitted_at')->orderByDesc('id')->first();
    }
}
