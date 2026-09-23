<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Support\PopulationMarkers;

class PopulationSummary
{
    public const SECTIONS = [
        'summary' => 'Full summary',
        'residents' => 'Registered residents',
        'families' => 'Families',
        'households' => 'Households',
        'seniors' => 'Senior citizens',
        'pwd' => 'Persons with disability',
        'sex' => 'Population by sex',
        'ages' => 'Population by age',
        'coverage' => 'Summary by barangay',
        'area-ages' => 'Age groups by barangay',
    ];

    public function build(?int $barangayId, string $section = 'summary'): array
    {
        $generatedAt = now();
        $today = $generatedAt->copy()->startOfDay();
        $barangays = Barangay::when($barangayId, fn ($q) => $q->whereKey($barangayId))->orderBy('name')->get(['id', 'name']);
        $withNames = in_array($section, ['residents', 'families', 'households', 'seniors', 'pwd'], true);
        $columns = ['barangay_id', 'household_id', 'family_number', 'sex', 'birth_date', 'remarks', 'status', 'residence_status', 'updated_at'];
        if ($withNames) {
            $columns = array_merge($columns, ['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'relationship_to_head']);
        }
        $records = Inhabitant::whereIn('barangay_id', $barangays->modelKeys())
            ->when($withNames, fn ($query) => $query->orderBy('last_name')->orderBy('first_name')->orderBy('id'))
            ->get($columns);
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
        $namedRecords = collect();
        $areaNames = $barangays->pluck('name', 'id');
        foreach ($records as $record) {
            $demographics[$record->barangay_id] ??= ['sex' => array_fill_keys(array_keys($sex), 0), 'ages' => array_fill_keys(array_keys($ages), 0), 'pwd' => 0, 'seniors' => 0, 'seniorRemarksOnly' => 0];
            $sexLabel = match (strtolower(trim((string) $record->sex))) {
                'male', 'm' => 'Male', 'female', 'f' => 'Female', default => 'Not specified / other',
            };
            $sex[$sexLabel]++;
            $demographics[$record->barangay_id]['sex'][$sexLabel]++;
            $ageLabel = 'Unknown / invalid birth date';
            $age = null;
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
            if ($withNames) {
                $household = $householdLookup->get($record->household_id);
                $validHousehold = $household && $household->barangay_id === $record->barangay_id;
                $namedRecords->push([
                    'name' => $record->fullName(), 'barangay_id' => $record->barangay_id,
                    'barangay' => $areaNames[$record->barangay_id], 'family' => trim((string) $record->family_number),
                    'household' => $validHousehold ? $this->baseHouseholdNumber($household->household_number) : null,
                    'household_number' => $validHousehold ? trim((string) $household->household_number) : null,
                    'sex' => $sexLabel, 'age' => $age, 'relationship' => $record->relationship_to_head,
                    'senior' => $seniorByAge || $seniorByRemarks,
                    'pwd' => PopulationMarkers::has($record->remarks, 'pwd'),
                ]);
            }
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
            'detailGroups' => $withNames ? $this->detailGroups($section, $namedRecords, $householdRows, $areaNames) : collect(),
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

    private function detailGroups(string $section, $members, $households, $areaNames)
    {
        $groups = collect();
        if ($section === 'households') {
            foreach ($households as $household) {
                $number = $this->baseHouseholdNumber($household->household_number);
                $key = $household->barangay_id.'|household:'.$number;
                $groups->put($key, ['title' => 'Household '.($number !== '' ? $number : '(number not encoded)'),
                    'barangay' => $areaNames[$household->barangay_id], 'household_number' => $number, 'members' => collect()]);
            }
        }
        $householdSizes = $members->filter(fn ($member) => $member['household'] !== null)
            ->countBy(fn ($member) => $member['barangay_id'].'|household:'.$member['household']);
        foreach ($members as $member) {
            if (($section === 'seniors' && ! $member['senior']) || ($section === 'pwd' && ! $member['pwd'])) {
                continue;
            }
            $householdKey = $member['barangay_id'].'|household:'.$member['household'];
            if ($section === 'families') {
                if ($member['family'] !== '') {
                    $key = $member['barangay_id'].'|family:'.$member['family'];
                    $title = 'Family '.$member['family'];
                } elseif ($member['household'] !== null && $householdSizes->get($householdKey) === 1) {
                    $key = $householdKey;
                    $title = 'Single-person family · Household '.$member['household'];
                } else {
                    continue; // Unidentified families must not become invented family groups.
                }
            } elseif ($section === 'households') {
                if (! $groups->has($householdKey) || $member['household'] === null) {
                    continue;
                }
                $key = $householdKey;
                $title = $groups[$key]['title'];
            } else {
                $key = 'residents';
                $title = self::SECTIONS[$section];
            }
            if (! $groups->has($key)) {
                $groups->put($key, ['title' => $title, 'barangay' => $member['barangay'], 'members' => collect()]);
            }
            $groups[$key]['members']->push($member);
        }
        return $groups->map(function ($group) use ($section) {
            if (in_array($section, ['families', 'households'], true)) {
                $group['head_name'] = $this->groupHead($group, $section);
            }
            return $group;
        })->sortBy(fn ($group) => $group['barangay'].' '.$group['title'], SORT_NATURAL)->values();
    }

    private function groupHead(array $group, string $section): string
    {
        $members = $group['members'];
        $relationship = fn ($member) => mb_strtolower(trim((string) $member['relationship']));
        $generic = ['head', 'self'];
        $household = ['household head', 'head of household'];
        $family = ['family head', 'head of family'];
        if ($section === 'families') {
            $heads = $members->filter(fn ($member) => in_array($relationship($member), $family, true));
            if ($heads->isEmpty()) {
                $heads = $members->filter(fn ($member) => in_array($relationship($member), array_merge($generic, $household), true));
            }
        } else {
            $heads = $members->filter(fn ($member) => in_array($relationship($member), $household, true));
            if ($heads->isEmpty()) {
                $heads = $members->filter(fn ($member) => in_array($relationship($member), $generic, true));
                // Additional decimal families must not replace the base household head.
                $baseHeads = $heads->filter(fn ($member) => $member['household_number'] === $group['household_number']);
                if ($baseHeads->isNotEmpty()) {
                    $heads = $baseHeads;
                }
            }
        }
        if ($heads->count() === 1) {
            return $heads->first()['name'] ?: 'Head name not encoded';
        }
        if ($heads->count() > 1) {
            return 'Multiple heads recorded - needs verification';
        }
        if ($members->count() === 1 && $relationship($members->first()) === '') {
            return $members->first()['name'] ?: 'Head name not encoded';
        }
        return 'Head not identified';
    }

    private function baseHouseholdNumber(string $number): string
    {
        $number = trim($number);

        return preg_match('/^([0-9]+)\.[0-9]+$/D', $number, $match) ? $match[1] : $number;
    }
}
