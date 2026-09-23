<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\Inhabitant;
use App\Support\PopulationMarkers;

class BarangayDashboardPopulation
{
    public function counts(Barangay $barangay): array
    {
        $counts = ['residents' => 0, 'pwd' => 0, 'seniors' => 0, 'indigent' => 0];
        $today = now('Asia/Manila')->startOfDay();
        $records = $barangay->inhabitants()
            ->when($barangay->usesResidenceRegistry(), fn ($query) => $query->where('status', Inhabitant::STATUS_ACTIVE))
            ->select(['id', 'birth_date', 'remarks'])->cursor();

        foreach ($records as $record) {
            $counts['residents']++;
            $counts['pwd'] += PopulationMarkers::has($record->remarks, 'pwd') ? 1 : 0;
            $counts['indigent'] += PopulationMarkers::has($record->remarks, 'indigent') ? 1 : 0;
            $senior = PopulationMarkers::has($record->remarks, 'sc');
            try {
                $birth = $record->birth_date?->shiftTimezone('Asia/Manila')->startOfDay();
                $senior = $senior || ($birth && $birth->lte($today) && $birth->diffInYears($today) >= 60);
            } catch (\Throwable $e) {
                // Imported invalid dates cannot establish age; retain explicit SC markers.
            }
            $counts['seniors'] += $senior ? 1 : 0;
        }

        return $counts;
    }
}
