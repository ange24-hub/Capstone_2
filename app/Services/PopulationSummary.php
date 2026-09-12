<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Support\PopulationMarkers;

class PopulationSummary
{
    public function build(?int $barangayId): array
    {
        $generatedAt = now();
        $today = $generatedAt->copy()->startOfDay();
        $barangays = Barangay::when($barangayId, fn ($q) => $q->whereKey($barangayId))->orderBy('name')->get(['id', 'name']);
        $records = Inhabitant::whereIn('barangay_id', $barangays->modelKeys())
            ->get(['barangay_id', 'household_id', 'family_number', 'sex', 'birth_date', 'remarks', 'status', 'residence_status', 'updated_at']);
        $householdRows = Household::whereIn('barangay_id', $barangays->modelKeys())->get(['id', 'barangay_id', 'household_number']);
        $householdLookup = $householdRows->keyBy('id');
        $households = $householdRows->groupBy('barangay_id')->map(fn ($items) => $items
            ->map(function ($item): string {
                $number = trim((string) $item->household_number);
                // Decimal suffixes identify additional families in the base household.
                // Treat identifiers as strings so 64.1 and 64.10 remain distinct families.
                return $this->baseHouseholdNumber($number);
            })->uniqueStrict()->count());
        $sex = ['Male' => 0, 'Female' => 0, 'Not specified / other' => 0];
        $ages = ['0–4' => 0, '5–17' => 0, '18–59' => 0, '60+' => 0, 'Unknown / invalid birth date' => 0];
        $statuses = ['Active' => 0, 'Migrated out' => 0, 'Inactive' => 0, 'Other / unspecified' => 0];
        $confirmedLivingHere = 0;
        $demographics = [];
        foreach ($records as $record) {
            $demographics[$record->barangay_id] ??= ['sex' => array_fill_keys(array_keys($sex), 0), 'ages' => array_fill_keys(array_keys($ages), 0), 'pwd' => 0, 'seniors' => 0, 'seniorRemarksOnly' => 0];
            $sexLabel = match (strtolower(trim((string) $record->sex))) {
                'male', 'm' => 'Male', 'female', 'f' => 'Female', default => 'Not specified / other',
            };
            $sex[$sexLabel]++;
            $demographics[$record->barangay_id]['sex'][$sexLabel]++;
            $ageLabel = 'Unknown / invalid birth date';
            try {
                $birth = $record->birth_date;
                if ($birth && $birth->lte($today)) {
                    $age = (int) $birth->diffInYears($today);
                    $ageLabel = match (true) { $age < 5 => '0–4', $age < 18 => '5–17', $age < 60 => '18–59', default => '60+' };
                }
            } catch (\Throwable $e) {
                // A malformed imported date remains explicitly unknown in the summary.
            }
            $ages[$ageLabel]++;
            $demographics[$record->barangay_id]['ages'][$ageLabel]++;
            $demographics[$record->barangay_id]['pwd'] += PopulationMarkers::has($record->remarks, 'pwd') ? 1 : 0;
            $seniorByAge = $ageLabel === '60+';
            $seniorByRemarks = PopulationMarkers::has($record->remarks, 'sc');
            $demographics[$record->barangay_id]['seniors'] += ($seniorByAge || $seniorByRemarks) ? 1 : 0;
            $demographics[$record->barangay_id]['seniorRemarksOnly'] += (!$seniorByAge && $seniorByRemarks) ? 1 : 0;
            $statuses[match ($record->status) {
                Inhabitant::STATUS_ACTIVE => 'Active', Inhabitant::STATUS_MIGRATED_OUT => 'Migrated out',
                Inhabitant::STATUS_INACTIVE => 'Inactive', default => 'Other / unspecified',
            }]++;
            if ($record->status === Inhabitant::STATUS_ACTIVE && $record->residence_status === Inhabitant::RESIDENCE_HERE) {
                $confirmedLivingHere++;
            }
        }
        $grouped = $records->groupBy('barangay_id');
        $coverage = $barangays->map(function ($barangay) use ($grouped, $households, $householdLookup, $demographics, $sex, $ages) {
            $items = $grouped->get($barangay->id, collect());
            $familyNumbers = $items->map(fn ($item) => trim((string) $item->family_number));
            // Apply the single-person rule across the base household, including decimal rows.
            // A resident with a family number is already included in the distinct-family count.
            $singlePersonFamiliesAdded = $items
                ->filter(fn ($item) => $householdLookup->get($item->household_id)?->barangay_id === $barangay->id)
                ->groupBy(fn ($item) => $this->baseHouseholdNumber($householdLookup->get($item->household_id)->household_number))
                ->filter(fn ($members) => $members->count() === 1 && trim((string) $members->first()->family_number) === '')
                ->count();
            return [
                'name' => $barangay->name, 'records' => $items->count(), 'households' => (int) $households->get($barangay->id, 0),
                'families' => $familyNumbers->filter(fn ($number) => $number !== '')->uniqueStrict()->count() + $singlePersonFamiliesAdded,
                'singlePersonFamiliesAdded' => $singlePersonFamiliesAdded,
                'recordsWithoutFamily' => $familyNumbers->filter(fn ($number) => $number === '')->count(),
                'lastUpdated' => $items->max('updated_at'),
                'label' => $items->isEmpty() ? 'No resident records encoded' : 'Records available; completeness unverified',
                'sex' => $demographics[$barangay->id]['sex'] ?? array_fill_keys(array_keys($sex), 0),
                'ages' => $demographics[$barangay->id]['ages'] ?? array_fill_keys(array_keys($ages), 0),
                'seniors' => $demographics[$barangay->id]['seniors'] ?? 0,
                'pwd' => $demographics[$barangay->id]['pwd'] ?? 0,
                'seniorRemarksOnly' => $demographics[$barangay->id]['seniorRemarksOnly'] ?? 0,
            ];
        });

        return compact('generatedAt', 'sex', 'ages', 'statuses', 'coverage', 'confirmedLivingHere') + [
            'scopeLabel' => $barangayId ? 'Barangay '.$barangays->firstOrFail()->name : 'Tomas Oppus — available barangay records',
            'totalRecords' => $records->count(), 'totalHouseholds' => (int) $households->sum(),
            'encodedHouseholdRows' => $householdRows->count(),
            'totalSeniors' => (int) $coverage->sum('seniors'),
            'totalPwd' => (int) $coverage->sum('pwd'),
            'seniorRemarksOnly' => (int) $coverage->sum('seniorRemarksOnly'),
            'totalFamilies' => (int) $coverage->sum('families'),
            'singlePersonFamiliesAdded' => (int) $coverage->sum('singlePersonFamiliesAdded'),
            'recordsWithoutFamily' => (int) $coverage->sum('recordsWithoutFamily'),
            'coveredBarangays' => $coverage->where('records', '>', 0)->count(), 'totalBarangays' => $barangays->count(),
        ];
    }

    private function baseHouseholdNumber(string $number): string
    {
        $number = trim($number);

        return preg_match('/^([0-9]+)\.[0-9]+$/D', $number, $match) ? $match[1] : $number;
    }
}
