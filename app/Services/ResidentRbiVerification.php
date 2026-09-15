<?php

namespace App\Services;

use App\Models\Inhabitant;
use App\Models\User;
use Illuminate\Support\Collection;

class ResidentRbiVerification
{
    public function recordsFor(int $barangayId): Collection
    {
        return Inhabitant::with('household')->where('barangay_id', $barangayId)->get();
    }

    public function check(User $resident, ?Collection $records = null): array
    {
        $records ??= $resident->barangay_id ? $this->recordsFor($resident->barangay_id) : collect();
        $name = $this->normalize($resident->name);
        $exact = collect();
        $possible = collect();

        foreach ($records as $record) {
            if ((int) $record->barangay_id !== (int) $resident->barangay_id || $name === '') {
                continue;
            }
            $names = [
                $this->normalize($record->fullName()),
                $this->normalize(implode(' ', [$record->last_name, $record->first_name, $record->middle_name, $record->suffix])),
            ];
            if (in_array($name, $names, true)) {
                $exact->push($record);
                continue;
            }
            foreach ($names as $candidate) {
                similar_text($name, $candidate, $similarity);
                if ($similarity >= 85 || $name === $this->normalize($record->first_name.' '.$record->last_name.' '.$record->suffix)) {
                    $possible->push($record);
                    break;
                }
            }
        }

        $matches = $exact->isNotEmpty() ? $exact : $possible;
        $record = $exact->count() === 1 ? $exact->first() : null;
        $eligible = $record
            && $record->status === Inhabitant::STATUS_ACTIVE
            && $record->residence_status === Inhabitant::RESIDENCE_HERE
            && (! $record->resident_user_id || (int) $record->resident_user_id === (int) $resident->id);

        [$label, $note] = match (true) {
            (bool) $eligible => ['Resident record found', 'Automatic RBI check: this name matches one active resident record marked as living in Barangay '.$resident->barangay?->name.'. Cleared for account approval based on the registry match.'],
            $exact->count() > 1 => ['Multiple matching records', 'More than one RBI record has this name. Resolve the identity in the registry before approval.'],
            $record && $record->status !== Inhabitant::STATUS_ACTIVE => ['Not an active resident', 'The matching record is inactive or migrated out. Verify and update the registry before approval.'],
            $record && $record->residence_status !== Inhabitant::RESIDENCE_HERE => ['Residency needs verification', 'The matching RBI record is marked: '.$record->residenceLabel().'. Confirm residency and update the registry before approval.'],
            $record !== null => ['Record already linked', 'This RBI record is linked to another account. Resolve the account identity before approval.'],
            $possible->isNotEmpty() => ['Possible name match', 'A similar name was found, but residency is not yet cleared. Verify the full name and correct the account or RBI record before approval.'],
            default => ['No RBI match found', 'No matching resident record was found in this barangay. Verify residency and add or correct the consolidated RBI record before approval.'],
        };

        return ['eligible' => (bool) $eligible, 'label' => $label, 'note' => $note, 'matches' => $matches->take(3)];
    }

    private function normalize(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower($name))));
    }
}
