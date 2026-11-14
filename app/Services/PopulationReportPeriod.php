<?php

namespace App\Services;

use App\Models\PopulationReportSubmission;

class PopulationReportPeriod
{
    public static function maximumYear(): int
    {
        return max(2027, now()->year + 5);
    }

    public static function current(): array
    {
        $date = now(config('migration_forecast.timezone', 'Asia/Manila'));

        return ['report_year' => $date->year, 'semester' => $date->month <= 6 ? 1 : 2];
    }

    public function canGenerate(int $year, int $semester): bool
    {
        return ['report_year' => $year, 'semester' => $semester] === self::current();
    }

    public function submitted(int $barangayId, int $year, int $semester): ?PopulationReportSubmission
    {
        return PopulationReportSubmission::where('barangay_id', $barangayId)
            ->where('report_year', $year)->where('semester', $semester)
            ->orderByDesc('submitted_at')->orderByDesc('id')->first();
    }
}
