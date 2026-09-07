<?php

namespace App\Support;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\BarangayRbiFamily;
use App\Models\DeceasedInhabitant;
use App\Models\Household;
use App\Models\Inhabitant;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RbiReportRegistry
{
    private static function normalize(string $value): string
    {
        return mb_strtoupper(preg_replace('/\s+/', ' ', trim($value)));
    }

    private static function nameKey(string $last, string $first, ?string $middle): string
    {
        return self::normalize($last).'|'.self::normalize(trim($first.' '.$middle));
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['registry' => $message]);
    }

    /** Called inside the controller transaction with the barangay and report locked. */
    public static function add(Barangay $barangay, BarangayRbiUpdate $report): array
    {
        $rows = array_map([HouseholdRbi::class, 'normalize'], $report->rows ?? []);
        Validator::make(['rows' => $rows], [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.household_head' => ['required', 'string', 'max:255'],
            'rows.*.inhabitant_name' => ['required', 'string', 'max:255'],
            'rows.*.sex' => ['required', 'in:Male,Female'],
            'rows.*.birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ])->validate();
        $residents = Inhabitant::where('barangay_id', $barangay->id)->get();
        $deceased = DeceasedInhabitant::where('barangay_id', $barangay->id)->get();
        $households = Household::where('barangay_id', $barangay->id)->get();
        $knownFamilies = BarangayRbiFamily::whereIn('household_id', $households->pluck('id'))->get();
        $resolved = [];
        $created = $linked = 0;
        foreach ($rows as $index => &$row) {
            $parts = array_map('trim', explode(',', $row['inhabitant_name']));
            if (count($parts) < 2 || count($parts) > 3 || $parts[0] === '' || $parts[1] === '') {
                self::fail('Member '.($index + 1).': use Last Name, First Name, Middle Name before adding to RBI.');
            }
            [$last, $first] = $parts;
            $middle = $parts[2] ?? null;
            $suffix = $row['suffix'] ?? '';
            $key = self::nameKey($last, $first, $middle);
            $date = ($row['birth_date'] ?? '') ?: null;
            $matchesName = fn ($person) => self::nameKey($person->last_name, $person->first_name, $person->middle_name) === $key && self::normalize((string) $person->suffix) === self::normalize($suffix);
            $matchesDate = fn ($person) => $date === null || $person->birth_date === null || $person->birth_date->format('Y-m-d') === $date;
            if ($deceased->filter($matchesName)->contains($matchesDate)) self::fail($row['inhabitant_name'].' matches a deceased record. Review the form before adding to RBI.');

            $head = self::normalize($row['household_head']);
            if (! empty($row['household_id'])) {
                $household = $households->firstWhere('id', (int) $row['household_id']);
                if (! $household) self::fail('The selected household does not belong to your barangay.');
            } elseif (filled($row['household_number'] ?? null)) {
                $number = trim($row['household_number']);
                $household = $households->firstWhere('household_number', $number);
                if (! $household) {
                    $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => $number, 'address' => $row['complete_address'] ?? null]);
                    $households->push($household);
                }
            } elseif (isset($resolved[$head])) {
                $household = $resolved[$head];
            } else {
                $heads = $residents->filter(fn ($person) => self::normalize($person->fullName()) === $head
                    || self::normalize($person->last_name.', '.trim($person->first_name.' '.$person->middle_name)) === $head);
                $ids = $heads->pluck('household_id')->merge($knownFamilies
                    ->filter(fn ($family) => self::normalize($family->household_head) === $head)
                    ->pluck('household_id'))->unique();
                if ($ids->count() > 1) self::fail('Several households match '.$row['household_head'].'. Select the correct household in the form.');
                $household = $ids->count() === 1 ? $households->firstWhere('id', $ids->first()) : null;
                if (! $household) {
                    $next = (int) $households->pluck('household_number')->filter(fn ($number) => ctype_digit((string) $number))->max() + 1;
                    $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => (string) $next]);
                    $households->push($household);
                }
            }
            if ($household->household_number === 'Not recorded') self::fail('Select a numbered household before adding members to RBI.');
            $resolved[$head] = $household;

            if (! empty($row['inhabitant_id'])) {
                $person = $residents->firstWhere('id', (int) $row['inhabitant_id']);
                if (! $person || ! $matchesName($person) || ! $matchesDate($person)) self::fail('The linked resident no longer matches '.$row['inhabitant_name'].'. Review the form.');
            } else {
                $matches = $residents->filter($matchesName)->filter($matchesDate);
                if ($matches->count() > 1) self::fail('Multiple registry records match '.$row['inhabitant_name'].'. Select the existing resident in the form.');
                $person = $matches->first();
            }
            if ($person) {
                if ($person->household_id !== $household->id || $person->status !== Inhabitant::STATUS_ACTIVE) {
                    self::fail($row['inhabitant_name'].' already belongs to another household or is not active. Review the registry first.');
                }
                $linked++;
            } else {
                $person = Inhabitant::create([
                    'barangay_id' => $barangay->id, 'household_id' => $household->id,
                    'last_name' => $last, 'first_name' => $first, 'middle_name' => $middle, 'suffix' => $suffix ?: null,
                    'complete_address' => ($row['complete_address'] ?? '') ?: null,
                    'recorded_age' => filled($row['recorded_age'] ?? null) ? (int) $row['recorded_age'] : null,
                    'education_level' => ($row['education_level'] ?? '') ?: null,
                    'religion' => ($row['religion'] ?? '') ?: null,
                    'sex' => $row['sex'], 'birth_date' => $date,
                    'birth_place' => ($row['birth_place'] ?? '') ?: null,
                    'civil_status' => ($row['civil_status'] ?? '') ?: null,
                    'occupation' => ($row['occupation'] ?? '') ?: null,
                    'relationship_to_head' => ($row['relationship'] ?? '') ?: null,
                    'family_number' => $household->inhabitants()->whereNotNull('family_number')->value('family_number') ?: $household->household_number,
                    'status' => Inhabitant::STATUS_ACTIVE,
                    'residence_status' => Inhabitant::RESIDENCE_UNCONFIRMED,
                    'residence_source' => 'Monthly RBI form; current residence needs confirmation',
                    'remarks' => trim(($row['remarks'] ?? '').' [Monthly RBI form '.$report->id.', '.$report->reporting_month->format('Y-m').']'),
                ]);
                $residents->push($person);
                $created++;
            }
            $row['household_number'] = $household->household_number;
            $row['household_id'] = (string) $household->id;
            $row['inhabitant_id'] = (string) $person->id;
        }
        unset($row);
        $report->update(['rows' => $rows]);
        return compact('rows', 'created', 'linked');
    }
}
